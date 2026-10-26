<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function __construct(private readonly AuditService $audit) {}

    /** Perfis que o utilizador actual pode atribuir (só quem gere permissões atribui super-admin). */
    private function assignableRoles(Request $request): array
    {
        $roles = User::STAFF_ROLES;
        if (! $request->user()->can('roles.manage')) {
            unset($roles['super-admin']);
        }

        return $roles;
    }

    public function index(Request $request): View
    {
        $staff = User::with('roles')->where('is_staff', true)
            ->when($request->query('q'), fn ($q, $t) => $q->where(fn ($w) => $w->where('name', 'like', "%{$t}%")->orWhere('email', 'like', "%{$t}%")))
            ->when($request->query('perfil'), fn ($q, $r) => $q->role($r))
            ->when($request->query('estado'), fn ($q, $s) => $q->where('status', $s))
            ->orderBy('name')->paginate(20)->withQueryString();

        return view('admin.staff.index', ['staff' => $staff]);
    }

    public function create(Request $request): View
    {
        return view('admin.staff.form', ['user' => null, 'roles' => $this->assignableRoles($request)]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', Rule::in(array_keys($this->assignableRoles($request)))],
        ]);

        $user = DB::transaction(function () use ($data, $request) {
            $user = User::create([
                'name' => $data['name'], 'email' => strtolower($data['email']), 'phone' => $data['phone'] ?? null,
                'password' => Str::password(32), 'status' => 'active', 'is_staff' => true, 'created_by' => $request->user()->id,
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();
            $user->syncRoles([$data['role']]);
            $this->audit->log('staff.created', $user, null, ['role' => $data['role']]);

            return $user;
        });

        // O funcionário define a palavra-passe através da ligação enviada por email.
        Password::sendResetLink(['email' => $user->email]);

        return redirect()->route('admin.staff.show', $user)->with('success', 'Funcionário registado. Foi enviado um email para definição da palavra-passe.');
    }

    public function show(User $user): View
    {
        abort_unless($user->is_staff, 404);
        $sessions = DB::table('sessions')->where('user_id', $user->id)->orderByDesc('last_activity')->get();

        return view('admin.staff.show', [
            'user' => $user->load('roles'),
            'activity' => AuditLog::where('user_id', $user->id)->latest('id')->paginate(25),
            'sessions' => $sessions,
        ]);
    }

    public function edit(Request $request, User $user): View
    {
        abort_unless($user->is_staff, 404);

        return view('admin.staff.form', ['user' => $user, 'roles' => $this->assignableRoles($request)]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->is_staff, 404);
        $roles = $this->assignableRoles($request);
        if ($user->hasRole('super-admin') && ! isset($roles['super-admin'])) {
            abort(403, 'Não pode alterar um Super Administrador.');
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', Rule::in(array_keys($roles))],
        ]);
        if ($user->id === $request->user()->id && $data['role'] !== $user->getRoleNames()->first()) {
            return back()->with('error', 'Não pode alterar o seu próprio perfil de acesso.');
        }
        $this->guardLastSuperAdmin($user, $data['role']);

        $oldRole = $user->getRoleNames()->first();
        $user->update(['name' => $data['name'], 'email' => strtolower($data['email']), 'phone' => $data['phone'] ?? null]);
        $user->syncRoles([$data['role']]);
        if ($oldRole !== $data['role']) {
            $this->audit->log('staff.role_changed', $user, ['role' => $oldRole], ['role' => $data['role']]);
        }

        return redirect()->route('admin.staff.show', $user)->with('success', 'Dados actualizados.');
    }

    public function toggle(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->is_staff, 404);
        if ($user->id === $request->user()->id) {
            return back()->with('error', 'Não pode desactivar a sua própria conta.');
        }
        if ($user->hasRole('super-admin') && ! $request->user()->can('roles.manage')) {
            abort(403);
        }
        $new = $user->isActive() ? 'inactive' : 'active';
        if ($new === 'inactive') {
            $this->guardLastSuperAdmin($user, null);
        }
        $user->update(['status' => $new]);
        if ($new === 'inactive') {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }
        $this->audit->log('staff.'.($new === 'active' ? 'activated' : 'deactivated'), $user, null, null, $request->input('reason'));

        return back()->with('success', $new === 'active' ? 'Conta activada.' : 'Conta desactivada e sessões terminadas.');
    }

    public function terminateSessions(User $user): RedirectResponse
    {
        abort_unless($user->is_staff, 404);
        $count = DB::table('sessions')->where('user_id', $user->id)->delete();
        $user->forceFill(['remember_token' => Str::random(60)])->saveQuietly();
        $this->audit->log('staff.sessions_terminated', $user, null, ['count' => $count]);

        return back()->with('success', "{$count} sessão(ões) terminada(s).");
    }

    private function guardLastSuperAdmin(User $user, ?string $newRole): void
    {
        if ($user->hasRole('super-admin') && $newRole !== 'super-admin'
            && User::role('super-admin')->where('status', 'active')->count() <= 1) {
            abort(422, 'Tem de existir pelo menos um Super Administrador activo.');
        }
    }
}

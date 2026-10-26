<?php

namespace App\Services;

use App\Events\DocumentReviewed;
use App\Models\Client;
use App\Models\ClientDocument;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class ClientService
{
    public function __construct(
        private readonly NumberGenerator $numbers,
        private readonly AuditService $audit,
    ) {}

    public const CLIENT_FIELDS = [
        'full_name', 'document_type', 'document_number', 'document_expiry', 'nuit', 'birth_date', 'nationality',
        'phone', 'phone_alt', 'email', 'occupation', 'monthly_income',
        'emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_relationship',
    ];

    public const ADDRESS_FIELDS = ['province', 'district', 'locality', 'neighborhood', 'address_line'];

    public const EMPLOYMENT_FIELDS = [
        'employment_type', 'employer_name', 'position', 'employer_phone', 'employer_address', 'start_date',
        'monthly_income', 'other_income', 'monthly_expenses',
    ];

    /** Registo de cliente pelo balcão; opcionalmente cria a conta do portal. */
    public function create(array $data, User $by, bool $createPortalAccount = false): Client
    {
        return DB::transaction(function () use ($data, $by, $createPortalAccount) {
            $user = null;
            if ($createPortalAccount && ! empty($data['email'])) {
                $user = User::create([
                    'name' => $data['full_name'],
                    'email' => $data['email'],
                    'phone' => $data['phone'] ?? null,
                    'password' => Str::password(24),
                    'status' => 'active',
                    'is_staff' => false,
                    'created_by' => $by->id,
                ]);
                $user->assignRole(User::CLIENT_ROLE);
            }

            $client = Client::create(Arr::only($this->normalize($data), self::CLIENT_FIELDS) + [
                'client_number' => $this->numbers->next('client', false),
                'user_id' => $user?->id,
                'status' => 'active',
                'kyc_status' => 'pending',
                'notes' => $data['notes'] ?? null,
                'created_by' => $by->id,
            ]);
            $this->saveRelated($client, $data);

            if ($user) {
                // O cliente define a sua própria palavra-passe através da ligação de recuperação.
                DB::afterCommit(fn () => Password::sendResetLink(['email' => $user->email]));
            }

            return $client;
        });
    }

    public function registerFromPortal(User $user, array $data): Client
    {
        $client = Client::create(Arr::only($this->normalize($data), self::CLIENT_FIELDS) + [
            'client_number' => $this->numbers->next('client', false),
            'user_id' => $user->id,
            'email' => $user->email,
            'status' => 'active',
            'kyc_status' => 'pending',
            'created_by' => $user->id,
        ]);
        $this->saveRelated($client, $data);

        return $client;
    }

    public function update(Client $client, array $data, bool $allowIdentityChange = true): Client
    {
        return DB::transaction(function () use ($client, $data, $allowIdentityChange) {
            $fields = self::CLIENT_FIELDS;
            if (! $allowIdentityChange) {
                // O cliente não pode alterar elementos de identificação já verificados.
                $fields = array_diff($fields, ['document_type', 'document_number', 'nuit', 'full_name', 'birth_date']);
            }
            $client->fill(Arr::only($this->normalize($data), $fields));
            if ($client->isDirty(['document_type', 'document_number', 'nuit']) && $client->kyc_status === 'verified') {
                $client->kyc_status = 'pending'; // nova verificação obrigatória
            }
            $nameChanged = $client->isDirty('full_name');
            $client->save();
            $this->saveRelated($client, $data);

            if ($client->user && $nameChanged) {
                $client->user->update(['name' => $client->full_name]);
            }

            return $client;
        });
    }

    private function saveRelated(Client $client, array $data): void
    {
        $address = array_filter(Arr::only($data, self::ADDRESS_FIELDS), fn ($v) => $v !== null && $v !== '');
        if (! empty($address['province'])) {
            $client->addresses()->updateOrCreate(['is_primary' => true], $address + ['type' => 'residential']);
        }

        $employment = Arr::only($data['employment'] ?? [], self::EMPLOYMENT_FIELDS);
        foreach (['monthly_income', 'other_income', 'monthly_expenses'] as $money) {
            if (isset($employment[$money]) && $employment[$money] !== '') {
                $employment[$money] = Money::toDb($employment[$money]);
            }
        }
        $employment = array_filter($employment, fn ($v) => $v !== null && $v !== '');
        if ($employment) {
            $client->employment()->updateOrCreate([], $employment);
        }
    }

    private function normalize(array $data): array
    {
        if (isset($data['monthly_income']) && $data['monthly_income'] !== '') {
            $data['monthly_income'] = Money::toDb($data['monthly_income']);
        }
        if (isset($data['document_number'])) {
            $data['document_number'] = strtoupper(trim($data['document_number']));
        }
        foreach (['nuit', 'phone_alt', 'email', 'document_expiry', 'birth_date'] as $nullable) {
            if (array_key_exists($nullable, $data) && $data[$nullable] === '') {
                $data[$nullable] = null;
            }
        }

        return $data;
    }

    public function reviewDocument(ClientDocument $document, User $user, string $status, ?string $reason): ClientDocument
    {
        $old = $document->status;
        $document->update([
            'status' => $status,
            'rejection_reason' => $status === 'rejected' ? $reason : null,
            'verified_by' => $user->id,
            'verified_at' => now(),
        ]);
        $this->audit->log('document.'.$status, $document, ['status' => $old], ['status' => $status], $reason);

        foreach ($document->applications as $application) {
            $application->reviews()->create([
                'user_id' => $user->id,
                'action' => $status === 'verified' ? 'document_verified' : 'document_rejected',
                'notes' => $document->typeLabel().($reason ? ": {$reason}" : ''),
            ]);
        }

        DocumentReviewed::dispatch($document);

        return $document;
    }

    public function setKycStatus(Client $client, User $user, string $status, ?string $notes = null): void
    {
        $old = $client->kyc_status;
        $client->kyc_status = $status;
        $client->kyc_verified_by = $user->id;
        $client->kyc_verified_at = now();
        $client->save();
        $this->audit->log('client.kyc_'.$status, $client, ['kyc_status' => $old], ['kyc_status' => $status], $notes);
    }

    public function setStatus(Client $client, string $status, ?string $reason = null): void
    {
        $client->update(['status' => $status]);
        if ($client->user && $status !== 'active') {
            DB::table('sessions')->where('user_id', $client->user_id)->delete();
        }
        if ($client->user) {
            $client->user->update(['status' => $status === 'active' ? 'active' : 'inactive']);
        }
        $this->audit->log('client.status', $client, null, ['status' => $status], $reason);
    }
}

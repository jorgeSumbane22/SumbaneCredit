<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\ReconciliationRecord;
use App\Services\Payments\ReconciliationService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ReconciliationController extends Controller
{
    public function __construct(private readonly ReconciliationService $reconciliation) {}

    public function index(): View
    {
        return view('admin.payments.reconciliation', [
            'records' => ReconciliationRecord::with('loan', 'performer')->latest('id')->paginate(20),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start', 'before_or_equal:today'],
            'declared_amount' => ['required', 'string'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $amount = Money::parseInput($data['declared_amount']);
        if ($amount === null) {
            return back()->withInput()->withErrors(['declared_amount' => 'Valor inválido.']);
        }

        $record = $this->reconciliation->reconcileMethodPeriod($data['method'], $data['period_start'], $data['period_end'], $amount, $request->user(), $data['notes'] ?? null);

        return back()->with($record->status === 'matched' ? 'success' : 'warning', $record->status === 'matched'
            ? 'Reconciliação sem diferenças.'
            : 'Diferença de '.Money::format($record->difference).' entre o extracto e os pagamentos confirmados.');
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disbursements', function (Blueprint $table) {
            $table->id();
            $table->string('disbursement_number', 30)->unique();
            $table->foreignId('loan_id')->constrained('loans')->restrictOnDelete();
            $table->decimal('gross_amount', 15, 2);
            $table->decimal('deducted_fees', 15, 2)->default(0);
            $table->decimal('net_amount', 15, 2);
            $table->string('method', 30);
            $table->string('destination_account', 100)->nullable();
            $table->string('reference', 100)->nullable();
            $table->string('status', 20)->default('pending')->index(); // pending|authorized|completed|cancelled
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('requested_at')->nullable();
            $table->foreignId('authorized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('authorized_at')->nullable();
            $table->foreignId('executed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('disbursed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('proof_path')->nullable();
            $table->text('notes')->nullable();
            $table->string('cancellation_reason')->nullable();
            // Igual ao loan_id enquanto o desembolso não for cancelado: impede desembolsos duplicados.
            $table->unsignedBigInteger('active_loan_key')->nullable()->unique();
            $table->timestamps();
            $table->unique(['method', 'reference']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_number', 30)->unique();
            $table->string('receipt_number', 30)->nullable()->unique();
            $table->foreignId('loan_id')->constrained('loans')->restrictOnDelete();
            $table->foreignId('client_id')->constrained('clients')->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->decimal('allocated_amount', 15, 2)->default(0);
            $table->string('method', 30);
            $table->string('reference', 100)->nullable();
            // "metodo|referencia" enquanto o pagamento não estiver rejeitado: impede referências duplicadas.
            $table->string('reference_key', 150)->nullable()->unique();
            $table->string('payer_phone', 30)->nullable();
            $table->date('paid_at');
            $table->string('type', 20)->default('regular'); // regular|settlement
            $table->string('status', 20)->default('pending')->index(); // pending|confirmed|rejected|reversed
            $table->string('source', 20)->default('staff'); // staff|client_portal|gateway
            $table->string('proof_path')->nullable();
            $table->text('notes')->nullable();
            $table->json('meta')->nullable(); // ex.: dispensas de juros aplicadas numa liquidação antecipada
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->timestamps();
            $table->index(['loan_id', 'status']);
            $table->index('paid_at');
        });

        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments')->restrictOnDelete();
            $table->foreignId('loan_installment_id')->constrained('loan_installments')->restrictOnDelete();
            $table->decimal('principal', 15, 2)->default(0);
            $table->decimal('interest', 15, 2)->default(0);
            $table->decimal('fee', 15, 2)->default(0);
            $table->decimal('penalty', 15, 2)->default(0);
            $table->decimal('total', 15, 2);
            $table->boolean('is_reversed')->default(false);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('payment_reversals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->unique()->constrained('payments')->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->text('reason');
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('penalties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('loans')->restrictOnDelete();
            $table->foreignId('loan_installment_id')->constrained('loan_installments')->restrictOnDelete();
            $table->string('type', 30)->default('late_fee');
            $table->decimal('amount', 15, 2);
            $table->date('applied_on');
            $table->string('status', 20)->default('applied'); // applied|waived
            $table->foreignId('waived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('waived_at')->nullable();
            $table->string('waiver_reason')->nullable();
            $table->timestamps();
            $table->unique(['loan_installment_id', 'type']);
        });

        // Razão (ledger) imutável. Débito aumenta o valor em dívida, crédito reduz.
        Schema::create('financial_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_number', 30)->unique();
            $table->foreignId('loan_id')->constrained('loans')->restrictOnDelete();
            $table->foreignId('client_id')->constrained('clients')->restrictOnDelete();
            $table->string('type', 40)->index();
            $table->string('component', 20); // principal|interest|fee|penalty|cash
            $table->string('direction', 10); // debit|credit
            $table->decimal('amount', 15, 2);
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('description')->nullable();
            $table->timestamp('posted_at')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['source_type', 'source_id']);
            $table->index(['loan_id', 'component']);
        });

        Schema::create('reconciliation_records', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20); // loan|method_period
            $table->foreignId('loan_id')->nullable()->constrained('loans')->nullOnDelete();
            $table->string('method', 30)->nullable();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->decimal('expected_amount', 15, 2);
            $table->decimal('actual_amount', 15, 2);
            $table->decimal('difference', 15, 2);
            $table->string('status', 20); // matched|mismatch
            $table->json('details')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_records');
        Schema::dropIfExists('financial_transactions');
        Schema::dropIfExists('penalties');
        Schema::dropIfExists('payment_reversals');
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('disbursements');
    }
};

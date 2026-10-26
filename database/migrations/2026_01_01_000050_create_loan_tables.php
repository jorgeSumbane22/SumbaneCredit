<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->string('loan_number', 30)->unique();
            $table->string('contract_number', 30)->unique();
            $table->foreignId('client_id')->constrained('clients')->restrictOnDelete();
            $table->foreignId('loan_application_id')->unique()->constrained('loan_applications')->restrictOnDelete();
            $table->foreignId('loan_product_id')->constrained('loan_products')->restrictOnDelete();
            $table->foreignId('loan_product_version_id')->constrained('loan_product_versions')->restrictOnDelete();

            // Condições contratuais (cópia imutável das condições aprovadas)
            $table->decimal('principal_amount', 15, 2);
            $table->decimal('interest_rate', 9, 4);
            $table->string('rate_period', 10);
            $table->decimal('rate_per_period', 14, 10);
            $table->string('repayment_frequency', 20);
            $table->string('amortization_method', 30);
            $table->unsignedSmallInteger('term');
            $table->json('fees')->nullable();
            $table->decimal('upfront_fees', 15, 2)->default(0);
            $table->decimal('financed_fees', 15, 2)->default(0);
            $table->decimal('total_interest', 15, 2)->default(0);
            $table->decimal('total_contract_amount', 15, 2)->default(0);
            $table->string('penalty_type', 20)->default('none');
            $table->decimal('penalty_value', 12, 4)->default(0);
            $table->unsignedSmallInteger('penalty_grace_days')->default(0);
            $table->string('early_settlement_rule', 40);
            $table->json('terms_snapshot');

            // Situação (valores derivados das prestações; recalculados após cada movimento)
            $table->string('status', 30)->default('awaiting_contract')->index();
            $table->decimal('principal_outstanding', 15, 2)->default(0);
            $table->decimal('total_paid', 15, 2)->default(0);
            $table->decimal('total_outstanding', 15, 2)->default(0);
            $table->unsignedInteger('days_past_due')->default(0)->index();
            $table->date('next_due_date')->nullable()->index();
            $table->date('first_due_date')->nullable();
            $table->date('maturity_date')->nullable();
            $table->unsignedTinyInteger('schedule_version')->default(1);

            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('contract_signed_at')->nullable();
            $table->foreignId('formalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('signed_contract_path')->nullable();
            $table->date('disbursed_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('loan_officer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['client_id', 'status']);
        });

        Schema::create('loan_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('loans')->restrictOnDelete();
            $table->unsignedTinyInteger('schedule_version')->default(1);
            $table->unsignedSmallInteger('number');
            $table->date('due_date');
            $table->decimal('opening_balance', 15, 2);
            $table->decimal('principal_due', 15, 2);
            $table->decimal('interest_due', 15, 2);
            $table->decimal('fee_due', 15, 2)->default(0);
            $table->decimal('penalty_due', 15, 2)->default(0);
            $table->decimal('principal_paid', 15, 2)->default(0);
            $table->decimal('interest_paid', 15, 2)->default(0);
            $table->decimal('fee_paid', 15, 2)->default(0);
            $table->decimal('penalty_paid', 15, 2)->default(0);
            $table->decimal('interest_waived', 15, 2)->default(0);
            $table->decimal('penalty_waived', 15, 2)->default(0);
            $table->decimal('total_due', 15, 2);
            $table->decimal('total_paid', 15, 2)->default(0);
            $table->string('status', 20)->default('pending')->index(); // pending|partial|paid|restructured
            $table->unsignedInteger('days_overdue')->default(0);
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->unique(['loan_id', 'schedule_version', 'number']);
            $table->index(['due_date', 'status']);
        });

        Schema::create('loan_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('loans')->cascadeOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('loan_restructurings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('loans')->restrictOnDelete();
            $table->unsignedTinyInteger('previous_schedule_version');
            $table->unsignedTinyInteger('new_schedule_version');
            $table->decimal('outstanding_principal', 15, 2);
            $table->decimal('capitalized_amount', 15, 2)->default(0);
            $table->decimal('new_principal', 15, 2);
            $table->unsignedSmallInteger('new_term');
            $table->decimal('new_interest_rate', 9, 4);
            $table->string('new_amortization_method', 30);
            $table->string('new_repayment_frequency', 20);
            $table->date('first_due_date');
            $table->text('reason');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('guarantors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_application_id')->constrained('loan_applications')->cascadeOnDelete();
            $table->foreignId('loan_id')->nullable()->constrained('loans')->nullOnDelete();
            $table->string('full_name');
            $table->string('document_type', 20)->default('bi');
            $table->string('document_number', 50);
            $table->string('phone', 30);
            $table->string('relationship', 60)->nullable();
            $table->string('occupation')->nullable();
            $table->decimal('monthly_income', 15, 2)->nullable();
            $table->string('address')->nullable();
            $table->string('status', 20)->default('declared'); // declared|verified|rejected
            $table->timestamps();
        });

        Schema::create('collaterals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_application_id')->constrained('loan_applications')->cascadeOnDelete();
            $table->foreignId('loan_id')->nullable()->constrained('loans')->nullOnDelete();
            $table->string('type', 40); // veiculo|imovel|equipamento|deposito|outro
            $table->string('description');
            $table->decimal('estimated_value', 15, 2);
            $table->string('ownership_document')->nullable();
            $table->string('status', 20)->default('declared'); // declared|verified|released|executed
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collaterals');
        Schema::dropIfExists('guarantors');
        Schema::dropIfExists('loan_restructurings');
        Schema::dropIfExists('loan_status_histories');
        Schema::dropIfExists('loan_installments');
        Schema::dropIfExists('loans');
    }
};

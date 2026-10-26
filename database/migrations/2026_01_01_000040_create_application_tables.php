<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_applications', function (Blueprint $table) {
            $table->id();
            $table->string('application_number', 30)->unique();
            $table->foreignId('client_id')->constrained('clients')->restrictOnDelete();
            $table->foreignId('loan_product_id')->constrained('loan_products')->restrictOnDelete();
            $table->foreignId('loan_product_version_id')->constrained('loan_product_versions')->restrictOnDelete();
            $table->decimal('requested_amount', 15, 2);
            $table->unsignedSmallInteger('term');
            $table->string('repayment_frequency', 20);
            $table->string('amortization_method', 30);
            $table->text('purpose');
            $table->decimal('monthly_income', 15, 2);
            $table->decimal('other_income', 15, 2)->default(0);
            $table->decimal('monthly_expenses', 15, 2)->default(0);
            $table->decimal('existing_obligations', 15, 2)->default(0);
            $table->string('employment_type', 30)->nullable();
            $table->string('employer_name')->nullable();
            $table->string('occupation')->nullable();
            $table->decimal('simulated_installment', 15, 2)->nullable();
            $table->decimal('simulated_total', 15, 2)->nullable();
            $table->string('status', 30)->default('submitted')->index();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->decimal('approved_amount', 15, 2)->nullable();
            $table->unsignedSmallInteger('approved_term')->nullable();
            $table->text('decision_notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('info_request')->nullable();
            $table->json('consents')->nullable();
            $table->timestamp('declaration_accepted_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['client_id', 'status']);
        });

        Schema::create('loan_application_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_application_id')->constrained('loan_applications')->cascadeOnDelete();
            $table->foreignId('client_document_id')->constrained('client_documents')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['loan_application_id', 'client_document_id'], 'app_doc_unique');
        });

        // Histórico de análise e de decisões (inclui transições de estado).
        Schema::create('loan_application_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_application_id')->constrained('loan_applications')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 40)->index();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->nullable();
            $table->text('notes')->nullable();
            $table->json('data')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('credit_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_application_id')->constrained('loan_applications')->cascadeOnDelete();
            $table->foreignId('rule_set_id')->nullable()->constrained('credit_assessment_rule_sets')->nullOnDelete();
            $table->unsignedInteger('rules_version');
            $table->json('rules');
            $table->json('inputs');
            $table->json('indicators');
            $table->string('result', 20); // favorable|review|unfavorable
            $table->text('explanation');
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_assessments');
        Schema::dropIfExists('loan_application_reviews');
        Schema::dropIfExists('loan_application_documents');
        Schema::dropIfExists('loan_applications');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_products', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('slug', 120)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('requirements')->nullable();
            $table->text('contract_conditions')->nullable();
            $table->string('status', 20)->default('active')->index(); // active|inactive
            $table->boolean('is_public')->default(true);
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Cada alteração às condições financeiras cria uma nova versão.
        // Pedidos e empréstimos referenciam a versão em vigor no momento — nunca é alterada.
        Schema::create('loan_product_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_product_id')->constrained('loan_products')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->decimal('min_amount', 15, 2);
            $table->decimal('max_amount', 15, 2);
            $table->unsignedSmallInteger('min_term');
            $table->unsignedSmallInteger('max_term');
            $table->decimal('interest_rate', 9, 4);
            $table->string('rate_period', 10); // monthly|annual
            $table->json('allowed_frequencies'); // ["monthly","biweekly","weekly"]
            $table->json('amortization_methods'); // ["annuity","equal_principal","flat"]
            $table->json('fees')->nullable(); // [{name,type:fixed|percent,value,charge:upfront|financed}]
            $table->string('penalty_type', 20)->default('none'); // none|fixed|percent
            $table->decimal('penalty_value', 12, 4)->default(0);
            $table->unsignedSmallInteger('penalty_grace_days')->default(0);
            $table->string('early_settlement_rule', 40)->default('waive_future_interest'); // waive_future_interest|full_contractual
            $table->json('eligibility_rules')->nullable();
            $table->text('change_notes')->nullable();
            $table->timestamp('effective_from');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['loan_product_id', 'version']);
        });

        Schema::table('loan_products', function (Blueprint $table) {
            $table->foreign('current_version_id')->references('id')->on('loan_product_versions')->nullOnDelete();
        });

        Schema::create('credit_assessment_rule_sets', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('version')->unique();
            $table->string('name');
            $table->json('rules');
            $table->boolean('is_active')->default(false)->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_assessment_rule_sets');
        Schema::table('loan_products', fn (Blueprint $table) => $table->dropForeign(['current_version_id']));
        Schema::dropIfExists('loan_product_versions');
        Schema::dropIfExists('loan_products');
    }
};

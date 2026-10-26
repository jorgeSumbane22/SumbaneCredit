<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->string('client_number', 30)->unique();
            $table->string('full_name');
            $table->string('document_type', 20)->default('bi'); // bi|passaporte|dire|outro
            $table->string('document_number', 50);
            $table->date('document_expiry')->nullable();
            $table->string('nuit', 20)->nullable()->unique();
            $table->date('birth_date')->nullable();
            $table->string('nationality', 60)->nullable();
            $table->string('phone', 30);
            $table->string('phone_alt', 30)->nullable();
            $table->string('email')->nullable()->index();
            $table->string('occupation')->nullable();
            $table->decimal('monthly_income', 15, 2)->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone', 30)->nullable();
            $table->string('emergency_contact_relationship', 60)->nullable();
            $table->string('status', 20)->default('active')->index(); // active|inactive|blocked
            $table->string('kyc_status', 20)->default('pending')->index(); // pending|verified|rejected
            $table->foreignId('kyc_verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('kyc_verified_at')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_demo')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['document_type', 'document_number']);
            $table->index('full_name');
        });

        Schema::create('client_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('type', 20)->default('residential');
            $table->string('province', 60);
            $table->string('district', 100)->nullable();
            $table->string('locality', 100)->nullable();
            $table->string('neighborhood', 100)->nullable();
            $table->string('address_line')->nullable();
            $table->boolean('is_primary')->default(true);
            $table->timestamps();
        });

        Schema::create('client_employment_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->unique()->constrained('clients')->cascadeOnDelete();
            $table->string('employment_type', 30)->nullable(); // employed|self_employed|informal|retired|other
            $table->string('employer_name')->nullable();
            $table->string('position')->nullable();
            $table->string('employer_phone', 30)->nullable();
            $table->string('employer_address')->nullable();
            $table->date('start_date')->nullable();
            $table->decimal('monthly_income', 15, 2)->nullable();
            $table->decimal('other_income', 15, 2)->nullable();
            $table->decimal('monthly_expenses', 15, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('client_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->restrictOnDelete();
            $table->string('type', 40)->index();
            $table->string('original_name');
            $table->string('disk', 30)->default('local');
            $table->string('path');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->string('checksum', 64);
            $table->string('status', 20)->default('pending')->index(); // pending|verified|rejected
            $table->text('notes')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_documents');
        Schema::dropIfExists('client_employment_details');
        Schema::dropIfExists('client_addresses');
        Schema::dropIfExists('clients');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('loans')->restrictOnDelete();
            $table->foreignId('client_id')->constrained('clients')->restrictOnDelete();
            $table->string('status', 20)->default('open')->index(); // open|promised|resolved|closed
            $table->string('priority', 20)->default('normal'); // low|normal|high
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('days_past_due')->default(0);
            $table->decimal('overdue_amount', 15, 2)->default(0);
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('last_contact_at')->nullable();
            $table->timestamp('next_action_at')->nullable()->index();
            $table->text('notes')->nullable();
            // Igual ao loan_id enquanto o processo estiver aberto: um processo aberto por empréstimo.
            $table->unsignedBigInteger('open_loan_key')->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('collection_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_id')->constrained('collections')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('channel', 20); // call|sms|email|visit|whatsapp
            $table->string('outcome', 40); // contacted|no_answer|promise|refused|wrong_number|other
            $table->text('notes')->nullable();
            $table->timestamp('contacted_at');
            $table->timestamp('next_action_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('payment_promises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_id')->constrained('collections')->cascadeOnDelete();
            $table->foreignId('loan_id')->constrained('loans')->restrictOnDelete();
            $table->decimal('promised_amount', 15, 2);
            $table->date('promised_date')->index();
            $table->string('status', 20)->default('pending')->index(); // pending|kept|broken|cancelled
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number', 30)->unique();
            $table->foreignId('client_id')->constrained('clients')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('subject');
            $table->string('category', 30); // credito|pagamentos|documentos|conta|outro
            $table->string('priority', 20)->default('normal'); // low|normal|high|urgent
            $table->string('status', 20)->default('open')->index(); // open|in_progress|awaiting_client|resolved|closed
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_reply_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ticket_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('message');
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->boolean('is_staff')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_replies');
        Schema::dropIfExists('support_tickets');
        Schema::dropIfExists('payment_promises');
        Schema::dropIfExists('collection_contacts');
        Schema::dropIfExists('collections');
    }
};

<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ClientDocument;
use App\Models\LoanApplication;
use App\Services\DocumentStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_cannot_access_other_clients_records_by_changing_ids(): void
    {
        $product = $this->product();
        $owner = $this->clientUser();
        $other = $this->clientUser();
        $loan = $this->activeLoan($owner, $product);
        $payment = $this->pay($loan, '500', '2026-01-20');
        $application = $loan->application;
        $doc = app(DocumentStorageService::class)->storeClientDocument($owner->client, UploadedFile::fake()->create('bi.pdf', 20, 'application/pdf'), 'bi');

        $this->actingAs($other);
        $this->get(route('client.loans.show', $loan))->assertForbidden();
        $this->get(route('client.loans.contract', $loan))->assertForbidden();
        $this->get(route('client.loans.statement', $loan))->assertForbidden();
        $this->get(route('client.applications.show', $application))->assertForbidden();
        $this->post(route('client.applications.cancel', $application))->assertForbidden();
        $this->get(route('client.payments.show', $payment))->assertForbidden();
        $this->get(route('client.payments.receipt', $payment))->assertForbidden();
        $this->get(route('client.documents.download', $doc))->assertForbidden();

        // O titular acede
        $this->actingAs($owner);
        $this->get(route('client.loans.show', $loan))->assertOk();
        $this->get(route('client.documents.download', $doc))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get(route('client.payments.receipt', $payment))->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_documents_are_stored_privately_with_safe_names(): void
    {
        $client = $this->clientUser();
        $this->actingAs($client)->post(route('client.documents.store'), [
            'type' => 'bi', 'file' => UploadedFile::fake()->create('meu bi.pdf', 100, 'application/pdf'),
        ])->assertSessionHas('success');

        $doc = ClientDocument::firstOrFail();
        $this->assertStringStartsWith("clients/{$client->client->id}/documents/", $doc->path);
        $this->assertStringEndsWith('.pdf', $doc->path);
        $this->assertStringNotContainsString('meu bi', $doc->path);
        $this->assertTrue(Storage::disk('local')->exists($doc->path));
        $this->assertStringNotContainsString(public_path(), Storage::disk('local')->path($doc->path));
        $this->assertSame('pending', $doc->status);
        Storage::disk('local')->delete($doc->path);
    }

    public function test_executable_uploads_are_rejected(): void
    {
        $client = $this->clientUser();
        $this->actingAs($client)->post(route('client.documents.store'), [
            'type' => 'bi', 'file' => UploadedFile::fake()->create('shell.php', 5, 'application/x-php'),
        ])->assertSessionHasErrors('file');
        $this->actingAs($client)->post(route('client.documents.store'), [
            'type' => 'bi', 'file' => UploadedFile::fake()->create('grande.pdf', 9000, 'application/pdf'),
        ])->assertSessionHasErrors('file');
        $this->assertSame(0, ClientDocument::count());
    }

    public function test_client_cannot_attach_documents_of_another_client(): void
    {
        $product = $this->product();
        $owner = $this->clientUser();
        $doc = app(DocumentStorageService::class)->storeClientDocument($owner->client, UploadedFile::fake()->create('bi.pdf', 20, 'application/pdf'), 'bi');
        $attacker = $this->clientUser();

        $this->actingAs($attacker)->post(route('client.applications.store'), $this->applicationData($product, ['document_ids' => [$doc->id]]))->assertRedirect();
        $this->assertSame(0, LoanApplication::firstOrFail()->documents()->count());
    }

    public function test_client_cannot_manipulate_ownership_or_values(): void
    {
        $product = $this->product(['interest_rate' => 5]);
        $client = $this->clientUser();
        $victim = $this->clientUser();

        // Campos não previstos (client_id, simulated_installment, status) são ignorados.
        $this->actingAs($client)->post(route('client.applications.store'), $this->applicationData($product) + [
            'client_id' => $victim->client->id, 'simulated_installment' => '1', 'status' => 'approved', 'interest_rate' => '0',
        ])->assertRedirect();

        $application = LoanApplication::firstOrFail();
        $this->assertSame($client->client->id, $application->client_id);
        $this->assertSame('submitted', $application->status->value);
        $this->assertSame('3672.09', (string) $application->simulated_installment);
    }

    public function test_client_cannot_change_verified_identity(): void
    {
        $client = $this->clientUser(['kyc_status' => 'verified', 'full_name' => 'Nome Original Teste']);
        $this->actingAs($client)->put(route('client.profile.update'), [
            'full_name' => 'Outro Nome Qualquer', 'document_number' => 'XYZ999', 'phone' => '841234567', 'province' => 'Gaza',
        ])->assertSessionHas('success');

        $c = $client->client->fresh();
        $this->assertSame('Nome Original Teste', $c->full_name);
        $this->assertNotSame('XYZ999', $c->document_number);
        $this->assertSame('841234567', $c->phone);
    }

    public function test_audit_logs_are_immutable(): void
    {
        $this->staff('admin');
        $log = AuditLog::firstOrFail();
        $this->expectException(LogicException::class);
        $log->update(['action' => 'adulterado']);
    }

    public function test_security_headers_are_present(): void
    {
        $this->get('/')->assertHeader('X-Frame-Options', 'SAMEORIGIN')->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_contact_form_validates_and_stores(): void
    {
        $this->post(route('public.contact.send'), ['name' => 'Ana', 'email' => 'ana@example.test', 'subject' => 'Info', 'message' => 'Gostaria de informação.', 'consent' => '1'])
            ->assertSessionHas('success');
        $this->assertDatabaseHas('contact_messages', ['email' => 'ana@example.test', 'status' => 'new']);
        $this->post(route('public.contact.send'), ['name' => '', 'email' => 'x'])->assertSessionHasErrors(['name', 'email', 'subject', 'message', 'consent']);
    }
}

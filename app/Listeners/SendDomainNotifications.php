<?php

namespace App\Listeners;

use App\Events\ApplicationInformationProvided;
use App\Events\ApplicationInformationRequested;
use App\Events\DocumentReviewed;
use App\Events\LoanApplicationApproved;
use App\Events\LoanApplicationRejected;
use App\Events\LoanApplicationSubmitted;
use App\Events\LoanDisbursed;
use App\Events\LoanSettled;
use App\Events\PaymentConfirmed;
use App\Events\PaymentRejected;
use App\Events\PaymentReversed;
use App\Events\PaymentSubmitted;
use App\Events\TicketReplied;
use App\Models\Client;
use App\Models\User;
use App\Notifications\ApplicationApprovedNotification;
use App\Notifications\ApplicationInfoProvidedNotification;
use App\Notifications\ApplicationRejectedNotification;
use App\Notifications\ApplicationSubmittedNotification;
use App\Notifications\ContractAvailableNotification;
use App\Notifications\DocumentReviewedNotification;
use App\Notifications\DocumentsRequestedNotification;
use App\Notifications\LoanDisbursedNotification;
use App\Notifications\LoanSettledNotification;
use App\Notifications\NewApplicationNotification;
use App\Notifications\PaymentConfirmedNotification;
use App\Notifications\PaymentProofSubmittedNotification;
use App\Notifications\PaymentRejectedNotification;
use App\Notifications\PaymentReversedNotification;
use App\Notifications\TicketReplyNotification;
use App\Services\NotificationDispatcher;

/**
 * Traduz eventos de domínio em notificações para clientes e funcionários.
 * As chaves de deduplicação impedem notificações repetidas para o mesmo facto.
 */
class SendDomainNotifications
{
    public function __construct(private readonly NotificationDispatcher $notifier) {}

    private function clientUser(?Client $client): ?User
    {
        return $client?->user;
    }

    private function once(string $key, ?User $user, $notification): void
    {
        if ($user) {
            $this->notifier->once($key, $user, $notification);
        }
    }

    public function handleSubmitted(LoanApplicationSubmitted $event): void
    {
        $app = $event->application;
        $this->once("application:{$app->id}:submitted", $this->clientUser($app->client), new ApplicationSubmittedNotification($app));
        $this->notifier->staffWithPermission('applications.review', new NewApplicationNotification($app));
    }

    public function handleInformationRequested(ApplicationInformationRequested $event): void
    {
        $app = $event->application;
        $count = $app->reviews()->where('action', 'info_requested')->count();
        $this->once("application:{$app->id}:info_requested:{$count}", $this->clientUser($app->client), new DocumentsRequestedNotification($app));
    }

    public function handleInformationProvided(ApplicationInformationProvided $event): void
    {
        $app = $event->application;
        if ($app->assignee) {
            $app->assignee->notify(new ApplicationInfoProvidedNotification($app));
        } else {
            $this->notifier->staffWithPermission('applications.review', new ApplicationInfoProvidedNotification($app));
        }
    }

    public function handleApproved(LoanApplicationApproved $event): void
    {
        $user = $this->clientUser($event->application->client);
        $this->once("application:{$event->application->id}:approved", $user, new ApplicationApprovedNotification($event->application, $event->loan));
        $this->once("loan:{$event->loan->id}:contract_available", $user, new ContractAvailableNotification($event->loan));
    }

    public function handleRejected(LoanApplicationRejected $event): void
    {
        $this->once("application:{$event->application->id}:rejected", $this->clientUser($event->application->client), new ApplicationRejectedNotification($event->application));
    }

    public function handleDisbursed(LoanDisbursed $event): void
    {
        $this->once("loan:{$event->loan->id}:disbursed", $this->clientUser($event->loan->client), new LoanDisbursedNotification($event->loan, $event->disbursement));
    }

    public function handlePaymentSubmitted(PaymentSubmitted $event): void
    {
        $this->notifier->staffWithPermission('payments.confirm', new PaymentProofSubmittedNotification($event->payment));
    }

    public function handlePaymentConfirmed(PaymentConfirmed $event): void
    {
        $this->once("payment:{$event->payment->id}:confirmed", $this->clientUser($event->payment->client), new PaymentConfirmedNotification($event->payment));
    }

    public function handlePaymentRejected(PaymentRejected $event): void
    {
        $this->once("payment:{$event->payment->id}:rejected", $this->clientUser($event->payment->client), new PaymentRejectedNotification($event->payment));
    }

    public function handlePaymentReversed(PaymentReversed $event): void
    {
        $this->once("payment:{$event->payment->id}:reversed", $this->clientUser($event->payment->client), new PaymentReversedNotification($event->payment));
    }

    public function handleSettled(LoanSettled $event): void
    {
        $this->once("loan:{$event->loan->id}:settled:".$event->loan->settled_at?->timestamp, $this->clientUser($event->loan->client), new LoanSettledNotification($event->loan));
    }

    public function handleDocumentReviewed(DocumentReviewed $event): void
    {
        $doc = $event->document;
        $this->once("document:{$doc->id}:{$doc->status}", $this->clientUser($doc->client), new DocumentReviewedNotification($doc));
    }

    public function handleTicketReplied(TicketReplied $event): void
    {
        if ($event->reply->is_staff) {
            $this->clientUser($event->ticket->client)?->notify(new TicketReplyNotification($event->ticket));
        } elseif ($event->ticket->assignee) {
            $event->ticket->assignee->notify(new TicketReplyNotification($event->ticket, true));
        } else {
            $this->notifier->staffWithPermission('support.manage', new TicketReplyNotification($event->ticket, true));
        }
    }
}

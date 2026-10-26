<?php

namespace App\Notifications;

use App\Models\ClientDocument;

class DocumentReviewedNotification extends AppNotification
{
    public function __construct(public ClientDocument $document)
    {
        parent::__construct();
    }

    public function title(): string
    {
        return $this->document->status === 'verified' ? 'Documento verificado' : 'Documento rejeitado';
    }

    public function message(): string
    {
        return $this->document->status === 'verified'
            ? 'O documento "'.$this->document->typeLabel().'" foi verificado.'
            : 'O documento "'.$this->document->typeLabel().'" foi rejeitado: '.$this->document->rejection_reason.'. Carregue um novo documento.';
    }

    public function url(object $notifiable): ?string
    {
        return route('client.documents.index');
    }

    public function icon(): string
    {
        return 'file-earmark-check';
    }

    public function level(): string
    {
        return $this->document->status === 'verified' ? 'success' : 'warning';
    }
}

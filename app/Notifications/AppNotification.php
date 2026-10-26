<?php

namespace App\Notifications;

use App\Notifications\Channels\SmsChannel;
use App\Services\SettingsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Base das notificações do sistema: notificação interna (base de dados) + email,
 * e SMS quando activado e configurado um prestador. Enviadas em fila, após commit.
 */
abstract class AppNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->afterCommit();
    }

    abstract public function title(): string;

    abstract public function message(): string;

    public function url(object $notifiable): ?string
    {
        return null;
    }

    public function icon(): string
    {
        return 'bell';
    }

    /** success|info|warning|danger */
    public function level(): string
    {
        return 'info';
    }

    /** Texto curto para SMS; null = não enviar por SMS. */
    public function smsText(): ?string
    {
        return null;
    }

    public function via(object $notifiable): array
    {
        $channels = ['database'];
        if (! empty($notifiable->email)) {
            $channels[] = 'mail';
        }
        if ($this->smsText() && ! empty($notifiable->phone) && app(SettingsService::class)->bool('notifications.sms_enabled')) {
            $channels[] = SmsChannel::class;
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->title().' — '.config('app.name'))
            ->greeting('Olá, '.$notifiable->name.'.')
            ->line($this->message());

        if ($url = $this->url($notifiable)) {
            $mail->action('Ver detalhes', $url);
        }

        return $mail->salutation('Com os melhores cumprimentos, '.config('app.name'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'message' => $this->message(),
            'url' => $this->url($notifiable),
            'icon' => $this->icon(),
            'level' => $this->level(),
        ];
    }

    public function toSms(object $notifiable): string
    {
        return (string) $this->smsText();
    }
}

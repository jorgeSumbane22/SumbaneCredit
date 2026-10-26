<?php

namespace App\Notifications\Channels;

use App\Services\Sms\SmsManager;
use Illuminate\Notifications\Notification;

class SmsChannel
{
    public function __construct(private readonly SmsManager $sms) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toSms') || empty($notifiable->phone)) {
            return;
        }
        $this->sms->driver()->send($notifiable->phone, $notification->toSms($notifiable));
    }
}

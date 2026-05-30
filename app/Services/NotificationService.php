<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\NotificationLog;
use App\Models\User;
use App\Models\ProviderStaff;

class NotificationService
{
    public function send(mixed $recipient, string $title, string $message, string $type): void
    {
        $targetRecipient = 'unknown';

        if ($recipient instanceof User) {
            Notification::create([
                'user_id' => $recipient->id,
                'title' => $title,
                'message' => $message,
                'type' => $type,
                'is_read' => false,
            ]);

            $targetRecipient = $type === 'Email' ? $recipient->email : ($recipient->mobile ?? $recipient->email);
        } elseif ($recipient instanceof ProviderStaff) {
            $targetRecipient = $type === 'Email' ? $recipient->email : ($recipient->mobile ?? $recipient->email);
        } elseif (is_string($recipient)) {
            $targetRecipient = $recipient;
        }

        NotificationLog::create([
            'notification_type' => $type,
            'recipient' => $targetRecipient,
            'message' => sprintf("[%s] %s", $title, $message),
            'status' => 'Sent',
            'sent_at' => now(),
        ]);
    }
}

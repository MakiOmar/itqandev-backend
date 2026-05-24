<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ContactSubmissionReceived extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array{name: string, email: string, subject?: string|null, message: string}  $submission
     */
    public function __construct(
        public array $submission,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $name = $this->submission['name'] ?? 'Someone';
        $subject = $this->submission['subject'] ?? 'Contact form';

        return [
            'title' => 'New contact message',
            'message' => "{$name} sent: {$subject}",
            'type' => 'info',
            'email' => $this->submission['email'] ?? '',
        ];
    }
}

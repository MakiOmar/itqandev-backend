<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class FormSubmissionReceived extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $labeled
     */
    public function __construct(
        public string $formTitle,
        public array $labeled,
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
        $preview = '';
        foreach ($this->labeled as $label => $value) {
            if (is_string($value) && $value !== '') {
                $preview = $value;
                break;
            }
        }

        return [
            'title' => 'New form submission',
            'message' => $this->formTitle.($preview !== '' ? ': '.$preview : ''),
            'type' => 'info',
            'form_title' => $this->formTitle,
        ];
    }
}

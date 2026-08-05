<?php

namespace App\Services\Forms\Actions;

use App\Mail\FormAutoreplyMail;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Services\Forms\FormSubmissionContext;
use Illuminate\Support\Facades\Mail;

final class EmailAutoreplyAction implements FormActionHandler
{
    public function type(): string
    {
        return 'email_autoreply';
    }

    public function handle(Form $form, FormSubmissionContext $context, array $settings, ?FormSubmission $submission): array
    {
        $hint = (string) ($settings['email_field'] ?? '');
        $to = $hint !== ''
            ? $context->valueByFieldNameOrId($hint)
            : $context->findEmail();
        if (! is_string($to) || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return [];
        }

        $subject = (string) ($settings['subject'] ?? 'We received your message');
        $body = (string) ($settings['body'] ?? 'Thank you for contacting us.');

        try {
            Mail::to($to)->queue(new FormAutoreplyMail($form, $subject, $body));
        } catch (\Throwable) {
            // Ignore mail transport failures for autoreply.
        }

        return [];
    }
}

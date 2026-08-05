<?php

namespace App\Services\Forms\Actions;

use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\User;
use App\Mail\FormSubmissionAdminMail;
use App\Notifications\FormSubmissionReceived;
use App\Services\Forms\FormSubmissionContext;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

final class EmailAdminsAction implements FormActionHandler
{
    public function type(): string
    {
        return 'email_admins';
    }

    public function handle(Form $form, FormSubmissionContext $context, array $settings, ?FormSubmission $submission): array
    {
        $subjectTpl = (string) ($settings['subject'] ?? 'New form submission: {{form_title}}');
        $subject = str_replace('{{form_title}}', $form->title, $subjectTpl);
        $recipientsRaw = trim((string) ($settings['recipients'] ?? ''));
        $emails = array_values(array_filter(array_map('trim', $recipientsRaw === '' ? [] : explode(',', $recipientsRaw))));

        if ($emails === []) {
            $admins = User::permission('manage forms')->get();
            if ($admins->isEmpty()) {
                $admins = User::role(['admin', 'super_admin'])->get();
            }
            $emails = $admins->pluck('email')->filter()->unique()->values()->all();
        }

        foreach ($emails as $email) {
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            try {
                Mail::to($email)->queue(new FormSubmissionAdminMail($form, $subject, $context->labeled));
            } catch (\Throwable) {
                // Mail may be unconfigured; fall through to database notification.
            }
        }

        if (! empty($settings['notify_database'])) {
            $admins = User::permission('manage forms')->get();
            if ($admins->isEmpty()) {
                $admins = User::role(['admin', 'super_admin'])->get();
            }
            if ($admins->isNotEmpty()) {
                Notification::send($admins, new FormSubmissionReceived($form->title, $context->labeled));
            }
        }

        return [];
    }
}

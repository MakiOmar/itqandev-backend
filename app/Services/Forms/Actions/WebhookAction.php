<?php

namespace App\Services\Forms\Actions;

use App\Models\Form;
use App\Models\FormSubmission;
use App\Services\Forms\FormSubmissionContext;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class WebhookAction implements FormActionHandler
{
    public function type(): string
    {
        return 'webhook';
    }

    public function handle(Form $form, FormSubmissionContext $context, array $settings, ?FormSubmission $submission): array
    {
        $url = trim((string) ($settings['url'] ?? ''));
        if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return [];
        }

        $body = [
            'form_id' => $form->id,
            'form_slug' => $form->slug,
            'form_title' => $form->title,
            'locale' => $context->locale,
            'values' => $context->values,
            'labeled' => $context->labeled,
            'submission_id' => $submission?->id,
            'submitted_at' => now()->toIso8601String(),
        ];
        $json = json_encode($body, JSON_UNESCAPED_UNICODE);
        $headers = ['Content-Type' => 'application/json', 'Accept' => 'application/json'];
        $secret = trim((string) ($settings['secret'] ?? ''));
        if ($secret !== '' && is_string($json)) {
            $headers['X-Form-Signature'] = hash_hmac('sha256', $json, $secret);
        }

        try {
            Http::withHeaders($headers)->timeout(10)->withBody($json ?: '{}', 'application/json')->post($url);
        } catch (\Throwable $e) {
            Log::warning('Form webhook failed', [
                'form_id' => $form->id,
                'error' => $e->getMessage(),
            ]);
        }

        return [];
    }
}

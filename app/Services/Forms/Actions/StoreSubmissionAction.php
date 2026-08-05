<?php

namespace App\Services\Forms\Actions;

use App\Models\Form;
use App\Models\FormSubmission;
use App\Services\Forms\FormSubmissionContext;

final class StoreSubmissionAction implements FormActionHandler
{
    public function type(): string
    {
        return 'store_submission';
    }

    public function handle(Form $form, FormSubmissionContext $context, array $settings, ?FormSubmission $submission): array
    {
        $storeIp = array_key_exists('store_ip', $settings)
            ? (bool) $settings['store_ip']
            : true;

        $row = FormSubmission::create([
            'form_id' => $form->id,
            'locale' => $context->locale,
            'status' => FormSubmission::STATUS_NEW,
            'payload' => [
                'values' => $context->values,
                'labeled' => $context->labeled,
                // Snapshot field types/labels so admin can render mailto/tel without the live layout.
                'fields' => array_values(array_map(static function (array $field): array {
                    $type = (string) ($field['type'] ?? 'text');
                    $settings = is_array($field['settings'] ?? null) ? $field['settings'] : [];

                    return [
                        'id' => (string) ($field['id'] ?? ''),
                        'type' => $type,
                        'label' => (string) ($settings['label'] ?? $type),
                    ];
                }, $context->fields)),
            ],
            'ip_address' => $storeIp ? $context->ip : null,
            'user_agent' => $storeIp ? $context->userAgent : null,
        ]);

        if ($context->submissionHolder) {
            $context->submissionHolder->submission = $row;
        }

        return ['submission_id' => $row->id];
    }
}

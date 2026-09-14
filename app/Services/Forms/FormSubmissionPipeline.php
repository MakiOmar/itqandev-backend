<?php

namespace App\Services\Forms;

use App\Models\Form;
use App\Models\FormSubmission;
use App\Services\Appearance\AppearanceLocalizedSettings;
use App\Services\Forms\Actions\EmailAdminsAction;
use App\Services\Forms\Actions\EmailAutoreplyAction;
use App\Services\Forms\Actions\FormActionHandler;
use App\Services\Forms\Actions\RedirectAction;
use App\Services\Forms\Actions\StoreSubmissionAction;
use App\Services\Forms\Actions\WebhookAction;
use App\Support\Honeypot;
use App\Support\SiteLanguages;
use App\Support\WesternDigits;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class FormSubmissionPipeline
{
    /**
     * @return array{success: bool, message?: string, redirect_url?: string, submission_id?: int}
     */
    public function submit(Form $form, Request $request): array
    {
        $locale = strtolower(trim((string) $request->header('X-Content-Locale', SiteLanguages::defaultCode())));
        $primary = SiteLanguages::primaryLocaleForContent($form->content_locale);
        $settings = FormLayoutDocument::normalizeSettings($form->settings ?? []);
        $layout = is_array($form->layout) ? $form->layout : [];
        $fields = FormLayoutDocument::flattenFields($layout);

        if (! empty($settings['honeypot'])) {
            Honeypot::rejectIfFilled($request);
        }

        $captcha = (string) ($settings['captcha'] ?? 'none');
        if ($captcha !== 'none') {
            $token = (string) $request->input('captcha_token', $request->input('cf-turnstile-response', ''));
            if (! FormCaptchaVerifier::verify($captcha, $token, $request->ip())) {
                throw ValidationException::withMessages([
                    'captcha_token' => ['Captcha verification failed.'],
                ]);
            }
        }

        $rawAll = $request->all();
        $rules = [];
        $attributes = [];
        $digitNormalizeMerge = [];
        $visibleIds = [];
        foreach ($fields as $field) {
            $id = (string) $field['id'];
            $type = (string) $field['type'];
            if ($type === 'html') {
                continue;
            }
            $resolved = AppearanceLocalizedSettings::resolveForLocale(
                is_array($field['settings'] ?? null) ? $field['settings'] : [],
                $locale,
                $primary,
                FormFieldRegistry::translatableKeys($type)
            );
            $conditions = FormConditionDocument::normalize($resolved['conditions'] ?? null);
            if (! FormConditionDocument::isVisible($conditions, $rawAll)) {
                continue;
            }
            $visibleIds[$id] = true;
            $required = ! empty($resolved['required']);
            $attributes[$id] = (string) ($resolved['label'] ?? $type);
            $rules[$id] = $this->rulesForField($type, $required, $resolved);
            // Email/tel must use Western ASCII digits (convert Eastern/Persian numerals).
            if (($type === 'email' || $type === 'tel') && $request->has($id)) {
                $raw = $request->input($id);
                if (is_string($raw)) {
                    $digitNormalizeMerge[$id] = WesternDigits::normalize($raw);
                }
            }
        }
        if ($digitNormalizeMerge !== []) {
            $request->merge($digitNormalizeMerge);
        }

        $validated = $request->validate($rules, [], $attributes);

        $values = [];
        $labeled = [];
        foreach ($fields as $field) {
            $id = (string) $field['id'];
            $type = (string) $field['type'];
            if ($type === 'html' || ! isset($visibleIds[$id])) {
                continue;
            }
            $resolved = AppearanceLocalizedSettings::resolveForLocale(
                is_array($field['settings'] ?? null) ? $field['settings'] : [],
                $locale,
                $primary,
                FormFieldRegistry::translatableKeys($type)
            );
            $label = (string) ($resolved['label'] ?? $type);
            if ($type === 'file' && $request->hasFile($id)) {
                $uploaded = $request->file($id);
                if (is_array($uploaded)) {
                    $urls = [];
                    foreach ($uploaded as $file) {
                        if ($file) {
                            $path = $file->store('form-uploads/'.$form->id, 'public');
                            $urls[] = Storage::disk('public')->url($path);
                        }
                    }
                    $values[$id] = $urls;
                    $labeled[$label] = implode(', ', $urls);
                    continue;
                }
                $path = $uploaded->store('form-uploads/'.$form->id, 'public');
                $values[$id] = Storage::disk('public')->url($path);
                $labeled[$label] = $values[$id];
                continue;
            }
            if ($type === 'hidden') {
                $values[$id] = (string) ($resolved['value'] ?? '');
                $labeled[$label] = $values[$id];
                continue;
            }
            $raw = $validated[$id] ?? null;
            if ($type === 'checkbox' && is_array($raw)) {
                $values[$id] = array_values($raw);
            } elseif ($type === 'consent') {
                $values[$id] = (bool) $raw;
            } else {
                $values[$id] = $raw;
            }
            $labeled[$label] = is_bool($values[$id])
                ? ($values[$id] ? 'yes' : 'no')
                : $values[$id];
        }

        $holder = new FormSubmissionResultHolder();
        $context = new FormSubmissionContext(
            values: $values,
            labeled: $labeled,
            fields: array_map(static function (array $field) use ($locale, $primary) {
                $type = (string) $field['type'];
                $field['settings'] = AppearanceLocalizedSettings::resolveForLocale(
                    is_array($field['settings'] ?? null) ? $field['settings'] : [],
                    $locale,
                    $primary,
                    FormFieldRegistry::translatableKeys($type)
                );

                return $field;
            }, $fields),
            locale: $locale,
            ip: $request->ip(),
            userAgent: substr((string) $request->userAgent(), 0, 512),
            submissionHolder: $holder,
        );

        $actions = FormLayoutDocument::normalizeActions($form->actions ?? []);
        if (! empty($settings['do_not_store'])) {
            $actions = array_values(array_filter(
                $actions,
                static fn ($a) => is_array($a) && ($a['type'] ?? '') !== 'store_submission'
            ));
        }
        $extras = [];
        $submission = null;
        foreach ($actions as $action) {
            if (empty($action['enabled'])) {
                continue;
            }
            $handler = $this->handlerFor((string) $action['type']);
            if (! $handler) {
                continue;
            }
            $result = $handler->handle(
                $form,
                $context,
                is_array($action['settings'] ?? null) ? $action['settings'] : [],
                $holder->submission
            );
            $extras = array_merge($extras, $result);
            $submission = $holder->submission;
        }

        $resolvedSettings = AppearanceLocalizedSettings::resolveForLocale(
            $settings,
            $locale,
            $primary,
            ['submit_label', 'success_message', 'error_message']
        );

        return array_filter([
            'success' => true,
            'message' => (string) ($resolvedSettings['success_message'] ?? 'Thank you.'),
            'redirect_url' => $extras['redirect_url'] ?? null,
            'submission_id' => $extras['submission_id'] ?? $submission?->id,
        ], static fn ($v) => $v !== null);
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return list<string|\Illuminate\Contracts\Validation\ValidationRule>
     */
    private function rulesForField(string $type, bool $required, array $settings): array
    {
        $base = [$required ? 'required' : 'nullable'];

        return match ($type) {
            'email' => array_merge($base, ['email', 'max:255']),
            'tel' => array_merge($base, ['string', 'max:64']),
            'url' => array_merge($base, ['url', 'max:2048']),
            'number' => array_merge($base, ['numeric']),
            'textarea', 'text', 'hidden' => array_merge($base, ['string', 'max:10000']),
            'date' => array_merge($base, ['date']),
            'time' => array_merge($base, ['date_format:H:i']),
            'select' => ! empty($settings['multiple'])
                ? array_merge($base, ['array'])
                : array_merge($base, ['string', 'max:512']),
            'radio' => array_merge($base, ['string', 'max:512']),
            'checkbox' => array_merge($base, ['array']),
            'consent' => array_merge($required ? ['accepted'] : ['nullable', 'boolean'], []),
            'file' => ! empty($settings['multiple'])
                ? array_merge($base, ['array'])
                : array_merge($base, [
                    'file',
                    'max:'.max(64, min(20480, (int) ($settings['max_kb'] ?? 5120))),
                ]),
            default => array_merge($base, ['string', 'max:2000']),
        };
    }

    private function handlerFor(string $type): ?FormActionHandler
    {
        return match ($type) {
            'store_submission' => new StoreSubmissionAction(),
            'email_admins' => new EmailAdminsAction(),
            'email_autoreply' => new EmailAutoreplyAction(),
            'redirect' => new RedirectAction(),
            'webhook' => new WebhookAction(),
            default => null,
        };
    }
}

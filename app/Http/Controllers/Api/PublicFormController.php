<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Services\Forms\FormLayoutDocument;
use App\Services\Forms\FormSubmissionPipeline;
use App\Support\SiteLanguages;
use App\Support\TranslatableContentPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PublicFormController extends Controller
{
    public function show(Request $request, string $slug): JsonResponse
    {
        $present = TranslatableContentPresenter::requestedPresentationLocale($request)
            ?: SiteLanguages::defaultCode();
        $version = (int) Cache::get('forms:cache_version', 1);
        $cacheKey = "forms:public:{$slug}:{$present}:v{$version}";

        $payload = Cache::remember($cacheKey, 600, function () use ($slug, $present) {
            $form = Form::query()
                ->with('translations')
                ->where('slug', $slug)
                ->where('status', Form::STATUS_PUBLISHED)
                ->first();
            if (! $form) {
                return null;
            }
            TranslatableContentPresenter::applyForm($form, $present);
            if (! TranslatableContentPresenter::hasFormContentForLocale($form, $present)) {
                return null;
            }

            $primary = SiteLanguages::primaryLocaleForContent($form->content_locale);
            $presented = FormLayoutDocument::presentPublic(
                is_array($form->layout) ? $form->layout : [],
                FormLayoutDocument::normalizeSettings($form->settings ?? []),
                $present,
                $primary
            );

            return [
                'id' => $form->id,
                'title' => $form->title,
                'slug' => $form->slug,
                'layout' => ['rows' => $presented['rows']],
                'settings' => $presented['settings'],
                'captcha' => [
                    'provider' => $presented['settings']['captcha'] ?? 'none',
                    'site_key' => $this->publicCaptchaSiteKey((string) ($presented['settings']['captcha'] ?? 'none')),
                ],
            ];
        });

        if ($payload === null) {
            abort(404);
        }

        // Recompute site key outside cache for env freshness on miss path already included
        return response()->json($payload);
    }

    public function submit(Request $request, string $slug, FormSubmissionPipeline $pipeline): JsonResponse
    {
        $form = Form::query()
            ->where('slug', $slug)
            ->where('status', Form::STATUS_PUBLISHED)
            ->firstOrFail();

        $result = $pipeline->submit($form, $request);

        return response()->json($result);
    }

    private function publicCaptchaSiteKey(string $provider): ?string
    {
        return match ($provider) {
            'turnstile' => config('services.turnstile.site_key') ?: null,
            'recaptcha_v2', 'recaptcha_v3' => config('services.recaptcha.site_key') ?: null,
            default => null,
        };
    }
}

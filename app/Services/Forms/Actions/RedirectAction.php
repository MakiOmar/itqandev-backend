<?php

namespace App\Services\Forms\Actions;

use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Page;
use App\Services\Forms\FormSubmissionContext;

final class RedirectAction implements FormActionHandler
{
    public function type(): string
    {
        return 'redirect';
    }

    public function handle(Form $form, FormSubmissionContext $context, array $settings, ?FormSubmission $submission): array
    {
        $url = trim((string) ($settings['url'] ?? ''));
        if ($url !== '' && filter_var($url, FILTER_VALIDATE_URL)) {
            return ['redirect_url' => $url];
        }

        $slug = trim((string) ($settings['page_slug'] ?? ''));
        if ($slug !== '') {
            $page = Page::query()
                ->where('slug', $slug)
                ->where('status', Page::STATUS_PUBLISHED)
                ->first();
            if ($page) {
                return ['redirect_url' => '/pages/'.$page->slug];
            }

            return ['redirect_url' => '/pages/'.$slug];
        }

        return [];
    }
}

<?php

namespace App\Services\Forms\Actions;

use App\Models\Form;
use App\Models\FormSubmission;
use App\Services\Forms\FormSubmissionContext;

interface FormActionHandler
{
    public function type(): string;

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>  Merged into response/context (e.g. redirect_url)
     */
    public function handle(Form $form, FormSubmissionContext $context, array $settings, ?FormSubmission $submission): array;
}

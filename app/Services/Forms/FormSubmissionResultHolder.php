<?php

namespace App\Services\Forms;

use App\Models\FormSubmission;

/** Holds the submission row created mid-pipeline for subsequent actions. */
final class FormSubmissionResultHolder
{
    public function __construct(public ?FormSubmission $submission = null) {}
}

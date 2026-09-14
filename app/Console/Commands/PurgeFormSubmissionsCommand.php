<?php

namespace App\Console\Commands;

use App\Models\Form;
use App\Models\FormSubmission;
use Illuminate\Console\Command;

class PurgeFormSubmissionsCommand extends Command
{
    protected $signature = 'forms:purge-submissions {--dry-run : Count without deleting}';

    protected $description = 'Delete form submissions older than each form retention_days setting.';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $deleted = 0;

        Form::query()->each(function (Form $form) use ($dry, &$deleted) {
            $settings = is_array($form->settings) ? $form->settings : [];
            $days = (int) ($settings['retention_days'] ?? 0);
            if ($days <= 0) {
                return;
            }
            $cutoff = now()->subDays($days);
            $query = FormSubmission::query()
                ->where('form_id', $form->id)
                ->where('created_at', '<', $cutoff);
            if ($dry) {
                $deleted += $query->count();

                return;
            }
            $deleted += $query->delete();
        });

        $this->info(($dry ? 'Would delete ' : 'Deleted ').$deleted.' submission(s).');

        return self::SUCCESS;
    }
}

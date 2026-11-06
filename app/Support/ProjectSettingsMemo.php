<?php

namespace App\Support;

/**
 * Per-request copy of the project settings payload. Bound as a scoped singleton so queue
 * workers (which forget scoped instances between jobs) never keep a stale copy.
 */
final class ProjectSettingsMemo
{
    /** @var array<string, mixed>|null */
    public ?array $payload = null;
}

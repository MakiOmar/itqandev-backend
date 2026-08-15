<?php

namespace App\Services\Appearance;

use App\Models\ChromeLayout;
use App\Models\ThemeTemplate;
use App\Services\PublicMarketingShellService;
use App\Support\MarketingSettingsCache;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class ThemeTemplateService
{
    public function __construct(
        private readonly ChromeLayoutService $layouts,
    ) {}

    public function list(int $perPage = 50): LengthAwarePaginator
    {
        return ThemeTemplate::query()
            ->orderByDesc('updated_at')
            ->orderBy('name')
            ->paginate(max(1, min($perPage, 100)));
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function create(array $input): ThemeTemplate
    {
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'Name is required.']);
        }

        $status = $this->normalizeStatus($input['status'] ?? ThemeTemplate::STATUS_DRAFT);
        $conditions = ThemeTemplateConditions::normalize($input['conditions'] ?? null);

        $headerId = $this->layouts->assertAssignableId(
            $input['header_layout_id'] ?? null,
            ChromeLayout::KIND_HEADER,
            'header_layout_id'
        );
        $footerId = $this->layouts->assertAssignableId(
            $input['footer_layout_id'] ?? null,
            ChromeLayout::KIND_FOOTER,
            'footer_layout_id'
        );
        $bodyId = $this->layouts->assertAssignableId(
            $input['body_layout_id'] ?? null,
            ChromeLayout::KIND_BODY,
            'body_layout_id'
        );

        if ($status === ThemeTemplate::STATUS_PUBLISHED) {
            $this->assertPublishedSlotsUsable($headerId, $footerId, $bodyId);
        }

        $template = ThemeTemplate::query()->create([
            'name' => $name,
            'status' => $status,
            'conditions' => $conditions,
            'header_layout_id' => $headerId,
            'footer_layout_id' => $footerId,
            'body_layout_id' => $bodyId,
        ]);

        $this->bustCaches();

        return $template;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function update(ThemeTemplate $template, array $input): ThemeTemplate
    {
        if (array_key_exists('name', $input)) {
            $name = trim((string) $input['name']);
            if ($name === '') {
                throw ValidationException::withMessages(['name' => 'Name is required.']);
            }
            $template->name = $name;
        }

        if (array_key_exists('status', $input)) {
            $template->status = $this->normalizeStatus($input['status']);
        }

        if (array_key_exists('conditions', $input)) {
            $template->conditions = ThemeTemplateConditions::normalize($input['conditions']);
        }

        if (array_key_exists('header_layout_id', $input)) {
            $template->header_layout_id = $this->layouts->assertAssignableId(
                $input['header_layout_id'],
                ChromeLayout::KIND_HEADER,
                'header_layout_id'
            );
        }
        if (array_key_exists('footer_layout_id', $input)) {
            $template->footer_layout_id = $this->layouts->assertAssignableId(
                $input['footer_layout_id'],
                ChromeLayout::KIND_FOOTER,
                'footer_layout_id'
            );
        }
        if (array_key_exists('body_layout_id', $input)) {
            $template->body_layout_id = $this->layouts->assertAssignableId(
                $input['body_layout_id'],
                ChromeLayout::KIND_BODY,
                'body_layout_id'
            );
        }

        if ($template->status === ThemeTemplate::STATUS_PUBLISHED) {
            $this->assertPublishedSlotsUsable(
                $template->header_layout_id,
                $template->footer_layout_id,
                $template->body_layout_id
            );
        }

        $template->save();
        $this->bustCaches();

        return $template->fresh();
    }

    public function delete(ThemeTemplate $template): void
    {
        $template->delete();
        $this->bustCaches();
    }

    /**
     * Pick the best matching published theme template for a matcher context.
     *
     * @param  array{
     *   context: string,
     *   content_type: string,
     *   record_id: int|null,
     *   query: array<string, string>,
     *   device: string|null,
     *   role: string|null,
     *   authenticated: bool
     * }  $ctx
     */
    public function findBestMatch(array $ctx): ?ThemeTemplate
    {
        if (! Schema::hasTable('theme_templates')) {
            return null;
        }

        $best = null;
        $bestScore = -1;

        $templates = ThemeTemplate::query()->published()->orderBy('id')->get();
        foreach ($templates as $template) {
            if (! ThemeTemplateConditions::matches($template, $ctx)) {
                continue;
            }
            $score = ThemeTemplateConditions::specificity($template, $ctx);
            if ($score > $bestScore || ($score === $bestScore && $best !== null && (int) $template->id > (int) $best->id)) {
                $best = $template;
                $bestScore = $score;
            } elseif ($best === null) {
                $best = $template;
                $bestScore = $score;
            }
        }

        return $best;
    }

    /**
     * Whether body slot applies for this route context (homepage / 404 only in v1).
     */
    public static function bodyAppliesForContext(string $context): bool
    {
        return in_array($context, ['homepage', 'not_found'], true);
    }

    private function normalizeStatus(mixed $status): string
    {
        $status = strtolower(trim((string) $status));
        if (! in_array($status, [ThemeTemplate::STATUS_DRAFT, ThemeTemplate::STATUS_PUBLISHED], true)) {
            throw ValidationException::withMessages([
                'status' => 'Status must be draft or published.',
            ]);
        }

        return $status;
    }

    private function assertPublishedSlotsUsable(?int $headerId, ?int $footerId, ?int $bodyId): void
    {
        // Empty slots inherit; assigned slots must already be published (assertAssignableId).
        // Publishing a template with no slots at all is allowed (conditions-only shell).
        unset($headerId, $footerId, $bodyId);
    }

    private function bustCaches(): void
    {
        MarketingSettingsCache::forgetAll();
        PublicMarketingShellService::forgetShellCaches();
        $this->layouts->forgetAllLayoutCaches();
    }
}

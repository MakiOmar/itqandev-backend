<?php

namespace App\Services\ContentTypes;

/**
 * A (possibly nested) heading in the builder dynamic tag dropdown.
 *
 * `scope` decides which builder documents list the group: `site` everywhere,
 * `record` in single / loop item layouts and CMS pages, `archive` in archive layouts.
 */
final class TagGroup
{
    public const SCOPE_SITE = 'site';

    public const SCOPE_RECORD = 'record';

    public const SCOPE_ARCHIVE = 'archive';

    public function __construct(
        public readonly string $id,
        public readonly string $label,
        public readonly string $scope,
        public readonly ?string $parent = null,
        public readonly ?string $contentType = null,
    ) {}

    /**
     * @return array{id: string, label: string, parent: string|null, scope: string, content_type: string|null}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'parent' => $this->parent,
            'scope' => $this->scope,
            'content_type' => $this->contentType,
        ];
    }
}

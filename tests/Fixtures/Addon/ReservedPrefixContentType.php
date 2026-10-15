<?php

namespace Tests\Fixtures\Addon;

/** Invalid on purpose: claims the built-in `blog` URL prefix. */
class ReservedPrefixContentType extends SampleItemContentType
{
    public function key(): string
    {
        return 'sample_reserved';
    }

    public function module(): ?string
    {
        return 'sample_bad';
    }

    public function publicPathPrefixes(): array
    {
        return ['blog'];
    }
}

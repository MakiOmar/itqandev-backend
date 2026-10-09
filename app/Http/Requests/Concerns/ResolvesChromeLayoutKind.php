<?php

namespace App\Http\Requests\Concerns;

use App\Models\ChromeLayout;

/**
 * Resolves the Theme Builder layout kind from the `{kind}` route segment
 * (`headers`, `loop-items`, …) for chrome layout bulk/transfer requests.
 */
trait ResolvesChromeLayoutKind
{
    public function layoutKind(): string
    {
        $kind = ChromeLayout::kindFromRouteSegment((string) $this->route('kind'));
        if ($kind === null) {
            abort(404, 'Unknown layout type.');
        }

        return $kind;
    }

    public function authorize(): bool
    {
        return $this->user()?->can('manageSettings') ?? false;
    }
}

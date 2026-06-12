<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Model;

trait AuthorizesResolvedModel
{
    protected function authorizeParentUpdate(Model $parent): void
    {
        $this->authorize('update', $parent);
    }
}

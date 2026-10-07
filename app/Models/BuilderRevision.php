<?php

namespace App\Models;

use App\Casts\JsonWithAppMediaUrls;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class BuilderRevision extends Model
{
    protected $fillable = [
        'revisable_type',
        'revisable_id',
        'document',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'document' => JsonWithAppMediaUrls::class,
            'created_by' => 'integer',
        ];
    }

    public function revisable(): MorphTo
    {
        return $this->morphTo();
    }
}

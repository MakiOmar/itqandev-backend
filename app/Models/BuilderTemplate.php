<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A saved builder node (band, row, column or block) that is copied into a layout on insert,
 * unlike {@see BuilderGlobal}, whose placements stay linked.
 */
class BuilderTemplate extends Model
{
    use SoftDeletes;

    public const KIND_BAND = 'band';

    public const KIND_ROW = 'row';

    public const KIND_COLUMN = 'column';

    public const KIND_BLOCK = 'block';

    /** @var list<string> */
    public const KINDS = [self::KIND_BAND, self::KIND_ROW, self::KIND_COLUMN, self::KIND_BLOCK];

    protected $fillable = [
        'name',
        'kind',
        'document',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'document' => 'array',
        ];
    }
}

<?php

namespace Tests\Fixtures\Addon;

use Illuminate\Database\Eloquent\Model;

/** Test-only record for the addon host contract; its table is created by the test. */
class SampleItem extends Model
{
    protected $table = 'sample_addon_items';

    protected $guarded = [];
}

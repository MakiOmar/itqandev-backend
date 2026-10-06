<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PageTranslation extends Model
{
    protected $fillable = [
        'page_id',
        'locale',
        'title',
        'subtitle',
        'excerpt',
    ];

    public function page()
    {
        return $this->belongsTo(Page::class);
    }
}

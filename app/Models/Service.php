<?php

namespace App\Models;

use App\Concerns\InvalidatesCache;
use App\Concerns\RefreshesCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    use HasFactory, InvalidatesCache, RefreshesCache;

    protected $fillable = [
        'slug',
        'content_locale',
        'icon',
        'sort_order',
        'is_published',
        'name',
        'short_description',
        'description',
        'process',
        'deliverables',
        'header_layout_id',
        'footer_layout_id',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'sort_order' => 'integer',
            'process' => 'array',
            'deliverables' => 'array',
        ];
    }

    public function translations()
    {
        return $this->hasMany(ServiceTranslation::class);
    }

    public function seoMetas()
    {
        return $this->morphMany(SeoMeta::class, 'seoable');
    }

    public function headerLayout()
    {
        return $this->belongsTo(ChromeLayout::class, 'header_layout_id');
    }

    public function footerLayout()
    {
        return $this->belongsTo(ChromeLayout::class, 'footer_layout_id');
    }
}

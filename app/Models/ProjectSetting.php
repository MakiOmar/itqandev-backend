<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Singleton row for operator project settings (branding, locales, homepage builder).
 */
class ProjectSetting extends Model
{
    public const SINGLETON_ID = 1;

    protected $fillable = [
        'payload',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }
}

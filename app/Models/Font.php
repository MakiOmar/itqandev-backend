<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Font extends Model
{
    protected $fillable = [
        'name',
        'css_family',
        'file_woff2',
        'file_woff',
        'file_ttf',
        'file_eot',
        'file_svg',
    ];

    /**
     * @return array<string, string|null>
     */
    public function sourceMap(): array
    {
        return [
            'woff2' => $this->file_woff2,
            'woff' => $this->file_woff,
            'ttf' => $this->file_ttf,
            'eot' => $this->file_eot,
            'svg' => $this->file_svg,
        ];
    }

    /**
     * @return list<string>
     */
    public function presentFormats(): array
    {
        $formats = [];
        foreach ($this->sourceMap() as $format => $url) {
            if (is_string($url) && trim($url) !== '') {
                $formats[] = $format;
            }
        }

        return $formats;
    }

    public function hasAnySourceFile(): bool
    {
        return $this->presentFormats() !== [];
    }
}

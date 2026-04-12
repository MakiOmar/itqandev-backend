<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceTranslation extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_id',
        'locale',
        'name',
        'short_description',
        'description',
        'process',
        'deliverables',
    ];

    protected function casts(): array
    {
        return [
            'process' => 'array',
            'deliverables' => 'array',
        ];
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }
}

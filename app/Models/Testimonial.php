<?php

namespace App\Models;

use App\Concerns\RefreshesCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Testimonial extends Model
{
    use HasFactory, RefreshesCache;

    protected $fillable = [
        'project_id',
        'client_name',
        'client_role',
        'company',
        'rating',
        'content',
        'video_url',
        'approved',
    ];

    protected $casts = [
        'rating' => 'integer',
        'approved' => 'boolean',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}

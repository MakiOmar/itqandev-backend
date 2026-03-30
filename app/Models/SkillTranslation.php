<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SkillTranslation extends Model
{
    use HasFactory;

    protected $fillable = [
        'skill_id',
        'locale',
        'name',
        'description',
    ];

    public function skill()
    {
        return $this->belongsTo(Skill::class);
    }
}


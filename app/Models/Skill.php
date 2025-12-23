<?php

namespace App\Models;

use App\Concerns\RefreshesCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Skill extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, RefreshesCache;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon_hint',
    ];

    public function projects()
    {
        return $this->belongsToMany(Project::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('icon')->singleFile();
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Skill extends Model
{
    //
}

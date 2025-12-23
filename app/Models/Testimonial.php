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
    ];

    protected $casts = [
        'rating' => 'integer',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Testimonial extends Model
{
    //
}

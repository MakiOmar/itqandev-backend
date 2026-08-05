<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FormSubmission extends Model
{
    use SoftDeletes;

    public const STATUS_NEW = 'new';

    public const STATUS_READ = 'read';

    public const STATUS_SPAM = 'spam';

    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'form_id',
        'locale',
        'status',
        'payload',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    public function form()
    {
        return $this->belongsTo(Form::class);
    }
}

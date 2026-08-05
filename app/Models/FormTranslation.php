<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormTranslation extends Model
{
    protected $fillable = [
        'form_id',
        'locale',
        'title',
    ];

    public function form()
    {
        return $this->belongsTo(Form::class);
    }
}

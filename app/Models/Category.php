<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    //

    protected $fillable = ['name', 'slug', 'parent_id', 'description', 'is_active'];




    public function Parent()
    {
        return $this->belongsTo(\App\Models\Parent::class);
    }


}
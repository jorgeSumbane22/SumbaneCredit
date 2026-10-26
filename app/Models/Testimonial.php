<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Testimonial extends Model
{
    protected $fillable = ['name', 'role', 'content', 'is_demo', 'is_active'];

    protected function casts(): array
    {
        return ['is_demo' => 'boolean', 'is_active' => 'boolean'];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Designation extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'short_name', 'is_active'];

    public function users()
    {
        return $this->hasMany(User::class);
    }
}

<?php
// app/Models/NoticeTemplate.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
// use Illuminate\Database\Eloquent\SoftDeletes;

class NoticeTemplate extends Model
{
    // use SoftDeletes;

    protected $fillable = [
        'memorial_no','date','subject','body','signature_body','user_id','is_active','status'
    ];

    protected $casts = [
        'date' => 'date',
        'is_active' => 'boolean',
    ];

    public function user()      { return $this->belongsTo(User::class); }
    public function distributions() { return $this->hasMany(NoticeTemplateDistribution::class); }
    public function regards()   { return $this->hasMany(NoticeTemplateRegard::class); }
}
 
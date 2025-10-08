<?php

// app/Models/NoticeTemplateDistribution.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NoticeTemplateDistribution extends Model
{
    protected $fillable = ['name','designation','notice_template_id','is_active','status'];
    protected $casts = ['is_active' => 'boolean'];

    public function noticeTemplate() { return $this->belongsTo(NoticeTemplate::class); }
}

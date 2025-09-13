<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @OA\Schema(
 *     schema="Notice",
 *     type="object",
 *     title="Notice",
 *     required={"id", "title", "description"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="title", type="string", example="Office Closed on Friday"),
 *     @OA\Property(property="description", type="string", example="The office will remain closed this Friday due to maintenance work."),
 *     @OA\Property(property="priority_level", type="string", example="high"),
 *     @OA\Property(property="status", type="string", example="published"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2024-07-29T12:00:00Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2024-07-29T12:00:00Z")
 * )
 */
class Notice extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'created_by',
        'modified_by',
        'status',         // new
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function modifier()
    {
        return $this->belongsTo(User::class, 'modified_by');
    }

    public function attachments()
    {
        return $this->hasMany(Attachment::class);
    }

    public function propagations()
    {
        return $this->hasMany(NoticePropagation::class);
    }
}

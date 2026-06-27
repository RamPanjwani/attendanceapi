<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class balance extends Model
{
    use HasFactory;

    protected $table = 'balance';

    protected $fillable = [
        'user_id',
        'sick_leaves',
        'casual_leaves',
        'planned_leaves',
    ];

    /**
     * Relationship: Balance belongs to a User.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
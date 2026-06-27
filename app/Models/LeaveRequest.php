<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveRequest extends Model
{
    use HasFactory;
    protected $table = 'leave_request';

    protected $fillable = [
        'user_id',
        'leave_dates',
        'sent_to_ids',
        'leave_type',
        'reason',
        'status',
        'days'
        
    ];

    protected $casts = [
        'leave_dates' => 'array',
        'sent_to_ids' => 'array',
    ];

    /**
     * Relationship: LeaveRequest belongs to a User.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Accessor for leave type (optional)
     */
    public function getLeaveTypeAttribute($value)
    {
        return ucfirst($value);
    }
}

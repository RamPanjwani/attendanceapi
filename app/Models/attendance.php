<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class attendance extends Model
{
    use HasFactory;

    protected $table = 'attendance';
    public $timestamps = false;
    protected $fillable = [
        'user_id',
        'date',
        'status',
        'check_in',
        'check_out',
        'lunchtime_start',
        'lunchtime',
    ];
    // In your Attendance model
    protected $casts = [
        'lunchtime' => 'float', // or 'integer' if appropriate
    ];
    /**
     * Get the user that owns the attendance record.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
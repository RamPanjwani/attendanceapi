<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Attendance;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    /**
     * Mark attendance (check-in and check-out for any date)
     */
    public function markAttendance(Request $request)
    {
        $request->validate([
            'date'      => 'required|date',
            'status'    => 'required|in:present,absent,late,half-day',
            'check_in'  => 'nullable|date_format:H:i:s',
            'check_out' => 'nullable|date_format:H:i:s|after_or_equal:check_in',
        ]);

        $user = Auth::user();
        $date = Carbon::parse($request->date)->toDateString();
        $oneWeekAgo = now()->subWeek()->startOfDay();

        // Check if the date is within the allowed range
        if (Carbon::parse($date)->lt($oneWeekAgo)) {
            throw ValidationException::withMessages([
                'date' => 'Attendance cannot be marked for dates more than one week in the past.'
            ]);
        }

        // Check if attendance already exists for the given date
        $attendance = Attendance::where('user_id', $user->id)->where('date', $date)->first();

        if ($attendance) {
            // If attendance is fully marked (check_out exists), prevent further updates
            if ($attendance->check_out) {
                return response()->json(['message' => 'Attendance for this day is already finalized'], 403);
            }

            // Update check_out if provided
            if ($request->check_out) {
                $attendance->update([
                    'check_out' => $request->check_out
                ]);
                return response()->json(['message' => 'Check-out updated successfully', 'attendance' => $attendance], 200);
            }
            return response()->json(['message' => 'Attendance already marked, provide check_out to end day'], 400);
        } else {
            // Create new attendance record
            $attendance = Attendance::create([
                'user_id'   => $user->id,
                'date'      => $date,
                'status'    => $request->status,
                'check_in'  => $request->check_in ?? now()->toTimeString(),
                'check_out' => $request->check_out
            ]);
            return response()->json(['message' => 'Attendance marked successfully', 'attendance' => $attendance], 201);
        }
    }

    /**
     * Check today's attendance status and auto-mark absent if past 12 PM (commented out)
     */
    public function checkTodayStatus()
    {
        $today = now()->toDateString();
        $userId = auth()->id();
        $now = Carbon::now();
        $noon = Carbon::today()->setTime(12, 0, 0);

        $todayAttendance = Attendance::where('user_id', $userId)->where('date', $today)->first();

        // Uncomment this block if you want auto-absent functionality
        // if (!$todayAttendance && $now->greaterThan($noon)) {
        //     $todayAttendance = Attendance::create([
        //         'user_id'   => $userId,
        //         'date'      => $today,
        //         'status'    => 'absent',
        //         'check_in'  => null,
        //         'check_out' => null
        //     ]);
        //     return response()->json([
        //         'date' => $todayAttendance->date,
        //         'status' => $todayAttendance->status,
        //         'check_in' => $todayAttendance->check_in,
        //         'check_out' => $todayAttendance->check_out,
        //         'message' => 'Automatically marked absent after 12:00 PM'
        //     ]);
        // }

        if ($todayAttendance) {
            return response()->json([
                'date' => $todayAttendance->date,
                'status' => $todayAttendance->status,
                'check_in' => $todayAttendance->check_in,
                'check_out' => $todayAttendance->check_out,
                'lunchtime' => $todayAttendance->lunchtime,
                'lunchtime_start' => $todayAttendance->lunchtime_start
            ]);
        }

        return response()->json(['message' => 'No attendance record for today']);
    }

    public function getAttendances()
    {
        $attendances = Attendance::where('user_id', auth()->id())
                        ->whereYear('date', now()->year)
                        ->get();

        return response()->json($attendances);
    }

    public function getAllEmployeeStatuses()
    {
        $today = now()->toDateString();

        $attendances = Attendance::whereDate('date', $today)
            ->with('user:id,name')
            ->get();

        $formattedData = $attendances->map(function ($attendance) {
            return [
                'user_id' => $attendance->user->id,
                'name' => $attendance->user->name,
                'status' => $attendance->status,
                'check_in' => $attendance->check_in,
                'check_out' => $attendance->check_out,
            ];
        });

        return response()->json([
            'message' => 'All employees\' attendance statuses retrieved successfully',
            'data' => $formattedData
        ]);
    }

    public function getUserAttendancesById($userId)
    {
        $user = User::find($userId);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $attendances = Attendance::where('user_id', $userId)
            ->whereYear('date', now()->year)
            ->get();

        return response()->json([
            'message' => 'Attendance records retrieved successfully',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
            ],
            'attendances' => $attendances
        ]);
    }

    public function toggleLunchtime(Request $request)
    {
        $attendance = Attendance::where('user_id', auth()->id())
            ->whereDate('date', today())
            ->first();

        if (!$attendance) {
            return response()->json(['message' => 'Attendance record not found'], 404);
        }

        if (is_null($attendance->lunchtime_start)) {
            $attendance->lunchtime_start = now()->toTimeString(); // Changed to toTimeString() for consistency
        } else {
            $startTime = Carbon::parse($attendance->lunchtime_start);
            $elapsedMinutes = $startTime->diffInMinutes(now());
            $currentLunchtime = is_numeric($attendance->lunchtime) ? floatval($attendance->lunchtime) : 0;
            $attendance->lunchtime = $currentLunchtime + $elapsedMinutes;
            $attendance->lunchtime_start = null;
        }

        $attendance->save();

        return response()->json([
            'message' => 'Lunchtime updated successfully!',
            'lunchtime' => $attendance->lunchtime,
            'lunchtime_start' => $attendance->lunchtime_start
        ]);
    }
}

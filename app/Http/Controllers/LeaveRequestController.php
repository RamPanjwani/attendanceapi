<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LeaveRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;
use App\Models\balance;
use App\Models\attendance;
use Illuminate\Support\Facades\Log; // Add this for logging
class LeaveRequestController extends Controller
{
    /**
     * Send a new leave request.
     */
    public function sendLeaveRequest(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'leave_dates'   => 'required|array|min:1',
            'leave_dates.*' => 'date',
            'sent_to_ids'   => 'required|array|min:1',
            'sent_to_ids.*' => 'exists:users,id',
            'leave_type'    => 'required|in:planned,sick,casual',
            'reason'        => 'nullable|string|max:500',
        ]);

        // Check if the user has already requested leave for any of the selected dates
        $existingLeaves = LeaveRequest::where('user_id', $user->id)
            ->whereJsonContains('leave_dates', $request->leave_dates)
            ->exists();

        if ($existingLeaves) {
            return response()->json([
                'message' => 'You have already requested leave for one or more selected dates.'
            ], 400);
        }

        // Calculate the number of leave days
        $days = count($request->leave_dates);

        // Create the leave request
        $leaveRequest = LeaveRequest::create([
            'user_id'     => $user->id,
            'leave_dates' => $request->leave_dates, // Store as array
            'sent_to_ids' => $request->sent_to_ids, // Store as array
            'leave_type'  => $request->leave_type,
            'reason'      => $request->reason,
            'status'      => 'pending',
            'days'        => $days, // Store calculated leave days
        ]);

        return response()->json([
            'message' => 'Leave request sent successfully!',
            'leave_request' => $leaveRequest
        ], 201);
    }


    /**
     * Get leave requests created by the authenticated user.
     */
    public function getLeaveRequests()
    {
        $userId = Auth::id();
        $leaveRequests = LeaveRequest::where('user_id', $userId)->get();

        return response()->json($leaveRequests);
    }

    /**
     * Get leave requests sent to the authenticated user.
     */
    public function getLeaveRequestsForUser(Request $request)
    {
        $userId = auth()->id();
        $leaveRequests = LeaveRequest::whereJsonContains('sent_to_ids', $userId)->get();

        return response()->json($leaveRequests);
    }

    /**
     * Get a specific leave request by ID if the user is the creator or a recipient.
     */
    public function getLeaveRequestById($id)
    {
        $user = Auth::user();
        $leaveRequest = LeaveRequest::where('id', $id)
            ->where(function (Builder $query) use ($user) {
                $query->where('user_id', $user->id) // User is the creator
                      ->orWhereJsonContains('sent_to_ids', $user->id); // User is a recipient
            })
            ->first();

        if (!$leaveRequest) {
            return response()->json(['message' => 'Leave request not found or unauthorized'], 404);
        }

        return response()->json($leaveRequest);
    }
 /**
 * Update the status of a leave request if the user is the creator or a recipient.
 */
    public function updateLeaveRequestStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:pending,approved,rejected,cancelled',
        ]);

        $user = Auth::user();
        $leaveRequest = LeaveRequest::where('id', $id)
            ->where(function (Builder $query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhereJsonContains('sent_to_ids', $user->id);
            })
            ->first();

        if (!$leaveRequest) {
            return response()->json(['message' => 'Leave request not found or unauthorized'], 404);
        }

        if ($leaveRequest->user_id === $user->id && in_array($request->status, ['approved', 'rejected'])) {
            return response()->json(['message' => 'You cannot approve or reject your own leave request'], 403);
        }

        if (in_array($leaveRequest->status, ['approved', 'rejected']) &&
            in_array($request->status, ['pending', $leaveRequest->status])) {
            return response()->json(['message' => 'Cannot change status back to pending or to the same status'], 403);
        }

        if ($leaveRequest->status === 'cancelled') {
            return response()->json(['message' => 'Leave request is cancelled and cannot be updated'], 403);
        }

        // Restore leave balance if approved request is rejected or cancelled
        if ($leaveRequest->status === 'approved' && in_array($request->status, ['rejected', 'cancelled'])) {
            $balance = Balance::where('user_id', $leaveRequest->user_id)->first();
            
            if ($balance) {
                $days = (int)$leaveRequest->days;
                $column = strtolower($leaveRequest->leave_type) . '_leaves';
                $beforeValue = (int)($balance->$column ?? 0);
                
                $balance->$column = $beforeValue + $days;
                
                try {
                    $balance->save();
                } catch (\Exception $e) {
                    // Handle exception silently or add minimal error handling if needed
                }
            }
        }

        // Approve leave request and handle leave balance
        if ($leaveRequest->status === 'pending' && $request->status === 'approved') {
            try {
                foreach ($leaveRequest->leave_dates as $date) {
                    Attendance::updateOrCreate(
                        [
                            'user_id' => $leaveRequest->user_id,
                            'date' => $date,
                        ],
                        ['status' => 'leave']
                    );
                }
            } catch (\Exception $e) {
                // Handle exception silently or add minimal error handling if needed
            }

            $balance = Balance::where('user_id', $leaveRequest->user_id)->first();
            
            if ($balance) {
                $days = (int)$leaveRequest->days;
                $column = strtolower($leaveRequest->leave_type) . '_leaves';
                $beforeValue = (int)($balance->$column ?? 0);

                $balance->$column = $beforeValue - $days;
                
                try {
                    $balance->save();
                    $balance->refresh();
                    
                    $actualValue = (int)($balance->$column ?? 0);
                    $expectedValue = $beforeValue - $days;
                    
                    if ($actualValue !== $expectedValue) {
                        \DB::update(
                            "UPDATE balance SET {$column} = ? WHERE user_id = ?",
                            [$expectedValue, $leaveRequest->user_id]
                        );
                    }
                } catch (\Exception $e) {
                    // Handle exception silently or add minimal error handling if needed
                }
            }
        }

        // Update the leave request status
        try {
            $leaveRequest->status = $request->status;
            $leaveRequest->save();
        } catch (\Exception $e) {
            // Handle exception silently or add minimal error handling if needed
        }

        return response()->json(['message' => 'Leave request status updated successfully']);
    }

    /**
     * Get details of users with approved leave requests for a specific month and year (MySQL).
     */
/**
 * Get details of users with approved leave requests for a specific month and year (MySQL).
 */
public function getApprovedLeavesByMonth(Request $request)
{
    Log::debug('Starting getApprovedLeavesByMonth', [
        'request_params' => $request->all(),
    ]);

    try {
        $request->validate([
            'month' => 'required|integer|between:1,12',
            'year' => 'required|integer|min:2000|max:9999',
        ]);
        Log::debug('Input validation passed', [
            'month' => $request->month,
            'year' => $request->year,
        ]);
    } catch (\Exception $e) {
        Log::error('Input validation failed', [
            'error' => $e->getMessage(),
        ]);
        return response()->json(['error' => 'Validation failed'], 422);
    }

    $month = (int)$request->month; // Use integer directly for MONTH comparison
    $year = (int)$request->year;

    Log::debug('Formatted month and year', [
        'month' => $month,
        'year' => $year,
    ]);

    try {
        $leaveRequests = LeaveRequest::where('status', 'approved')
        ->whereRaw("JSON_SEARCH(leave_dates, 'one', ?)", ["2025-03-%"])
        ->with(['user' => function ($query) {
            $query->select('id', 'name', 'email');
        }])
        ->get();    

        Log::debug('Leave requests retrieved from JSON_TABLE', [
            'count' => $leaveRequests->count(),
            'requests' => $leaveRequests->toArray(),
        ]);
    } catch (\Exception $e) {
        Log::error('JSON_TABLE query failed', [
            'error' => $e->getMessage(),
            'sql' => "SELECT * FROM leave_request WHERE status = 'approved' AND EXISTS (SELECT 1 FROM JSON_TABLE(leave_dates, '$[*]' COLUMNS (leave_date VARCHAR(10) PATH '$')) AS dates WHERE MONTH(STR_TO_DATE(dates.leave_date, '%Y-%m-%d')) = {$month} AND YEAR(STR_TO_DATE(dates.leave_date, '%Y-%m-%d')) = {$year})",
        ]);

        // Fallback to PHP filtering
        $leaveRequests = LeaveRequest::where('status', 'approved')->get();
        $leaveRequests = $leaveRequests->filter(function ($leaveRequest) use ($month, $year) {
            foreach ($leaveRequest->leave_dates as $date) {
                $dateMonth = (int)date('m', strtotime($date));
                $dateYear = (int)date('Y', strtotime($date));
                if ($dateMonth === $month && $dateYear === $year) {
                    Log::debug('PHP filter match', [
                        'leave_request_id' => $leaveRequest->id,
                        'date' => $date,
                        'month' => $dateMonth,
                        'year' => $dateYear,
                    ]);
                    return true;
                }
            }
            return false;
        })->load(['user' => function ($query) {
            $query->select('id', 'name', 'email');
        }]);

        Log::debug('Leave requests after PHP fallback', [
            'count' => $leaveRequests->count(),
            'requests' => $leaveRequests->toArray(),
        ]);
    }

    if ($leaveRequests->isEmpty()) {
        Log::info('No approved leave requests found', [
            'month' => $month,
            'year' => $year,
        ]);
        return response()->json([
            'message' => 'No approved leave requests found for the specified month and year',
            'data' => []
        ], 200);
    }

    $result = $leaveRequests->map(function ($leaveRequest) {
        $formatted = [
            'user' => [
                'id' => $leaveRequest->user->id,
                'name' => $leaveRequest->user->name,
                'email' => $leaveRequest->user->email,
            ],
            'leave_request' => [
                'id' => $leaveRequest->id,
                'leave_dates' => $leaveRequest->leave_dates,
                'leave_type' => $leaveRequest->leave_type,
                'reason' => $leaveRequest->reason,
                'days' => $leaveRequest->days,
                'approved_at' => $leaveRequest->updated_at,
            ]
        ];
        Log::debug('Formatted leave request', [
            'leave_request_id' => $leaveRequest->id,
            'data' => $formatted,
        ]);
        return $formatted;
    });

    Log::info('Returning approved leave requests', [
        'count' => $result->count(),
    ]);

    return response()->json([
        'message' => 'Approved leave requests retrieved successfully',
        'data' => $result
    ], 200);
}
}
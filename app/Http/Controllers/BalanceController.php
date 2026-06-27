<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Balance; // Import the Balance model
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class BalanceController extends Controller
{
    // ... (other methods remain unchanged) ...

    /**
     * Get the leave balance of the authenticated user.
     */
    public function getLeaveBalance(Request $request)
    {
        $userId = Auth::id();
        
        $balance = Balance::where('user_id', $userId)->first();

        return response()->json([
            'message' => 'Leave balance retrieved successfully',
            'data' => [
                'sick_leaves' => $balance->sick_leaves,
                'casual_leaves' => $balance->casual_leaves,
                'planned_leaves' => $balance->planned_leaves,
            ]
        ]);
    }

    public function getLeaveBalances(Request $request)
    {
        // Get all users who have the role 'employee'
        $employees = User::role('employee')->with('balance')->get();

        // Format response data
        $leaveBalances = $employees->map(function ($employee) {
            return [
                'user_id' => $employee->id,
                'name' => $employee->name,
                'sick_leaves' => $employee->balance->sick_leaves ?? 0,
                'casual_leaves' => $employee->balance->casual_leaves ?? 0,
                'planned_leaves' => $employee->balance->planned_leaves ?? 0,
            ];
        });

        return response()->json([
            'message' => 'Leave balances retrieved successfully',
            'data' => $leaveBalances
        ]);
    }
}
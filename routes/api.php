<?php

    use App\Http\Controllers\AttendanceController;
    use App\Http\Controllers\BalanceController;
    use Illuminate\Support\Facades\Route;
    use App\Http\Controllers\UserController;
    use App\Http\Controllers\LeaveRequestController;

    Route::middleware('auth:sanctum')->group(function () {
        Route::middleware(['role:admin|management'])->group(function () {
            Route::post('register/employee',[UserController::class,'registerEmp']);
            Route::post('register/management',[UserController::class,'registerManagement']);
            Route::get('attendance/get/status',[AttendanceController::class,'getAllEmployeeStatuses']);
            Route::get('attendance/get/{userId}',[AttendanceController::class,'getUserAttendancesById']);
            Route::get('leave/request/sent',[LeaveRequestController::class,'getLeaveRequestsForUser']);
            Route::get('leave/request/calendar',[LeaveRequestController::class,'getApprovedLeavesByMonth']);
        });
        Route::middleware(['role:management'])->group(function () {
            Route::get('leave/balance/all',[BalanceController::class,'getLeaveBalances']);
        });
        Route::middleware(['role:employee'])->group(function () { 
        });
        Route::post('logout',[UserController::class,'logout']);
        Route::get('user',[UserController::class,'getUser']);
        Route::get('user/all',[UserController::class,'getAllUsers']);
        Route::post('user/update/name',[UserController::class,'updateName']);
        Route::post('leave/request/send',[LeaveRequestController::class,'sendLeaveRequest']);
        Route::get('leave/request',[LeaveRequestController::class,'getLeaveRequests']);
        Route::get('leave/request/{id}',[LeaveRequestController::class,'getLeaveRequestById']);
        Route::post('leave/request/update/{id}',[LeaveRequestController::class,'updateLeaveRequestStatus']);
        Route::get('leave/balance',[BalanceController::class,'getLeaveBalance']);
        Route::post('attendance/mark',[AttendanceController::class,'markAttendance']);
        Route::get('attendance/today',[AttendanceController::class,'checkTodayStatus']);
        Route::get('attendance/get',[AttendanceController::class,'getAttendances']);
        Route::post('lunchtime/toggle',[AttendanceController::class,'toggleLunchtime']);
    });

    Route::post('login',[UserController::class,'login']);
    Route::post('password/forgot',[UserController::class,'forgotPassword']);
    Route::post('password/reset',[UserController::class,'resetPassword']);
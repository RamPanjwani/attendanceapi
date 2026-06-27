<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use App\Models\balance;
class UserController extends Controller
{
    public function registerEmp(Request $request){
        $registerUserData = $request->validate([
            'name'=>'required|string',
            'email'=>'required|string|email|unique:users',
            'password'=>'required|min:8'
        ]);
        $user = User::create([
            'name' => $registerUserData['name'],
            'email' => $registerUserData['email'],
            'password' => Hash::make($registerUserData['password']),
        ]);
        $user->assignRole('employee');
        Balance::create([
            'user_id' => $user->id,
            'sick_leaves' => 2,
            'casual_leaves' => 2,
            'planned_leaves' => 2
        ]);
        return response()->json([
            'message' => 'User Created ',
        ]);
    }

    public function registerManagement(Request $request){
        $registerUserData = $request->validate([
            'name'=>'required|string',
            'email'=>'required|string|email|unique:users',
            'password'=>'required|min:8'
        ]);
        $user = User::create([
            'name' => $registerUserData['name'],
            'email' => $registerUserData['email'],
            'password' => Hash::make($registerUserData['password']),
        ]);
        $user->assignRole('management');
        Balance::create([
            'user_id' => $user->id,
            'sick_leaves' => 2,
            'casual_leaves' => 2,
            'planned_leaves' => 2
        ]);
        return response()->json([
            'message' => 'User Created ',
        ]);
    }

    public function login(Request $request){
        $loginUserData = $request->validate([
            'email'=>'required|string|email',
            'password'=>'required|min:8'
        ]);
        $user = User::where('email',$loginUserData['email'])->first();
        if(!$user || !Hash::check($loginUserData['password'],$user->password)){
            return response()->json([
                'message' => 'Invalid Credentials'
            ],401);
        }
        $token = $user->createToken($user->name.'-AuthToken')->plainTextToken;
        return response()->json([
            'access_token' => $token,
        ]);
    }

    public function logout(){
        auth()->user()->tokens()->delete();

        return response()->json([
          "message"=>"logged out"
        ]);
    }

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        $status = Password::sendResetLink($request->only('email'));
        return $status === Password::RESET_LINK_SENT
            ? back()->with(['status' => __($status)])
            : back()->withErrors(['email' => __($status)]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password)
                ])->save();
            }
        );

        return $status === Password::PASSWORD_RESET
            ? response()->json(['message' => 'Password reset successfully.'], 200)
            : response()->json(['message' => 'Password reset failed.'], 400);
    }

    public function updateName(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);
        $user = Auth::user();
        $user->name = $request->name;
        return response()->json(['message' => 'Name updated successfully']);
    }

    public function getUser()
    {
        $user = auth()->user();

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,  // or whatever attribute holds the user's name
            'roles' => $user->getRoleNames()
        ]);
    }


    public function getAllUsers()
    {
        $loggedInUser = auth()->user();
        if ($loggedInUser->hasRole('admin')) {
            $users = User::all();
        } elseif ($loggedInUser->hasRole('management')) {
            $users = User::role('employee')->get();
        } elseif ($loggedInUser->hasRole('employee')) {
            $users = User::role('management')->get();
        } else {
            $users = collect([]);
        }
        return response()->json([
            'users' => $users
        ]);
    }
}

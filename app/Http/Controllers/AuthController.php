<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    // Get the current user
    public function me(Request $request)
    {
        if (!$request->user()) {
            $this->unauthenticated();
        }

        // Get user with position, designation, and station
        $user = User::with('position', 'designation', 'station')
            ->find($request->user()->id);

        return response()->json([
            'data' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'middle_name' => $user->middle_name,
                'last_name' => $user->last_name,
                'role' => $user->role,
                'username' => $user->username,
                'email' => $user->email,
                'phone' => $user->phone,
                'position' => $user->position->only(['id', 'position_name']),
                'designation' => $user->designation->only(['id', 'designation_name']),
                'station' => $user->station->only(['id', 'station_name'])
            ]
        ]);
    }

    // Login a user
    public function login(Request $request)
    {
        // Validate the request
        $validated = $request->validate([
            'username' => 'required',
            'password' => 'required|min:6'
        ]);

        // Attempt to log the user in
        if (!auth()->attempt($validated)) {
            return response()->json([
                'data' => [
                    'message' => 'Invalid credentials',
                    'error' => 1
                ]
            ], 401);
        }

        // Generate a token for the user
        $token = auth()->user()->createToken('authToken')->plainTextToken;

        return response()->json([
            'data' => [
                'access_token' => $token,
                'message' => 'Logged in successfully',
                'success' => 1
            ]
        ]);
    }

    // Logout a user
    public function logout(Request $request)
    {
        try {
            // Revoke the user's token
            $request->user()->currentAccessToken()->delete();
        } catch (\Throwable $th) {
            return response()->json([
                'data' => [
                    'message' => 'Logout failed',
                    'error' => 1
                ]
            ], 422);
        }

        return response()->json([
            'data' => [
                'message' => 'Logged out successfully',
                'success' => 1
            ]
        ], 200);
    }

    // Unauthenticated user
    public function unauthenticated()
    {
        return response()->json([
            'data' => [
                'message' => 'Unauthenticated',
                'error' => 1
            ]
        ], 401);
    }
}

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

    // Register a user
    public function register(Request $request)
    {
        // check if user is not admin
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'data' => [
                    'message' => 'Unauthorized',
                    'error' => 1
                ]
            ], 401);
        }

        // Validate the request
        $validated = $request->validate([
            'first_name' => 'required',
            'middle_name' => '',
            'last_name' => 'required',
            'email' => 'email|unique:users',
            'phone' => 'unique:users',
            'position_id' => 'required',
            'designation_id' => 'required',
            'station_id' => 'required',
            'username' => 'required|unique:users',
            'password' => 'required|min:6'
        ]);

        try {
            // Create a user
            $user = User::create(array_merge(
                $validated,
                ['password' => bcrypt($request->password)]
            ));
        } catch (\Throwable $th) {
            return response()->json([
                'data' => [
                    'message' => 'User registration failed',
                    'error' => 1
                ]
            ], 422);
        }

        return response()->json([
            'data' => [
                'data' => $request->except('password'),
                'message' => 'User registered successfully',
                'success' => 1
            ]
        ], 201);
    }

    // Update a user
    public function update(Request $request, $id)
    {
        // check if user is not admin
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'data' => [
                    'message' => 'Unauthorized',
                    'error' => 1
                ]
            ], 401);
        }

        // Validate the request
        $validated = $request->validate([
            'first_name' => 'required',
            'middle_name' => '',
            'last_name' => 'required',
            'email' => 'email',
            'phone' => '',
            'position_id' => 'required',
            'designation_id' => 'required',
            'station_id' => 'required',
            'username' => 'required',
            'password' => 'required|min:6',
            'role' => 'required',
            'is_active' => 'required|boolean'
        ]);

        try {
            // Update a user
            $user = User::find($id);
            $user->update(array_merge(
                $validated,
                ['password' => bcrypt($request->password)]
            ));
        } catch (\Throwable $th) {
            return response()->json([
                'data' => [
                    'message' => 'User update failed',
                    'error' => 1
                ]
            ], 422);
        }

        return response()->json([
            'data' => [
                'data' => $request->except('password'),
                'message' => 'User updated successfully',
                'success' => 1
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

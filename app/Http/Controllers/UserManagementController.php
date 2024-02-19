<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;

class UserManagementController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $search = trim($request->search) ?? '';

        // Get all users paginated
        $users = User::with([
            'position:id,position_name',
            'designation:id,designation_name',
            'station:id,station_name'
        ])
        ->where('first_name', 'LIKE', "%{$search}%")
        ->orWhere('middle_name', 'LIKE', "%{$search}%")
        ->orWhere('last_name', 'LIKE', "%{$search}%")
        ->orWhere('email', 'LIKE', "%{$search}%")
        ->orWhere('phone', 'LIKE', "%{$search}%")
        ->orWhere('username', 'LIKE', "%{$search}%")
        ->orWhere('role', 'LIKE', "%{$search}%")
        ->orWhereRelation('position', 'position_name', 'LIKE', "%{$search}%")
        ->orWhereRelation('designation', 'designation_name', 'LIKE', "%{$search}%")
        ->orWhereRelation('station', 'station_name', 'LIKE', "%{$search}%")
        ->orderBy('first_name')
        ->orderBy('last_name')
        ->paginate(50);

        return response()->json([
            'data' => $users
        ], 201);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
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

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        // Return a json response of the official receipt
        return response()->json([
            'data' => $user
        ], 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
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

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        try {
            $user->delete();
        } catch (\Throwable $th) {
            return response()->json([
                'data' => [
                    'message' =>
                        $th->getCode() === '23000' ?
                            'Failed to delete user. There is a connected OR/s for this user.' :
                            'Unknown error occured',
                    'error' => 1
                ]
            ], 422);
        }

        return response()->json([
            'data' => [
                'message' => 'User deleted successfully',
                'success' => 1
            ]
        ], 201);
    }
}

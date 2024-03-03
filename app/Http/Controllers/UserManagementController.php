<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Position;
use App\Models\Designation;
use App\Models\Station;
use App\Services\UserLogsServices;

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
     * Display a listing of the resource.
     */
    public function indexLogs(Request $request)
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
        $userLog = new UserLogsServices(
            $request->user()->id,
            $request->getClientIp(),
            $request->header('User-Agent') ?? $request->server('User-Agent')
        );

        // Validate the request
        $validated = $request->validate([
            'first_name' => 'required',
            'middle_name' => 'nullable',
            'last_name' => 'required',
            'email' => 'email|unique:users|nullable',
            'phone' => 'unique:users|nullable',
            'position_id' => 'required',
            'designation_id' => 'required',
            'station_id' => 'required',
            'username' => 'required|unique:users',
            'password' => 'required|min:6',
            'is_active' => 'required'
        ]);

        // Create a new position if not exists and get the id
        $position = Position::find($validated['position_id']);
        if (!$position) {
            $position = Position::create([
                'position_name' => $validated['position_id'],
            ]);
        }

        // Create a new designation if not exists and get the id
        $designation = Designation::find($validated['designation_id']);
        if (!$designation) {
            $designation = Designation::create([
                'designation_name' => $validated['designation_id'],
            ]);
        }

        // Create a new station if not exists and get the id
        $station = Station::find($validated['station_id']);
        if (!$station) {
            $station = Station::create([
                'station_name' => $validated['station_id'],
            ]);
        }

        try {
            // Create a user
            $user = User::create(array_merge(
                $validated,
                [
                    'position_id' => $position->id,
                    'designation_id' => $designation->id,
                    'station_id' => $station->id,
                    'password' => bcrypt($request->password)
                ]
            ));
        } catch (\Throwable $th) {
            return response()->json([
                'data' => [
                    'message' => 'User registration failed',
                    'error' => 1
                ]
            ], 422);
        }

        $userLog->logActivity(
            "Registered user - $user->first_name $user->last_name"
        );
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
        $userLog = new UserLogsServices(
            $request->user()->id,
            $request->getClientIp(),
            $request->header('User-Agent') ?? $request->server('User-Agent')
        );

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
            'email' => 'email|nullable',
            'phone' => 'nullable',
            'position_id' => 'required',
            'designation_id' => 'required',
            'station_id' => 'required',
            'username' => 'required',
            'role' => 'required',
            'is_active' => 'required|boolean',
            'password' => ''
        ]);

        // Create a new position if not exists and get the id
        $position = Position::find($validated['position_id']);
        if (!$position) {
            $position = Position::create([
                'position_name' => $validated['position_id'],
            ]);
        }

        // Create a new designation if not exists and get the id
        $designation = Designation::find($validated['designation_id']);
        if (!$designation) {
            $designation = Designation::create([
                'designation_name' => $validated['designation_id'],
            ]);
        }

        // Create a new station if not exists and get the id
        $station = Station::find($validated['station_id']);
        if (!$station) {
            $station = Station::create([
                'station_name' => $validated['station_id'],
            ]);
        }

        try {
            // Update a user
            $user = User::find($id);

            if (trim($request->password)) {
                $user->update(array_merge(
                    $validated,
                    [
                        'position_id' => $position->id,
                        'designation_id' => $designation->id,
                        'station_id' => $station->id,
                        'password' => bcrypt(trim($request->password))
                    ]
                ));
            } else {
                $user->update([
                    'first_name' => $validated['first_name'],
                    'middle_name' => $validated['middle_name'],
                    'last_name' => $validated['last_name'],
                    'email' => $validated['email'],
                    'phone' => $validated['phone'],
                    'position_id' => $position->id,
                    'designation_id' => $designation->id,
                    'station_id' => $station->id,
                    'username' => $validated['username'],
                    'role' => $validated['role'],
                    'is_active' => $validated['is_active']
                ]);
            }
        } catch (\Throwable $th) {
            return response()->json([
                'data' => [
                    'message' => 'User update failed',
                    'error' => 1
                ]
            ], 422);
        }

        $userLog->logActivity(
            "Updated user - $user->first_name $user->last_name"
        );
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
    public function destroy(Request $request, User $user)
    {
        $userLog = new UserLogsServices(
            $request->user()->id,
            $request->getClientIp(),
            $request->header('User-Agent') ?? $request->server('User-Agent')
        );

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

        $userLog->logActivity(
            "Deleted user - $user->first_name $user->last_name"
        );
        return response()->json([
            'data' => [
                'message' => 'User deleted successfully',
                'success' => 1
            ]
        ], 201);
    }
}

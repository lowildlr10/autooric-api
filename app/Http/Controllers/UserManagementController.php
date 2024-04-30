<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Position;
use App\Models\Designation;
use App\Models\Station;
use App\Services\UserLogsServices;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;
use Intervention\Image\Drivers\Gd\Driver;

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
            'middle_name' => 'nullable|string',
            'last_name' => 'required',
            'email' => 'email|unique:users|nullable',
            'phone' => 'unique:users|nullable',
            'position_id' => 'required',
            'designation_id' => 'required',
            'station_id' => 'required',
            'username' => 'required|unique:users',
            'password' => 'required|min:6',
            'role' => 'required',
            'esig' => 'nullable|string',
            'is_active' => 'required|boolean'
        ]);

        try {
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

            // Create a user
            $user = User::create(array_merge(
                $validated,
                [
                    'position_id' => $position->id,
                    'designation_id' => $designation->id,
                    'station_id' => $station->id,
                    'esig' => null,
                    'password' => bcrypt($request->password)
                ]
            ));

            if ($request->esig && !empty($request->esig)) {
                $esig = $this->processAndSaveImage($request->esig, $user->id);
                $user->esig = $esig;
                $user->save();
            }
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
            'first_name' => 'required|string',
            'middle_name' => 'nullable|string',
            'last_name' => 'required|string',
            'email' => 'email|nullable',
            'phone' => 'nullable|string',
            'position_id' => 'required|string',
            'designation_id' => 'required|string',
            'station_id' => 'required|string',
            'username' => 'required|string',
            'role' => 'required|string',
            'esig' => 'nullable|string',
            'is_active' => 'required|boolean',
            'password' => ''
        ]);

        try {
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

            // Update a user
            $user = User::find($id);

            if ($request->esig !== $user->esig && !empty($request->esig)) {
                $esig = $this->processAndSaveImage($request->esig, $id);
            } else {
                if (!empty($request->esig)) {
                    $esig = $request->esig;
                } else {
                    $esig = null;
                }
            }

            if (trim($request->password)) {
                $user->update(array_merge(
                    $validated,
                    [
                        'position_id' => $position->id,
                        'designation_id' => $designation->id,
                        'station_id' => $station->id,
                        'esig' => $esig,
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
                    'esig' => $esig,
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
        if (User::count() === 1) {
            return response()->json([
                'data' => [
                    'message' => 'Failed to delete user. System has only one user registerd.',
                    'error' => 1
                ]
            ], 422);
        }

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
                            'Failed to delete category. There are records connected to this record.' :
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

    private function processAndSaveImage($base64Data, $imageName)
    {
        $appUrl = env('APP_URL') ?? 'http://localhost';

        $width = 100;
        $image = Image::read($base64Data)->scale($width);

        $filename = "$imageName.png";
        $directory = 'public/images/esig';
        $publicPath = "$appUrl/storage/images/esig/$filename";

        if (!Storage::exists($directory)) {
            Storage::makeDirectory($directory);
        }

        $image->encodeByExtension('png', progressive: true, quality: 10)
            ->save(public_path("storage/images/esig/$filename"));

        return $publicPath;
    }
}

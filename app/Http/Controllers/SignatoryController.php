<?php

namespace App\Http\Controllers;

use App\Models\Signatory;
use App\Models\Position;
use App\Models\Designation;
use App\Models\Station;
use App\Http\Requests\StoreSignatoryRequest;
use App\Http\Requests\UpdateSignatoryRequest;
use Illuminate\Http\Request;

class SignatoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Get all the paper sizes
        $signatories = Signatory::orderBy('signatory_name')
            ->get();

        return response()->json([
            'data' => $signatories
        ]);
    }

    /**
     * Display a listing of the resource.
     */
    public function indexPaginated(Request $request)
    {
        $search = trim($request->search) ?? '';

        // Get all the paper sizes
        $signatories = Signatory::orderBy('signatory_name');

        if ($search) {
            $signatories = $signatories
                ->where('signatory_name', 'LIKE', "%$search%")
                ->orWhere('report_module', 'LIKE', "%$search%");
        }

        $signatories = $signatories->paginate(50);

        return response()->json([
            'data' => $signatories
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreSignatoryRequest $request)
    {
        $reportModule = $request->report_module ?? [];

        // Validate the request
        $request->validated();

        try {
            foreach ($reportModule as $keyReport => $report) {
                foreach ($report as $key => $value) {
                    if ($key === 'position_id' && $value) {

                        // Create a new position if not exists and get the id
                        $position = Position::find($value);
                        if (!$position) {
                            $position = Position::create([
                                'position_name' => $value
                            ]);
                        }
                        $reportModule[$keyReport]['position_id'] = $position->id;
                    }

                    if ($key === 'designation_id' && $value) {
                        // Create a new designation if not exists and get the id
                        $designation = Designation::find($value);
                        if (!$designation) {
                            $designation = Designation::create([
                                'designation_name' => $value
                            ]);
                        }

                        $reportModule[$keyReport]['designation_id'] = $designation->id;
                    }

                    if ($key === 'station_id' && $value) {
                        // Create a new station if not exists and get the id
                        $station = Station::find($value);
                        if (!$station) {
                            $station = Station::create([
                                'station_name' => $value
                            ]);
                        }

                        $reportModule[$keyReport]['station_id'] = $station->id;
                    }
                }
            }

            // Create a new signatory
            $paperSize = Signatory::create([
                'signatory_name' => $request->signatory_name,
                'report_module' => json_encode($request->report_module)
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'data' => [
                    'message' => 'Failed to create signatory',
                    'error' => 1
                ]
            ], 422);
        }

        return response()->json([
            'data' => [
                'data' => $request->all(),
                'message' => 'Signatory created successfully',
                'success' => 1
            ]
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Signatory $signatory)
    {
        // Return a json response of the paper size
        return response()->json([
            'data' => $signatory,
            'success' => 1
        ], 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateSignatoryRequest $request, Signatory $signatory)
    {
        $reportModule = $request->report_module ?? [];

        // Validate the request
        $request->validated();

        try {
            foreach ($reportModule as $keyReport => $report) {
                foreach ($report as $key => $value) {
                    if ($key === 'position_id' && $value) {

                        // Create a new position if not exists and get the id
                        $position = Position::find($value);
                        if (!$position) {
                            $position = Position::create([
                                'position_name' => $value
                            ]);
                        }
                        $reportModule[$keyReport]['position_id'] = $position->id;
                    }

                    if ($key === 'designation_id' && $value) {
                        // Create a new designation if not exists and get the id
                        $designation = Designation::find($value);
                        if (!$designation) {
                            $designation = Designation::create([
                                'designation_name' => $value
                            ]);
                        }

                        $reportModule[$keyReport]['designation_id'] = $designation->id;
                    }

                    if ($key === 'station_id' && $value) {
                        // Create a new station if not exists and get the id
                        $station = Station::find($value);
                        if (!$station) {
                            $station = Station::create([
                                'station_name' => $value
                            ]);
                        }

                        $reportModule[$keyReport]['station_id'] = $station->id;
                    }
                }
            }

            // Create a new signatory
            $signatory->update([
                'signatory_name' => $request->signatory_name,
                'report_module' => json_encode($reportModule),
                'is_active' => $request->is_active
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'data' => [
                    'message' => 'Failed to update signatory',
                    'error' => 1
                ]
            ], 422);
        }

        return response()->json([
            'data' => [
                'data' => $request->all(),
                'message' => 'Signatory updated successfully',
                'success' => 1
            ]
        ], 201);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Signatory $signatory)
    {
        try {
            $signatory->delete();
        } catch (\Throwable $th) {
            return response()->json([
                'data' => [
                    'message' => 'Unknown error occured',
                    'error' => 1
                ]
            ], 422);
        }

        return response()->json([
            'data' => [
                'message' => 'Signatory deleted successfully',
                'success' => 1
            ]
        ], 201);
    }
}

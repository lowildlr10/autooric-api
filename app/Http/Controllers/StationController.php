<?php

namespace App\Http\Controllers;

use App\Models\Station;

class StationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Get all the stations
        $stations = Station::orderBy('station_name')
            ->get();

        return response()->json([
            'data' => $stations
        ]);
    }
}

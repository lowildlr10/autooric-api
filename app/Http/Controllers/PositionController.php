<?php

namespace App\Http\Controllers;

use App\Models\Position;

class PositionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Get all the positions
        $positions = Position::orderBy('position_name')
            ->get();

        return response()->json([
            'data' => $positions
        ]);
    }
}

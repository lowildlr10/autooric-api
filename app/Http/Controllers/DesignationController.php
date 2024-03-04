<?php

namespace App\Http\Controllers;

use App\Models\Designation;

class DesignationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Get all the designations
        $designations = Designation::orderBy('designation_name')
            ->get();

        return response()->json([
            'data' => $designations
        ]);
    }
}

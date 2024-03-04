<?php

namespace App\Http\Controllers;

use App\Models\Payor;

class PayorController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Get all the payors
        $payors = Payor::orderBy('payor_name')
            ->get();

        return response()->json([
            'data' => $payors
        ]);
    }
}

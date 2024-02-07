<?php

namespace App\Http\Controllers;

use App\Models\Payor;
use App\Http\Requests\StorePayorRequest;
use App\Http\Requests\UpdatePayorRequest;

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

    /**
     * Display a listing of the resource.
     */
    public function indexPaginated()
    {
        // Get all the payors
        $payors = Payor::orderBy('payor_name')
            ->paginate(50);

        return response()->json([
            'data' => $payors
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePayorRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Payor $payor)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePayorRequest $request, Payor $payor)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Payor $payor)
    {
        //
    }
}

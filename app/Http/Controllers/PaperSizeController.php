<?php

namespace App\Http\Controllers;

use App\Models\PaperSize;
use App\Http\Requests\StorePaperSizeRequest;
use App\Http\Requests\UpdatePaperSizeRequest;

class PaperSizeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePaperSizeRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(PaperSize $paperSize)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePaperSizeRequest $request, PaperSize $paperSize)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PaperSize $paperSize)
    {
        //
    }
}

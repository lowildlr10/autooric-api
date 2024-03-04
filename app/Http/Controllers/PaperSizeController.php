<?php

namespace App\Http\Controllers;

use App\Models\PaperSize;
use App\Http\Requests\StorePaperSizeRequest;
use App\Http\Requests\UpdatePaperSizeRequest;
use Illuminate\Http\Request;

class PaperSizeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Get all the paper sizes
        $paperSizes = PaperSize::orderBy('paper_name')
            ->get();

        return response()->json([
            'data' => $paperSizes
        ]);
    }

    /**
     * Display a listing of the resource.
     */
    public function indexPaginated(Request $request)
    {
        $search = trim($request->search) ?? '';

        // Get all the paper sizes
        $paperSizes = PaperSize::orderBy('paper_name');

        if ($search) {
            $paperSizes = $paperSizes
                ->where('paper_name', 'LIKE', "%$search%")
                ->orWhere('width', 'LIKE', "%$search%")
                ->orWhere('height', 'LIKE', "%$search%");
        }

        $paperSizes = $paperSizes->paginate(50);

        return response()->json([
            'data' => $paperSizes
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePaperSizeRequest $request)
    {
        // Validate the request
        $request->validated();

        try {
            // Create a new paperSize
            $paperSize = PaperSize::create([
                'paper_name' => $request->paper_name,
                'width' => $request->width,
                'height' => $request->height
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'data' => [
                    'message' => 'Failed to create paper size',
                    'error' => 1
                ]
            ], 422);
        }

        return response()->json([
            'data' => [
                'data' => $request->all(),
                'message' => 'Paper size created successfully',
                'success' => 1
            ]
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(PaperSize $paperSize)
    {
        // Return a json response of the paper size
        return response()->json([
            'data' => $paperSize,
            'success' => 1
        ], 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePaperSizeRequest $request, PaperSize $paperSize)
    {
        // Validate the request
        $request->validated();

        try {
            // Create a new category
            $paperSize->update([
                'paper_name' => $request->paper_name,
                'width' => $request->width,
                'height' => $request->height
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'data' => [
                    'message' => 'Failed to update paper size',
                    'error' => 1
                ]
            ], 422);
        }

        return response()->json([
            'data' => [
                'data' => $request->all(),
                'message' => 'Paper size updated successfully',
                'success' => 1
            ]
        ], 201);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PaperSize $paperSize)
    {
        try {
            $paperSize->delete();
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
                'message' => 'Paper size deleted successfully',
                'success' => 1
            ]
        ], 201);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Particular;
use App\Http\Requests\StoreParticularRequest;
use App\Http\Requests\UpdateParticularRequest;

class ParticularController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Get all the particulars
        $particulars = Particular::with('category:id,category_name')
            ->orderBy('particular_name')
            ->get();

        return response()->json([
            'data' => $particulars
        ]);
    }

    /**
     * Display a listing of the resource.
     */
    public function indexPaginated()
    {
        // Get all the particulars
        $particulars = Particular::with('category:id,category_name')
            ->orderBy('particular_name')
            ->paginate(50);

        return response()->json([
            'data' => $particulars
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreParticularRequest $request)
    {
        // Validate the request
        $request->validated();

        // Create a new category if not exists and get the id
        $category = Category::find($request->category_id);
        if (!$category) {
            $category = Category::create([
                'category_name' => $request->category_id
            ]);
        }

        try {
            $isExisting = Particular::where('category_id', $category->id)
                ->where('particular_name', $request->particular_name)
                ->first();

            if ($isExisting) {
                return response()->json([
                    'data' => [
                        'message' => 'Particular already exists',
                        'error' => 1
                    ]
                ], 422);
            }

            // Create a new particular
            $particular = Particular::create([
                'category_id' => $category->id,
                'particular_name' => $request->particular_name,
                'order_no' => Particular::where('category_id', $category->id)
                    ->count()
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'data' => [
                    'message' => 'Failed to create particular',
                    'error' => 1
                ]
            ], 422);
        }

        return response()->json([
            'data' => [
                'data' => [
                    'particular_name' => $request->particular_name,
                    'category' => $category->only(['id', 'category_name']),
                ],
                'message' => 'Particular created successfully',
                'success' => 1
            ]
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Particular $particular)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateParticularRequest $request, Particular $particular)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Particular $particular)
    {
        //
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Particular;
use App\Http\Requests\StoreParticularRequest;
use App\Http\Requests\UpdateParticularRequest;
use Illuminate\Http\Request;

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
    public function indexPaginated(Request $request)
    {
        $search = trim($request->search) ?? '';

        $categories = Category::with(['particulars' => function($query) use ($search) {
            if ($search) {
                $query->where('particular_name', 'LIKE', "%$search%");
            }
        }]);

        if ($search) {
            $categories = $categories
                ->where('category_name', 'LIKE', "%$search%")
                ->orWhereRelation('particulars', 'particular_name', 'LIKE', "%$search%")
                ->orWhereRelation('particulars', 'default_amount', 'LIKE', "%$search%");
        }

        $categories = $categories
            ->orderBy('order_no')
            ->paginate(5);

        return response()->json([
            'data' => $categories
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
                'default_amount' => $request->default_amount,
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
                    'default_amount' => $request->default_amount,
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
        // Return a json response of the particular
        return response()->json([
            'data' => $particular,
            'success' => 1
        ], 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateParticularRequest $request, Particular $particular)
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
            // Create a new particular
            $particular = Particular::create([
                'category_id' => $category->id,
                'particular_name' => $request->particular_name,
                'default_amount' => $request->default_amount,
                'order_no' => $request->order_no,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'data' => [
                    'message' => 'Failed to update particular',
                    'error' => 1
                ]
            ], 422);
        }

        return response()->json([
            'data' => [
                'data' => [
                    'particular_name' => $request->particular_name,
                    'default_amount' => $request->default_amount,
                    'category' => $category->only(['id', 'category_name']),
                ],
                'message' => 'Particular updated successfully',
                'success' => 1
            ]
        ], 201);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Particular $particular)
    {
        try {
            $particular->delete();
        } catch (\Throwable $th) {
            return response()->json([
                'data' => [
                    'message' =>
                        $th->getCode() === '23000' ?
                            'Failed to delete particular. There are a connected OR/s for this record.' :
                            'Unknown error occured',
                    'error' => 1
                ]
            ], 422);
        }

        return response()->json([
            'data' => [
                'message' => 'Particular deleted successfully',
                'success' => 1
            ]
        ], 201);
    }
}

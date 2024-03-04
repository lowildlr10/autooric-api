<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Get all the categories
        $categories = Category::orderBy('category_name')->get();

        return response()->json([
            'data' => $categories
        ]);
    }

    /**
     * Display a listing of the resource.
     */
    public function indexPaginated(Request $request)
    {
        $search = trim($request->search) ?? '';

        // Get all the categories
        $categories = Category::orderBy('order_no');

        if ($search) {
            $categories = $categories->where('category_name', 'LIKE', "%$search%");
        }

        $categories = $categories
            ->paginate(50);

        return response()->json([
            'data' => $categories
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCategoryRequest $request)
    {
        // Validate the request
        $request->validated();

        try {
            // Create a new category
            $category = Category::create([
                'category_name' => $request->category_name
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'data' => [
                    'message' => 'Failed to create a category',
                    'error' => 1
                ]
            ], 422);
        }

        return response()->json([
            'data' => [
                'data' => $request->all(),
                'message' => 'Category created successfully',
                'success' => 1
            ]
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Category $category)
    {
        // Return a json response of the category
        return response()->json([
            'data' => $category,
            'success' => 1
        ], 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCategoryRequest $request, Category $category)
    {
        // Validate the request
        $request->validated();

        try {
            // Create a new category
            $category->update([
                'category_name' => $request->category_name,
                'order_no' => $request->order_no
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'data' => [
                    'message' => 'Failed to update category',
                    'error' => 1
                ]
            ], 422);
        }

        return response()->json([
            'data' => [
                'data' => $request->all(),
                'message' => 'Category updated successfully',
                'success' => 1
            ]
        ], 201);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Category $category)
    {
        try {
            $category->delete();
        } catch (\Throwable $th) {
            return response()->json([
                'data' => [
                    'message' =>
                        $th->getCode() === '23000' ?
                            'Failed to delete category. There are records connected to this record.' :
                            'Unknown error occured',
                    'error' => 1
                ]
            ], 422);
        }

        return response()->json([
            'data' => [
                'message' => 'Category deleted successfully',
                'success' => 1
            ]
        ], 201);
    }
}

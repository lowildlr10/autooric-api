<?php

namespace App\Http\Controllers;

use App\Models\Discount;
use App\Http\Requests\StoreDiscountRequest;
use App\Http\Requests\UpdateDiscountRequest;
use Illuminate\Http\Request;

class DiscountController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Get all the discounts
        $discounts = Discount::orderBy('discount_name')->get();

        return response()->json([
            'data' => $discounts
        ]);
    }

    /**
     * Display a listing of the resource.
     */
    public function indexPaginated(Request $request)
    {
        $search = trim($request->search) ?? '';

        // Get all the discounts
        $discounts = Discount::orderBy('discount_name');

        if ($search) {
            $discounts = $discounts
                ->where('discount_name', 'LIKE', "%$search%")
                ->orWhere('percent', 'LIKE', "%$search%");
        }

        $discounts = $discounts->paginate(50);

        return response()->json([
            'data' => $discounts
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreDiscountRequest $request)
    {
        // Validate the request
        $request->validated();

        try {
            // Create a new discount
            $discount = Discount::create([
                'discount_name' => $request->discount_name,
                'percent' => $request->percent,
                'requires_card_no' => $request->requires_card_no
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'data' => [
                    'message' => 'Failed to create discount',
                    'error' => 1
                ]
            ], 422);
        }

        return response()->json([
            'data' => [
                'data' => $request->all(),
                'message' => 'Discount created successfully',
                'success' => 1
            ]
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Discount $discount)
    {
        // Return a json response of the discount
        return response()->json([
            'data' => $discount,
            'success' => 1
        ], 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateDiscountRequest $request, Discount $discount)
    {
        // Validate the request
        $request->validated();

        try {
            // Create a new discount
            $discount->update([
                'discount_name' => $request->discount_name,
                'percent' => $request->percent,
                'requires_card_no' => $request->requires_card_no,
                'is_active' => $request->is_active
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'data' => [
                    'message' => 'Failed to update discount',
                    'error' => 1
                ]
            ], 422);
        }

        return response()->json([
            'data' => [
                'data' => $request->all(),
                'message' => 'Discount updated successfully',
                'success' => 1
            ]
        ], 201);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Discount $discount)
    {
        try {
            $discount->delete();
        } catch (\Throwable $th) {
            return response()->json([
                'data' => [
                    'message' =>
                        $th->getCode() === '23000' ?
                            'Failed to delete discount. There are a connected OR/s for this record.' :
                            'Unknown error occured',
                    'error' => 1
                ]
            ], 422);
        }

        return response()->json([
            'data' => [
                'message' => 'Discount deleted successfully',
                'success' => 1
            ]
        ], 201);
    }
}

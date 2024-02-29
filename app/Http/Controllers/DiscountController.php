<?php

namespace App\Http\Controllers;

use App\Models\Discount;
use App\Http\Requests\StoreDiscountRequest;
use App\Http\Requests\UpdateDiscountRequest;

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
    public function indexPaginated()
    {
        // Get all the discounts
        $discounts = Discount::orderBy('discount_name')
            ->paginate(50);

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
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateDiscountRequest $request, Discount $discount)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Discount $discount)
    {
        //
    }
}

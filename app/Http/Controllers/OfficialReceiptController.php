<?php

namespace App\Http\Controllers;

use App\Models\OfficialReceipt;
use App\Models\Payor;
use App\Http\Requests\StoreOfficialReceiptRequest;
use App\Http\Requests\UpdateOfficialReceiptDepositRequest;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;

class OfficialReceiptController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $search = trim($request->search) ?? '';

        // Get all official receipts paginated
        $officialReceipts = OfficialReceipt::with([
            'natureCollection:id,particular_name',
            'payor:id,payor_name',
            'discount:id,discount_name,percent,requires_card_no',
            'accountablePersonnel:id,first_name,last_name',
            'depositedBy:id,first_name,last_name'
        ]);

        try {
            if ($search) {
                $searchData = explode('|', $search);
                $from = $searchData[0] === '*' ? '*' : date_format(date_create($searchData[0]), 'Y-m-d');
                $to = $searchData[1] === '*' ? '*' : date_format(date_create($searchData[1]), 'Y-m-d');
                $particulars = $searchData[2] === '*' ? '' : $searchData[2];

                if ($from !== '*' && $to !== '*') {
                    $officialReceipts = $officialReceipts
                        ->whereBetween('receipt_date', [$from, $to]);
                }

                if ($particulars) {
                    $officialReceipts = $officialReceipts
                        ->whereRelation('natureCollection', 'id', $particulars);
                }
            }
        } catch (\Throwable $th) {}

        $officialReceipts = $officialReceipts
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        return response()->json([
            'data' => $officialReceipts
        ], 201);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreOfficialReceiptRequest $request)
    {
        // Validate the request
        $request->validated();

        // Create a new payor if not exists and get the id
        $payor = Payor::find($request->payor_id);
        if (!$payor) {
            $payor = Payor::create([
                'payor_name' => $request->payor_id,
            ]);
        }

        try {
            // Create a new official receipt
            $officialReceipt = OfficialReceipt::create([
                'accountable_personnel_id' => $request->user()->id,
                'receipt_date' => $request->receipt_date,
                'or_no' => $request->or_no,
                'payor_id' => $payor->id,
                'nature_collection_id' => $request->nature_collection_id,
                'amount' => $request->amount,
                'discount_id' => $request->discount_id,
                'card_no' => $request->card_no,
                'amount_words' => $request->amount_words,
                'payment_mode' => $request->payment_mode,
                'drawee_bank' => $request->drawee_bank,
                'check_no' => $request->check_no,
                'check_date' => $request->check_date
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'data' => [
                    'message' => $th->getCode() === '23000' ? 'Duplicate official receipt number' : 'Failed to create official receipt',
                    'error' => 1
                ]
            ], 422);
        }

        return response()->json([
            'data' => [
                'data' => $officialReceipt,
                'message' => 'Official receipt has been created',
                'success' => 1
            ]
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(OfficialReceipt $officialReceipt)
    {
        // Return a json response of the official receipt
        return response()->json([
            'data' => $officialReceipt,
            'success' => 1
        ], 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function deposit(UpdateOfficialReceiptDepositRequest $request, OfficialReceipt $officialReceipt)
    {
        // Validate the request
        $request->validated();

        if ($officialReceipt->is_cancelled || $officialReceipt->deposit) {
            $message = "";

            if ($officialReceipt->deposit) {
                $message = "Official receipt has already been deposited";
            }

            if ($officialReceipt->is_cancelled) {
                $message = "Official receipt has been cancelled";
            }

            return response()->json([
                'data' => [
                    'message' => $message,
                    'error' => 1
                ]
            ], 422);
        }

        try {
            // Update the official receipt
            $officialReceipt->update([
                'deposited_date' => now(),
                'deposit' => $request->deposit,
                'deposited_by_id' => $request->user()->id,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'data' => [
                    'message' => 'Failed to deposit official receipt',
                    'error' => 1
                ]
            ], 422);
        }

        return response()->json([
            'data' => [
                'message' => 'Official receipt has been deposited',
                'success' => 1
            ]
        ], 201);
    }

    /**
     * Update the cancel status from storage.
     */
    public function cancel(OfficialReceipt $officialReceipt)
    {
        // Update the official receipt
        try {
            $officialReceipt->update([
                'is_cancelled' => true,
                'cancelled_date' => now()
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'data' => [
                    'message' => 'Failed to cancel official receipt',
                    'error' => 1
                ]
            ], 422);
        }

        return response()->json([
            'data' => [
                'message' => 'Official receipt has been cancelled',
                'success' => 1
            ]
        ], 201);
    }
}

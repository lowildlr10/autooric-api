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

        try {
            $dateSearch = date_format(date_create($search), 'Y-m-d');
        } catch (\Throwable $th) {
            $dateSearch = '';
        }

        // Get all official receipts paginated
        $officialReceipts = OfficialReceipt::with([
            'natureCollection:id,particular_name',
            'payor:id,payor_name',
            'discount:id,discount_name,percent,requires_card_no',
            'accountablePersonnel:id,first_name,last_name'
        ])
        ->where('or_no', 'LIKE', "%{$search}%")
        ->orWhere('amount', 'LIKE', "%{$search}%")
        ->orWhereRelation('natureCollection', 'particular_name', 'LIKE', "%{$search}%")
        ->orWhereRelation('payor', 'payor_name', 'LIKE', "%{$search}%")
        ->orWhereRelation('discount', 'discount_name', 'LIKE', "%{$search}%")
        ->orWhereRelation('accountablePersonnel', 'first_name', 'LIKE', "%{$search}%")
        ->orWhereRelation('accountablePersonnel', 'last_name', 'LIKE', "%{$search}%");

        if ($dateSearch) {
            $officialReceipts = $officialReceipts->orWhere('receipt_date', 'LIKE', "%{$dateSearch}%");
        }

        switch (strtolower($search)) {
            case 'pending':
                $officialReceipts = $officialReceipts->orWhere(function (Builder $query) {
                    $query->whereNull('deposited_date')
                        ->whereNull('cancelled_date');
                });
                break;
            case 'cancel':
            case 'cancelled':
                $officialReceipts = $officialReceipts->orWhereNotNull('cancelled_date');
                break;
            case 'deposit':
            case 'deposited':
                $officialReceipts = $officialReceipts->orWhere(function (Builder $query) {
                    $query->whereNotNull('deposited_date')
                        ->whereNull('cancelled_date');
                });
                break;
            default:
                break;
        }

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
                'payment_mode' => $request->payment_mode
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
            'data' => $officialReceipt
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
                'deposit' => $request->deposit
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

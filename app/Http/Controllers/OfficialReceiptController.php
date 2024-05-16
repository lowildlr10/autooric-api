<?php

namespace App\Http\Controllers;

use App\Models\OfficialReceipt;
use App\Models\Payor;
use App\Http\Requests\StoreOfficialReceiptRequest;
use App\Http\Requests\UpdateOfficialReceiptRequest;
use App\Http\Requests\UpdateOfficialReceiptDepositRequest;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

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
            'depositedBy:id,first_name,last_name',
            'cancelledBy:id,first_name,last_name'
        ]);

        try {
            if ($search) {
                $searchData = explode('|', $search);
                $from = $searchData[0] === '*' ? '*' : date_format(date_create($searchData[0]), 'Y-m-d');
                $to = $searchData[1] === '*' ? '*' : date_format(date_create($searchData[1]), 'Y-m-d');
                $particulars = $searchData[2] === '*' ? '' : $searchData[2];
                $searchTag = trim($searchData[3]);

                if ($from !== '*' && $to !== '*') {
                    $officialReceipts = $officialReceipts
                        ->whereBetween('receipt_date', [$from, $to]);
                }

                if ($particulars) {
                    $officialReceipts = $officialReceipts
                        ->whereRelation('natureCollection', 'id', $particulars);
                }

                if ($searchTag) {
                    $officialReceipts = $officialReceipts
                        ->where(function($query) use ($searchTag) {
                            $query->where('receipt_date', 'LIKE', "%$searchTag%")
                                ->orWhere('deposited_date', 'LIKE', "%$searchTag%")
                                ->orWhere('cancelled_date', 'LIKE', "%$searchTag%")
                                ->orWhere('or_no', 'LIKE', "%$searchTag%")
                                ->orWhere('amount', 'LIKE', "%$searchTag%")
                                ->orWhere('deposit', 'LIKE', "%$searchTag%")
                                ->orWhere('amount_words', 'LIKE', "%$searchTag%")
                                ->orWhere('card_no', 'LIKE', "%$searchTag%")
                                ->orWhere('payment_mode', 'LIKE', "%$searchTag%")
                                ->orWhere('drawee_bank', 'LIKE', "%$searchTag%")
                                ->orWhere('check_no', 'LIKE', "%$searchTag%")
                                ->orWhere('check_date', 'LIKE', "%$searchTag%")
                                ->orWhereRelation('discount', 'discount_name', 'LIKE', "%$searchTag%")
                                ->orWhereRelation('natureCollection', 'particular_name', 'LIKE', "%$searchTag%")
                                ->orWhereRelation('payor', 'payor_name', 'LIKE', "%$searchTag%")
                                ->orWhereRelation('cancelledBy', function($query) use ($searchTag) {
                                    $query->where('first_name', 'LIKE', "%$searchTag%")
                                        ->orWhere('last_name', 'LIKE', "%$searchTag%");
                                })
                                ->orWhereRelation('depositedBy', function($query) use ($searchTag) {
                                    $query->where('first_name', 'LIKE', "%$searchTag%")
                                        ->orWhere('last_name', 'LIKE', "%$searchTag%");
                                })
                                ->orWhereRelation('accountablePersonnel', function($query) use ($searchTag) {
                                    $query->where('first_name', 'LIKE', "%$searchTag%")
                                        ->orWhere('last_name', 'LIKE', "%$searchTag%");
                                });
                        });
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

        try {
            // Create a new payor if not exists and get the id
            $payor = Payor::where('id', $request->payor_id)
                        ->orWhere('payor_name', $request->payor_id)
                        ->first();
            if (!$payor) {
                $payor = Payor::create([
                    'payor_name' => $request->payor_id,
                ]);
            }

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
                'drawee_bank' => $request->drawee_bank ?? null,
                'check_no' => $request->check_no ?? null,
                'check_date' =>
                    $request->check_date ? date('Y-m-d', strtotime($request->check_date)) : null
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
    public function update(UpdateOfficialReceiptRequest $request, OfficialReceipt $officialReceipt)
    {
        // Validate the request
        $request->validated();

        try {
            // Create a new payor if not exists and get the id
            $payor = Payor::where('id', $request->payor_id)
                        ->orWhere('payor_name', $request->payor_id)
                        ->first();
            if (!$payor) {
                $payor = Payor::create([
                    'payor_name' => $request->payor_id,
                ]);
            }

            $data = [
                'receipt_date' => date('Y-m-d', strtotime($request->receipt_date)),
                'or_no' => $request->or_no,
                'payor_id' => $payor->id,
                'nature_collection_id' => $request->nature_collection_id,
                'amount' => $request->amount,
                'discount_id' => $request->discount_id,
                'card_no' => $request->card_no,
                'amount_words' => $request->amount_words,
                'payment_mode' => $request->payment_mode,
                'drawee_bank' => $request->drawee_bank ?? null,
                'check_no' => $request->check_no ?? null,
                'check_date' =>
                    $request->check_date ? date('Y-m-d', strtotime($request->check_date)) : null
            ];

            if ($officialReceipt->deposited_date && $request->deposited_date) {
                $data = [...$data, 'deposited_date' => date('Y-m-d', strtotime($request->deposited_date))];
            }
            if ($officialReceipt->cancelled_date && $request->cancelled_date) {
                $data = [...$data, 'cancelled_date' => date('Y-m-d', strtotime($request->cancelled_date))];
            }

            // Update official receipt
            $officialReceipt->update($data);
        } catch (\Throwable $th) {
            return response()->json([
                'data' => [
                    'message' => 'Failed to update official receipt',
                    'error' => 1
                ]
            ], 422);
        }

        return response()->json([
            'data' => [
                'data' => $officialReceipt->load([
                    'natureCollection:id,particular_name',
                    'payor:id,payor_name',
                    'discount:id,discount_name,percent,requires_card_no',
                ]),
                'message' => 'Official receipt updated successfully',
                'success' => 1
            ]
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
                'deposited_date' => $request->deposited_date,
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
    public function cancel(Request $request, OfficialReceipt $officialReceipt)
    {
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
                'is_cancelled' => true,
                'cancelled_date' => now(),
                'cancelled_by_id' => $request->user()->id,
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

    /**
     * Clear the OR status from storage.
     */
    public function revertStatus(OfficialReceipt $officialReceipt)
    {
        if ($officialReceipt->is_cancelled || $officialReceipt->deposit) {
            try {
                // Update the official receipt
                // $officialReceipt->update([
                //     'deposited_by_id ' => null,
                //     'deposited_date' => null,
                //     'deposit' => null,
                //     'is_cancelled' => false,
                //     'cancelled_date' => null,
                //     'cancelled_by_id' => null,
                // ]);

                DB::table('official_receipts')
                    ->where('id', $officialReceipt->id)
                    ->update([
                        'deposited_by_id' => null,
                        'deposited_date' => null,
                        'deposit' => null,
                        'is_cancelled' => false,
                        'cancelled_date' => null,
                        'cancelled_by_id' => null,
                    ]);
            } catch (\Throwable $th) {
                return response()->json([
                    'data' => [
                        'message' => 'Unable to return to pending status for the official receipt',
                        'error' => 1
                    ]
                ], 422);
            }
        } else {
            $message = "";

            if (!$officialReceipt->deposit) {
                $message = "The official receipt has not been processed for deposit";
            }

            if (!$officialReceipt->is_cancelled) {
                $message = "The official receipt has not been processed for cancel";
            }

            return response()->json([
                'data' => [
                    'message' => $message,
                    'error' => 1
                ]
            ], 422);
        }

        return response()->json([
            'data' => [
                'data' => $officialReceipt,
                'message' => 'Official receipt has been reverted back to pending',
                'success' => 1
            ]
        ], 201);
    }

    public function checkDuplicate($orNo) {
        try {
            $orCount = OfficialReceipt::where('or_no', $orNo)->count();

            if ($orCount > 0) {
                return response()->json([
                    'data' => [
                        'has_duplicate' => '1',
                        'message' => 'Official receipt has duplicate',
                        'success' => 1
                    ]
                ], 201);
            }

            return response()->json([
                'data' => [
                    'has_duplicate' => '0',
                    'message' => 'Official receipt does not have duplicate',
                    'success' => 1
                ]
            ], 201);
        } catch (\Throwable $th) {
            return response()->json([
                'data' => [
                    'message' => 'Failed to check duplicate.',
                    'error' => 1
                ]
            ], 422);
        }
    }
}

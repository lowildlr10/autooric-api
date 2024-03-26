<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Http\Requests\StoreAccountRequest;
use App\Http\Requests\UpdateAccountRequest;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Get all the accounts
        $accounts = Account::orderBy('account_name')->get();

        return response()->json([
            'data' => $accounts
        ]);
    }

    /**
     * Display a listing of the resource.
     */
    public function indexPaginated(Request $request)
    {
        $search = trim($request->search) ?? '';

        // Get all the accounts
        $accounts = Account::orderBy('account_name');

        if ($search) {
            $accounts = $accounts->where('account_name', 'LIKE', "%$search%")
                ->orWhere('account_number', 'LIKE', "%$search%");
        }

        $accounts = $accounts
            ->paginate(50);

        return response()->json([
            'data' => $accounts
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreAccountRequest $request)
    {
        // Validate the request
        $request->validated();

        try {
            // Create a new account
            $account = Account::create([
                'account_name' => $request->account_name,
                'account_number' => $request->account_number
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'data' => [
                    'message' => 'Failed to create an account',
                    'error' => 1
                ]
            ], 422);
        }

        return response()->json([
            'data' => [
                'data' => $request->all(),
                'message' => 'Account created successfully',
                'success' => 1
            ]
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Account $account)
    {
        // Return a json response of the account
        return response()->json([
            'data' => $account,
            'success' => 1
        ], 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateAccountRequest $request, Account $account)
    {
        // Validate the request
        $request->validated();

        try {
            // Create a new account
            $account->update([
                'account_name' => $request->account_name,
                'account_number' => $request->account_number
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'data' => [
                    'message' => 'Failed to update account',
                    'error' => 1
                ]
            ], 422);
        }

        return response()->json([
            'data' => [
                'data' => $request->all(),
                'message' => 'Account updated successfully',
                'success' => 1
            ]
        ], 201);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Account $account)
    {
        try {
            $account->delete();
        } catch (\Throwable $th) {
            return response()->json([
                'data' => [
                    'message' =>
                        $th->getCode() === '23000' ?
                            'Failed to delete account. There are records connected to this record.' :
                            'Unknown error occured',
                    'error' => 1
                ]
            ], 422);
        }

        return response()->json([
            'data' => [
                'message' => 'Account deleted successfully',
                'success' => 1
            ]
        ], 201);
    }
}

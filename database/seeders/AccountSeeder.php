<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $accounts = [
            [
                'account_name' => 'PNP Neuro Psychiatric Hospital Fund',
                'account_number' => '1862-1001-89'
            ],
            [
                'account_name' => 'PNP CLS Fund',
                'account_number' => '1862-1011-69'
            ],
            [
                'account_name' => 'PNP Trust Receipts',
                'account_number' => '1862-100-162'
            ],
            [
                'account_name' => 'PROCOR Housimg Board Fund',
                'account_number' => '1372-0080-20'
            ],
            [
                'account_name' => 'PROCOR BAC',
                'account_number' => '1372-0094-26'
            ],
            [
                'account_name' => 'Bureau of Treasury',
                'account_number' => '3402-2844-02'
            ],
            [
                'account_name' => 'PNP Support Fund',
                'account_number' => '1862-1017-54'
            ],
            [
                'account_name' => 'PRO COR',
                'account_number' => '1862-1017-54'
            ],
            [
                'account_name' => 'PhilHealth',
                'account_number' => ''
            ],
        ];

        foreach ($accounts as $account) {
            \App\Models\Account::create([
                'account_name' => $account['account_name'],
                'account_number' => $account['account_number'],
            ]);
        }
    }
}

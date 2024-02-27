<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ParticularSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all categories based on the category name arrays
        $categories = \App\Models\Category::whereIn('category_name', [
            'Trust Receipts',
            'General Fund',
            'Trust Liabilities',
            'Others'
        ])->get();

        // Create a new particular
        \App\Models\Particular::create([
            'particular_name' => 'NP Validation',
            'category_id' => $categories->where('category_name', 'Trust Receipts')->first()->id,
            'order_no' => \App\Models\Particular::where('category_id',
                $categories->where('category_name', 'Trust Receipts')->first()->id
            )->count()
        ]);
        \App\Models\Particular::create([
            'particular_name' => 'PNP/DI Clearance',
            'category_id' => $categories->where('category_name', 'Trust Receipts')->first()->id,
            'order_no' => \App\Models\Particular::where('category_id',
                $categories->where('category_name', 'Trust Receipts')->first()->id
            )->count()
        ]);
        \App\Models\Particular::create([
            'particular_name' => 'Decal',
            'category_id' => $categories->where('category_name', 'Trust Receipts')->first()->id,
            'order_no' => \App\Models\Particular::where('category_id',
                $categories->where('category_name', 'Trust Receipts')->first()->id
            )->count()
        ]);
        \App\Models\Particular::create([
            'particular_name' => 'PNP Tirelock',
            'category_id' => $categories->where('category_name', 'Trust Receipts')->first()->id,
            'order_no' => \App\Models\Particular::where('category_id',
                $categories->where('category_name', 'Trust Receipts')->first()->id
            )->count()
        ]);
        \App\Models\Particular::create([
            'particular_name' => 'Neoro-Psychiatric Examination',
            'category_id' => $categories->where('category_name', 'Trust Receipts')->first()->id,
            'order_no' => \App\Models\Particular::where('category_id',
                $categories->where('category_name', 'Trust Receipts')->first()->id
            )->count()
        ]);
        \App\Models\Particular::create([
            'particular_name' => 'Drug Test',
            'default_amount' => 900,
            'category_id' => $categories->where('category_name', 'Trust Receipts')->first()->id,
            'order_no' => \App\Models\Particular::where('category_id',
                $categories->where('category_name', 'Trust Receipts')->first()->id
            )->count()
        ]);

        \App\Models\Particular::create([
            'particular_name' => 'Forfeiture of Pay and Allowances',
            'category_id' => $categories->where('category_name', 'General Fund')->first()->id,
            'order_no' => \App\Models\Particular::where('category_id',
                $categories->where('category_name', 'General Fund')->first()->id
            )->count()
        ]);
        \App\Models\Particular::create([
            'particular_name' => 'Suspension/Overpayment',
            'category_id' => $categories->where('category_name', 'General Fund')->first()->id,
            'order_no' => \App\Models\Particular::where('category_id',
                $categories->where('category_name', 'General Fund')->first()->id
            )->count()
        ]);
        \App\Models\Particular::create([
            'particular_name' => 'Demotion in Rank',
            'category_id' => $categories->where('category_name', 'General Fund')->first()->id,
            'order_no' => \App\Models\Particular::where('category_id',
                $categories->where('category_name', 'General Fund')->first()->id
            )->count()
        ]);
        \App\Models\Particular::create([
            'particular_name' => 'COA Disallowances',
            'category_id' => $categories->where('category_name', 'General Fund')->first()->id,
            'order_no' => \App\Models\Particular::where('category_id',
                $categories->where('category_name', 'General Fund')->first()->id
            )->count()
        ]);
        \App\Models\Particular::create([
            'particular_name' => 'Firearms Accoutability',
            'category_id' => $categories->where('category_name', 'General Fund')->first()->id,
            'order_no' => \App\Models\Particular::where('category_id',
                $categories->where('category_name', 'General Fund')->first()->id
            )->count()
        ]);
        \App\Models\Particular::create([
            'particular_name' => 'Refund of SALARY OVERPAYMENT',
            'category_id' => $categories->where('category_name', 'General Fund')->first()->id,
            'order_no' => \App\Models\Particular::where('category_id',
                $categories->where('category_name', 'General Fund')->first()->id
            )->count()
        ]);
        \App\Models\Particular::create([
            'particular_name' => 'Refund of Cash Advance',
            'category_id' => $categories->where('category_name', 'General Fund')->first()->id,
            'order_no' => \App\Models\Particular::where('category_id',
                $categories->where('category_name', 'General Fund')->first()->id
            )->count()
        ]);

        \App\Models\Particular::create([
            'particular_name' => 'Donation for PNP HELP and FOOD BANK',
            'category_id' => $categories->where('category_name', 'Trust Liabilities')->first()->id,
            'order_no' => \App\Models\Particular::where('category_id',
                $categories->where('category_name', 'Trust Liabilities')->first()->id
            )->count()
        ]);
        \App\Models\Particular::create([
            'particular_name' => 'Konsulta/Professional Fee for RDMU',
            'category_id' => $categories->where('category_name', 'Trust Liabilities')->first()->id,
            'order_no' => \App\Models\Particular::where('category_id',
                $categories->where('category_name', 'Trust Liabilities')->first()->id
            )->count()
        ]);

        \App\Models\Particular::create([
            'particular_name' => 'PNP Housing Board',
            'category_id' => $categories->where('category_name', 'Others')->first()->id,
            'order_no' => \App\Models\Particular::where('category_id',
                $categories->where('category_name', 'Others')->first()->id
            )->count()
        ]);
        \App\Models\Particular::create([
            'particular_name' => 'Bidding Documents',
            'category_id' => $categories->where('category_name', 'Others')->first()->id,
            'order_no' => \App\Models\Particular::where('category_id',
                $categories->where('category_name', 'Others')->first()->id
            )->count()
        ]);
    }
}

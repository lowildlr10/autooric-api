<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create a new category: "Trust Receipts", "General Fund", "Trust Liabilities", "Others"
        $categories = [
            'Trust Receipts',
            'General Fund',
            'Trust Liabilities',
            'Others'
        ];

        foreach ($categories as $key => $category) {
            \App\Models\Category::create([
                'category_name' => $category,
                'order_no' => $key + 1,
            ]);
        }
    }
}

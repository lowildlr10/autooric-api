<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PositionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create the default positions
        \App\Models\Position::create([
            'position_name' => 'System Administrator',
        ]);

        \App\Models\Position::create([
            'position_name' => 'NUP',
        ]);

        \App\Models\Position::create([
            'position_name' => 'PMAJ',
        ]);

        \App\Models\Position::create([
            'position_name' => 'PCOL',
        ]);
    }
}

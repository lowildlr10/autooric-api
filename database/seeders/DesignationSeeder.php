<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DesignationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create the default designations
        \App\Models\Designation::create([
            'designation_name' => 'System Administrator',
        ]);

        \App\Models\Designation::create([
            'designation_name' => 'Collecting Clerk',
        ]);

        \App\Models\Designation::create([
            'designation_name' => 'Collection Supervisor',
        ]);

        \App\Models\Designation::create([
            'designation_name' => 'Police Major',
        ]);

        \App\Models\Designation::create([
            'designation_name' => 'Chief',
        ]);
    }
}

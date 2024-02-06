<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get position, designation, and station for system administrator
        $position = \App\Models\Position::where('position_name', 'System Administrator')->first();
        $designation = \App\Models\Designation::where('designation_name', 'System Administrator')->first();
        $station = \App\Models\Station::where('station_name', 'RFSO 15')->first();

        // Create the default admin user
        \App\Models\User::create([
            'first_name' => 'System',
            'last_name' => 'Administrator',
            'email' => 'rfso15procor@gmail.com',
            'position_id' => $position->id,
            'designation_id' => $designation->id,
            'station_id' => $station->id,
            'username' => 'admin',
            'password' => bcrypt('pwd12345'),
            'role' => 'admin',
        ]);
    }
}

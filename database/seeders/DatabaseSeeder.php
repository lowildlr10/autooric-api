<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(PositionSeeder::class);
        $this->call(DesignationSeeder::class);
        $this->call(StationSeeder::class);
        $this->call(UserSeeder::class);
        $this->call(PaperSizeSeeder::class);
        $this->call(CategorySeeder::class);
        $this->call(ParticularSeeder::class);
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PaperSizeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create the default paper sizes
        \App\Models\PaperSize::create([
            'paper_name' => 'Official Receipt',
            'width' => '4.09449',
            'height' => '8.0315',
        ]);

        \App\Models\PaperSize::create([
            'paper_name' => 'A4',
            'width' => '8.3',
            'height' => '11.7',
        ]);

        \App\Models\PaperSize::create([
            'paper_name' => 'Letter',
            'width' => '8.5',
            'height' => '11',
        ]);

        \App\Models\PaperSize::create([
            'paper_name' => 'Long',
            'width' => '8.5',
            'height' => '13',
        ]);
    }
}

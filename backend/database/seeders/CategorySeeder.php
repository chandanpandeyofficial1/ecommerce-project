<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    // Creates the grocery categories.
    public function run(): void
    {
        foreach (['Rice', 'Dal', 'Pulses', 'Flour', 'Spices'] as $name) {
            Category::firstOrCreate(['name' => $name]);
        }
    }
}

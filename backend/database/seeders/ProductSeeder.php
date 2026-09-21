<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    // Creates a few sample products for each category.
    public function run(): void
    {
        // Each row is: name, price, stock, unit, description.
        $products = [
            'Rice' => [
                ['Basmati Rice', 120.00, 100, '1 kg', 'Long grain aged basmati rice.'],
                ['Sona Masoori Rice', 65.00, 150, '1 kg', 'Light rice for daily meals.'],
                ['Brown Rice', 90.00, 60, '1 kg', 'Unpolished whole grain rice.'],
            ],
            'Dal' => [
                ['Toor Dal', 145.00, 80, '1 kg', 'Split pigeon peas.'],
                ['Moong Dal', 130.00, 70, '1 kg', 'Yellow split moong.'],
                ['Masoor Dal', 105.00, 90, '1 kg', 'Red split lentils.'],
            ],
            'Pulses' => [
                ['Kabuli Chana', 110.00, 75, '1 kg', 'White chickpeas.'],
                ['Rajma', 140.00, 55, '1 kg', 'Red kidney beans.'],
                ['Green Moong', 120.00, 65, '1 kg', 'Whole green gram.'],
            ],
            'Flour' => [
                ['Whole Wheat Atta', 55.00, 200, '1 kg', 'Stone ground chakki atta.'],
                ['Besan', 95.00, 80, '500 g', 'Fine gram flour.'],
                ['Maida', 45.00, 90, '1 kg', 'Refined wheat flour.'],
            ],
            'Spices' => [
                ['Turmeric Powder', 40.00, 120, '100 g', 'Pure ground haldi.'],
                ['Red Chilli Powder', 55.00, 110, '100 g', 'Hot red chilli powder.'],
                ['Garam Masala', 70.00, 100, '100 g', 'Blended whole spice mix.'],
            ],
        ];

        foreach ($products as $categoryName => $rows) {
            $category = Category::firstOrCreate(['name' => $categoryName]);

            foreach ($rows as [$name, $price, $stock, $unit, $description]) {
                Product::firstOrCreate(
                    ['category_id' => $category->id, 'name' => $name],
                    [
                        'description' => $description,
                        'price' => $price,
                        'stock' => $stock,
                        'unit' => $unit,
                    ]
                );
            }
        }
    }
}

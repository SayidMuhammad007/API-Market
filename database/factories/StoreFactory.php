<?php

namespace Database\Factories;

use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Store>
 */
class StoreFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $branchIds = DB::table('branches')->pluck('id');
        $categoryIds = DB::table('categories')->pluck('id');
        $priceIds = DB::table('prices')->pluck('id');

        return [
            'name' => $this->faker->name(),
            'made_in' => $this->faker->country(),
            'barcode' => $this->faker->numberBetween(55555, 99999),
            'price_come' => $this->faker->numberBetween(10000, 100000),
            'price_sell' => $this->faker->numberBetween(10000, 100000),
            'price_wholesale' => $this->faker->numberBetween(10000, 100000),
            'quantity' => $this->faker->numberBetween(1000, 10000),
            'danger_count' => $this->faker->numberBetween(1000, 10000),
            'branch_id' => $this->faker->randomElement($branchIds),
            'category_id' => $this->faker->randomElement($categoryIds),
            'price_id' => $this->faker->randomElement($priceIds),
        ];
    }
    public function configure(): StoreFactory
    {
        return $this->afterCreating(function (Store $product) {
            $imageUrl = $this->faker->imageUrl(800,800, null, false);
            $product->addMediaFromUrl($imageUrl)->toMediaCollection('images');
        });
    }
}

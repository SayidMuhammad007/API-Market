<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        \App\Models\Branch::factory(2)->create();
        \App\Models\Price::factory(2)->create();
        // \App\Models\Category::factory(2)->create();
        // \App\Models\Company::factory(2)->create();
        // \App\Models\Type::factory(2)->create();
        // \App\Models\Customer::factory(2)->create();
        // \App\Models\Store::factory(2)->create();

        \App\Models\User::factory()->create([
            'name' => 'admin',
            'password' => Hash::make('secret'),
            'phone' => '+998907758032',
            'branch_id' => 1,
        ]);
    }
}

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
        // \App\Models\User::factory(10)->create();

        \App\Models\User::factory()->create([
            'name' => 'admin',
            'password' => '$2y$12$y4Si4iZsr/k1Tpyi9Jsv3unwjj0HM7ds.ObQfIBD4ZSYRMpwpiOYq', //secret
            'phone' => '+998907758032'
        ]);
    }
}

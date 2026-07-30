<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Lumina\Ratings\Database\Seeders\RatingSeeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            \Lumina\Cms\Database\Seeders\DatabaseSeeder::class,
            CustomerSeeder::class,
            EcommerceSeeder::class,
            PostSeeder::class,
            CouponSeeder::class,
            // \Lumina\Ecommerce\Database\Seeders\OrderSeeder::class,
            RatingSeeder::class,
        ]);
    }
}

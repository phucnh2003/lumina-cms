<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Lumina\Coupon\Models\Coupon;
use Lumina\Customer\Models\CustomerGroup;

final class CouponSeeder extends Seeder
{
    public function run(): void
    {
        // Query VIP customer group ID dynamically
        $vipGroupId = CustomerGroup::where('slug', 'khach-hang-vip')->value('id');

        // Dynamic categories query if available (fallback to empty)
        $fashionCategoryIds = [];
        $categoryClass = 'Lumina\\Ecommerce\\Models\\Category';
        if (class_exists($categoryClass)) {
            $fashionCategoryIds = $categoryClass::whereIn('slug', ['thoi-trang-nam', 'thoi-trang-nu'])
                ->pluck('id')
                ->toArray();
        }

        // 1. Summer public coupon
        Coupon::create([
            'code' => 'SUMMER2026',
            'type' => 'fixed',
            'value' => 50000,
            'min_spend' => 200000,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addMonths(3),
            'is_active' => true,
            'visibility' => 'public',
            'rules' => [
                'is_guest_allowed' => true,
            ],
        ]);

        // 2. VIP only private coupon
        Coupon::create([
            'code' => 'VIPONLY',
            'type' => 'percentage',
            'value' => 20,
            'max_discount' => 100000,
            'min_spend' => 500000,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addMonths(6),
            'is_active' => true,
            'visibility' => 'private',
            'rules' => [
                'is_guest_allowed' => false,
                'customer_groups' => $vipGroupId ? [$vipGroupId] : [],
            ],
        ]);

        // 3. New user registered in 2026 coupon
        Coupon::create([
            'code' => 'NEWUSER2026',
            'type' => 'fixed',
            'value' => 30000,
            'min_spend' => 100000,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addMonths(2),
            'is_active' => true,
            'visibility' => 'public',
            'rules' => [
                'is_guest_allowed' => false,
                'user_registered_after' => '2026-01-01',
            ],
        ]);

        // 4. Fashion category coupon
        Coupon::create([
            'code' => 'FASHION50',
            'type' => 'percentage',
            'value' => 50,
            'max_discount' => 150000,
            'min_spend' => 300000,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addMonths(1),
            'is_active' => true,
            'visibility' => 'public',
            'rules' => [
                'is_guest_allowed' => true,
                'applicable_categories' => $fashionCategoryIds,
            ],
        ]);
    }
}

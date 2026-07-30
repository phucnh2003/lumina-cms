<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Lumina\Customer\Models\CustomerGroup;

final class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create Customer Groups (using json_encode for translatable 'name' field)
        $vipGroup = CustomerGroup::create([
            'name' => json_encode(['vi' => 'Khách hàng VIP', 'en' => 'VIP Customers']),
            'slug' => 'khach-hang-vip',
            'type' => 'customer_group',
        ]);

        $wholesaleGroup = CustomerGroup::create([
            'name' => json_encode(['vi' => 'Khách hàng Bán sỉ', 'en' => 'Wholesale Customers']),
            'slug' => 'khach-hang-ban-si',
            'type' => 'customer_group',
        ]);

        $retailGroup = CustomerGroup::create([
            'name' => json_encode(['vi' => 'Khách hàng Bán lẻ', 'en' => 'Retail Customers']),
            'slug' => 'khach-hang-ban-le',
            'type' => 'customer_group',
        ]);

        // 2. Create Users/Customers
        $customers = [
            [
                'name' => 'Nguyễn Văn A',
                'email' => 'nguyenvana@example.com',
                'password' => Hash::make('password123'),
            ],
            [
                'name' => 'Trần Thị B',
                'email' => 'tranthib@example.com',
                'password' => Hash::make('password123'),
            ],
            [
                'name' => 'Lê Văn C',
                'email' => 'levanc@example.com',
                'password' => Hash::make('password123'),
            ],
            [
                'name' => 'Phạm Minh D',
                'email' => 'phamminhd@example.com',
                'password' => Hash::make('password123'),
            ],
            [
                'name' => 'Hoàng Thanh E',
                'email' => 'hoangthanhe@example.com',
                'password' => Hash::make('password123'),
            ],
        ];

        foreach ($customers as $index => $customerData) {
            $user = User::create($customerData);

            // Assign groups to users
            if ($index === 0) {
                // User A is VIP
                $user->taxonomies()->attach($vipGroup->id);
            } elseif ($index === 1 || $index === 2) {
                // User B, C are Wholesale
                $user->taxonomies()->attach($wholesaleGroup->id);
            } else {
                // User D, E are Retail
                $user->taxonomies()->attach($retailGroup->id);
            }

            // Seed a domestic address for each user
            $user->addresses()->create([
                'type' => 'domestic',
                'receiver_name' => $user->name,
                'receiver_phone' => '090123456'.$index,
                'detail_address' => 'Số '.($index + 1).' Đường Lê Lợi, Phường Bến Thành, Quận 1',
                'is_default' => true,
            ]);

            // Seed an international address for VIP and Wholesale customers
            if ($index < 3) {
                $user->addresses()->create([
                    'type' => 'international',
                    'receiver_name' => $user->name,
                    'receiver_phone' => '090765432'.$index,
                    'detail_address' => '123 Fake Street, Suite '.($index + 1),
                    'city' => 'Singapore',
                    'country_code' => 'SG',
                    'is_default' => false,
                ]);
            }
        }
    }
}

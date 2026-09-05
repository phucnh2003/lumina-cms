<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Lumina\Ecommerce\Models\Option;
use Lumina\Ecommerce\Models\Order;
use Lumina\Ecommerce\Models\Product;
use Lumina\Payment\Models\Transaction;
use Lumina\Taxonomies\Models\ProductCategory as Category;

class EcommerceSeeder extends Seeder
{
    public function run(): void
    {
        $categoriesData = [
            [
                'name' => ['vi' => 'Thời trang Nam', 'en' => 'Men\'s Fashion'],
                'slug' => 'thoi-trang-nam',
                'status' => 'active',
                'children' => [
                    [
                        'name' => ['vi' => 'Áo thun Nam', 'en' => 'Men\'s T-Shirts'],
                        'slug' => 'ao-thun-nam',
                        'status' => 'active',
                    ],
                    [
                        'name' => ['vi' => 'Quần Jeans Nam', 'en' => 'Men\'s Jeans'],
                        'slug' => 'quan-jeans-nam',
                        'status' => 'active',
                    ],
                    [
                        'name' => ['vi' => 'Áo khoác Nam', 'en' => 'Men\'s Jackets'],
                        'slug' => 'ao-khoac-nam',
                        'status' => 'active',
                    ],
                ],
            ],
            [
                'name' => ['vi' => 'Thời trang Nữ', 'en' => 'Women\'s Fashion'],
                'slug' => 'thoi-trang-nu',
                'status' => 'active',
                'children' => [
                    [
                        'name' => ['vi' => 'Váy & Đầm', 'en' => 'Dresses'],
                        'slug' => 'vay-va-dam',
                        'status' => 'active',
                    ],
                    [
                        'name' => ['vi' => 'Áo sơ mi Nữ', 'en' => 'Women\'s Blouses'],
                        'slug' => 'ao-so-mi-nu',
                        'status' => 'active',
                    ],
                    [
                        'name' => ['vi' => 'Túi xách Nữ', 'en' => 'Women\'s Handbags'],
                        'slug' => 'tui-xach-nu',
                        'status' => 'active',
                    ],
                ],
            ],
            [
                'name' => ['vi' => 'Phụ kiện thời trang', 'en' => 'Fashion Accessories'],
                'slug' => 'phu-kien-thoi-trang',
                'status' => 'active',
                'children' => [
                    [
                        'name' => ['vi' => 'Giày & Sandal', 'en' => 'Shoes & Sandals'],
                        'slug' => 'giay-va-sandal',
                        'status' => 'active',
                    ],
                    [
                        'name' => ['vi' => 'Đồng hồ cao cấp', 'en' => 'Premium Watches'],
                        'slug' => 'dong-ho-cao-cap',
                        'status' => 'active',
                    ],
                ],
            ],
        ];

        // Seed Categories
        $createdCategories = [];
        foreach ($categoriesData as $cat) {
            $parent = Category::create([
                'name' => json_encode($cat['name']),
                'slug' => $cat['slug'],
                'status' => $cat['status'],
            ]);
            $createdCategories[$cat['slug']] = $parent;

            if (isset($cat['children'])) {
                foreach ($cat['children'] as $child) {
                    $childCat = Category::create([
                        'name' => json_encode($child['name']),
                        'slug' => $child['slug'],
                        'status' => $child['status'],
                        'parent_id' => $parent->id,
                    ]);
                    $createdCategories[$child['slug']] = $childCat;
                }
            }
        }

        // Seed Options (Attributes) & their values — values are just `options` rows with `parent_id` set.
        $sizeOption = Option::create([
            'name' => 'Kích thước',
            'slug' => 'kich-thuoc',
        ]);
        $valM = $sizeOption->children()->create(['name' => 'M', 'slug' => 'm']);
        $valL = $sizeOption->children()->create(['name' => 'L', 'slug' => 'l']);

        $colorOption = Option::create([
            'name' => 'Màu sắc',
            'slug' => 'mau-sac',
        ]);
        $valBlack = $colorOption->children()->create(['name' => 'Đen', 'slug' => 'den']);
        $valBrown = $colorOption->children()->create(['name' => 'Nâu', 'slug' => 'nau']);

        // Seed 20 Fashion Products with images
        $productsData = [
            [
                'name' => ['vi' => 'Áo thun cotton Basic', 'en' => 'Basic Cotton T-Shirt'],
                'slug' => 'ao-thun-cotton-basic',
                'price' => 150000,
                'stock' => 100,
                'status' => 'active',
                'category' => 'ao-thun-nam',
                'image' => 'https://images.unsplash.com/photo-1521572267360-ee0c2909d518?w=800&auto=format&fit=crop&q=80',
            ],
            [
                'name' => ['vi' => 'Quần Jeans Slimfit Nam', 'en' => 'Slimfit Men\'s Jeans'],
                'slug' => 'quan-jeans-slimfit-nam',
                'price' => 450000,
                'stock' => 50,
                'status' => 'active',
                'category' => 'quan-jeans-nam',
                'image' => 'https://images.unsplash.com/photo-1542272604-780c36856d61?w=800&auto=format&fit=crop&q=80',
            ],
            [
                'name' => ['vi' => 'Đầm voan hoa mùa hè', 'en' => 'Summer Floral Chiffon Dress'],
                'slug' => 'dam-voan-hoa-mua-he',
                'price' => 520000,
                'stock' => 30,
                'status' => 'active',
                'category' => 'vay-va-dam',
                'image' => 'https://images.unsplash.com/photo-1572804013309-59a88b7e92f1?w=800&auto=format&fit=crop&q=80',
            ],
            [
                'name' => ['vi' => 'Áo sơ mi lụa công sở', 'en' => 'Office Silk Blouse'],
                'slug' => 'ao-so-mi-lua-cong-so',
                'price' => 380000,
                'stock' => 40,
                'status' => 'active',
                'category' => 'ao-so-mi-nu',
                'image' => 'https://images.unsplash.com/photo-1602810318383-e386cc2a3ccf?w=800&auto=format&fit=crop&q=80',
            ],
            [
                'name' => ['vi' => 'Giày Sneaker thể thao', 'en' => 'Sporty Sneaker'],
                'slug' => 'giay-sneaker-the-thao',
                'price' => 890000,
                'stock' => 20,
                'status' => 'active',
                'category' => 'giay-va-sandal',
                'image' => 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=800&auto=format&fit=crop&q=80',
            ],
            [
                'name' => ['vi' => 'Đồng hồ nam dây da cổ điển', 'en' => 'Classic Leather Men\'s Watch'],
                'slug' => 'dong-ho-nam-day-da-co-dien',
                'price' => 2500000,
                'stock' => 15,
                'status' => 'active',
                'category' => 'dong-ho-cao-cap',
                'image' => 'https://images.unsplash.com/photo-1524805444758-089113d48a6d?w=800&auto=format&fit=crop&q=80',
            ],
            [
                'name' => ['vi' => 'Áo khoác Blazer nữ thời thượng', 'en' => 'Trendy Women\'s Blazer'],
                'slug' => 'ao-khoac-blazer-nu-thoi-thuong',
                'price' => 650000,
                'stock' => 25,
                'status' => 'active',
                'category' => 'ao-so-mi-nu',
                'image' => 'https://images.unsplash.com/photo-1591047139829-d91aecb6caea?w=800&auto=format&fit=crop&q=80',
            ],
            [
                'name' => ['vi' => 'Quần short Kaki nam dạo phố', 'en' => 'Men\'s Streetwear Khaki Shorts'],
                'slug' => 'quan-short-kaki-nam-dao-pho',
                'price' => 220000,
                'stock' => 60,
                'status' => 'active',
                'category' => 'ao-thun-nam',
                'image' => 'https://images.unsplash.com/photo-1591195853828-11db59a44f6b?w=800&auto=format&fit=crop&q=80',
            ],
            [
                'name' => ['vi' => 'Áo Hoodie nỉ form rộng unisex', 'en' => 'Unisex Oversized Fleece Hoodie'],
                'slug' => 'ao-hoodie-ni-form-rong-unisex',
                'price' => 350000,
                'stock' => 45,
                'status' => 'active',
                'category' => 'ao-thun-nam',
                'image' => 'https://images.unsplash.com/photo-1556905055-8f358a7a47b2?w=800&auto=format&fit=crop&q=80',
            ],
            [
                'name' => ['vi' => 'Váy Midi xếp ly dáng dài nữ', 'en' => 'Women\'s Long Pleated Midi Skirt'],
                'slug' => 'vay-midi-xep-ly-dang-dai-nu',
                'price' => 290000,
                'stock' => 35,
                'status' => 'active',
                'category' => 'vay-va-dam',
                'image' => 'https://images.unsplash.com/photo-1583496661160-fb5886a0aaaa?w=800&auto=format&fit=crop&q=80',
            ],
            [
                'name' => ['vi' => 'Túi xách da đeo chéo sành điệu', 'en' => 'Trendy Leather Crossbody Bag'],
                'slug' => 'tui-xach-da-deo-cheo-sanh-dieu',
                'price' => 480000,
                'stock' => 18,
                'status' => 'active',
                'category' => 'tui-xach-nu',
                'image' => 'https://images.unsplash.com/photo-1590874103328-eac38a683ce7?w=800&auto=format&fit=crop&q=80',
            ],
            [
                'name' => ['vi' => 'Kính râm chống tia UV thời trang', 'en' => 'Fashion UV Protection Sunglasses'],
                'slug' => 'kinh-ram-chong-tia-uv-thoi-trang',
                'price' => 190000,
                'stock' => 80,
                'status' => 'active',
                'category' => 'phu-kien-thoi-trang',
                'image' => 'https://images.unsplash.com/photo-1511499767150-a48a237f0083?w=800&auto=format&fit=crop&q=80',
            ],
            [
                'name' => ['vi' => 'Thắt lưng da nam công sở', 'en' => 'Men\'s Office Leather Belt'],
                'slug' => 'that-lung-da-nam-cong-so',
                'price' => 280000,
                'stock' => 50,
                'status' => 'active',
                'category' => 'phu-kien-thoi-trang',
                'image' => 'https://images.unsplash.com/photo-1624222247344-550fb60583dc?w=800&auto=format&fit=crop&q=80',
            ],
            [
                'name' => ['vi' => 'Giày cao gót nữ thanh lịch', 'en' => 'Elegant Women\'s High Heels'],
                'slug' => 'giay-cao-got-nu-thanh-lich',
                'price' => 590000,
                'stock' => 22,
                'status' => 'active',
                'category' => 'giay-va-sandal',
                'image' => 'https://images.unsplash.com/photo-1543163521-1bf539c55dd2?w=800&auto=format&fit=crop&q=80',
            ],
            [
                'name' => ['vi' => 'Áo len cổ lọ nam ấm áp', 'en' => 'Warm Men\'s Turtleneck Sweater'],
                'slug' => 'ao-len-co-lo-nam-am-ap',
                'price' => 320000,
                'stock' => 30,
                'status' => 'active',
                'category' => 'ao-khoac-nam',
                'image' => 'https://images.unsplash.com/photo-1620799140408-edc6dcb6d633?w=800&auto=format&fit=crop&q=80',
            ],
            [
                'name' => ['vi' => 'Áo khoác phao dáng ngắn', 'en' => 'Short Puffer Jacket'],
                'slug' => 'ao-khoac-phao-dang-ngan',
                'price' => 750000,
                'stock' => 15,
                'status' => 'active',
                'category' => 'ao-khoac-nam',
                'image' => 'https://images.unsplash.com/photo-1544441893-675973e31985?w=800&auto=format&fit=crop&q=80',
            ],
            [
                'name' => ['vi' => 'Bộ Pijama lụa satin cao cấp', 'en' => 'Premium Satin Silk Pijama Set'],
                'slug' => 'bo-pijama-lua-satin-cao-cap',
                'price' => 420000,
                'stock' => 28,
                'status' => 'active',
                'category' => 'vay-va-dam',
                'image' => 'https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?w=800&auto=format&fit=crop&q=80',
            ],
            [
                'name' => ['vi' => 'Mũ lưỡi trai thêu cá tính', 'en' => 'Cool Embroidered Baseball Cap'],
                'slug' => 'mu-luoi-trai-theu-ca-tinh',
                'price' => 120000,
                'stock' => 70,
                'status' => 'active',
                'category' => 'phu-kien-thoi-trang',
                'image' => 'https://images.unsplash.com/photo-1588850561407-ed78c282e89b?w=800&auto=format&fit=crop&q=80',
            ],
            [
                'name' => ['vi' => 'Quần Tây âu nam công sở', 'en' => 'Elegant Men\'s Office Trousers'],
                'slug' => 'quan-tay-au-nam-cong-so',
                'price' => 390000,
                'stock' => 40,
                'status' => 'active',
                'category' => 'quan-jeans-nam',
                'image' => 'https://images.unsplash.com/photo-1479064555552-3ef4979f8908?w=800&auto=format&fit=crop&q=80',
            ],
            [
                'name' => ['vi' => 'Túi tote vải canvas đơn giản', 'en' => 'Simple Canvas Tote Bag'],
                'slug' => 'tui-tote-vai-canvas-don-gian',
                'price' => 95000,
                'stock' => 120,
                'status' => 'active',
                'category' => 'tui-xach-nu',
                'image' => 'https://images.unsplash.com/photo-1544816155-12df9643f363?w=800&auto=format&fit=crop&q=80',
            ],
        ];

        $createdProducts = [];
        foreach ($productsData as $prod) {
            $product = Product::create([
                'name' => json_encode($prod['name']),
                'slug' => $prod['slug'],
                'price' => $prod['price'],
                'stock' => $prod['stock'],
                'status' => $prod['status'],
            ]);
            $createdProducts[] = $product;

            if (isset($createdCategories[$prod['category']])) {
                $product->categories()->attach($createdCategories[$prod['category']]->id);
            }

            // Create 2 variants for each product & associate Option Values and images
            $isWatch = $prod['category'] === 'dong-ho-cao-cap';

            $var1 = $product->variants()->create([
                'name' => $isWatch ? 'Dây Da Đen (Black Leather)' : 'Size M',
                'image' => ['url' => $prod['image']],
                'price' => $prod['price'],
                'sale_price' => $prod['price'] + 30000,
                'stock' => intval($prod['stock'] / 2),
                'is_default' => true,
                'has_options' => true,
            ]);
            $var1->options()->attach($isWatch ? $valBlack->id : $valM->id);

            $var2 = $product->variants()->create([
                'name' => $isWatch ? 'Dây Da Nâu (Brown Leather)' : 'Size L',
                'image' => ['url' => $prod['image']],
                'price' => $prod['price'] + 20000,
                'sale_price' => $prod['price'] + 50000,
                'stock' => intval($prod['stock'] / 2),
                'is_default' => false,
                'has_options' => true,
            ]);
            $var2->options()->attach($isWatch ? $valBrown->id : $valL->id);
        }

        // Seed sample orders & payment transactions based on seeded products
        $paymentStatuses = [Order::PAYMENT_STATUS_UNPAID, Order::PAYMENT_STATUS_PAID];
        $customerIds = User::pluck('id')->toArray();

        for ($i = 0; $i < 15; $i++) {
            $paymentStatus = fake()->randomElement($paymentStatuses);
            $orderStatus = $paymentStatus === Order::PAYMENT_STATUS_PAID
                ? fake()->randomElement([Order::STATUS_PROCESSING, Order::STATUS_COMPLETED])
                : Order::STATUS_PENDING;

            $order = Order::create([
                'customer_id' => ! empty($customerIds) ? fake()->randomElement($customerIds) : null,
                'shipping_address' => fake()->address(),
                'billing_address' => fake()->address(),
                'notes' => fake()->sentence(),
                'status' => $orderStatus,
                'payment_status' => $paymentStatus,
                'shipping_status' => $orderStatus === Order::STATUS_COMPLETED
                    ? Order::SHIPPING_STATUS_DELIVERED
                    : ($orderStatus === Order::STATUS_PROCESSING ? Order::SHIPPING_STATUS_SHIPPED : Order::SHIPPING_STATUS_PENDING),
                'payment_method' => fake()->randomElement(['cod', 'vnpay', 'stripe']),
                'shipping_method' => 'Standard',
                'currency' => 'VND',
                'shipping_fee' => fake()->randomElement([20000, 30000, 0]),
                'paid_at' => $paymentStatus === Order::PAYMENT_STATUS_PAID ? now()->subHours(fake()->numberBetween(1, 24)) : null,
            ]);

            // Add 1-2 random products to order
            $orderProducts = collect($createdProducts)->random(fake()->numberBetween(1, 2));
            $orderTotal = 0;

            foreach ($orderProducts as $prod) {
                $qty = fake()->numberBetween(1, 2);
                $lineTotal = $prod->price * $qty;
                $orderTotal += $lineTotal;

                $order->items()->create([
                    'orderable_id' => $prod->id,
                    'orderable_type' => Product::class,
                    'item_name' => json_decode($prod->getRawOriginal('name') ?? '', true)['vi'] ?? $prod->name,
                    'quantity' => $qty,
                    'unit_price' => $prod->price,
                    'line_total' => $lineTotal,
                ]);
            }

            // Create payment transaction
            Transaction::create([
                'order_id' => $order->id,
                'gateway' => $order->payment_method,
                'kind' => 'charge',
                'status' => $paymentStatus === Order::PAYMENT_STATUS_PAID ? 'success' : 'pending',
                'amount' => $orderTotal + $order->shipping_fee,
                'currency' => 'VND',
                'message' => $paymentStatus === Order::PAYMENT_STATUS_PAID ? 'Giao dịch thành công' : 'Chờ thanh toán',
                'transaction_number' => 'TXN'.now()->format('dmy').fake()->unique()->numberBetween(1000, 9999),
                'is_test' => true,
                'is_cod_gateway' => $order->payment_method === 'cod',
                'verified_at' => $paymentStatus === Order::PAYMENT_STATUS_PAID ? now() : null,
            ]);
        }
    }
}

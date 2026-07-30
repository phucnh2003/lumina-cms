<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Lumina\Posts\Models\Policy;
use Lumina\Posts\Models\Post;
use Lumina\Taxonomies\Models\PostCategory;

class PostSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Seed Post Categories
        $categories = [
            [
                'name' => ['vi' => 'Tin tức thời trang', 'en' => 'Fashion News'],
                'slug' => 'tin-tuc-thoi-trang',
                'status' => 'active',
            ],
            [
                'name' => ['vi' => 'Khuyến mãi & Sự kiện', 'en' => 'Promotions & Events'],
                'slug' => 'khuyen-mai-va-su-kien',
                'status' => 'active',
            ],
            [
                'name' => ['vi' => 'Hướng dẫn phối đồ', 'en' => 'Styling Guides'],
                'slug' => 'huong-dan-phoi-do',
                'status' => 'active',
            ],
        ];

        $createdCategories = [];
        foreach ($categories as $cat) {
            $createdCategories[] = PostCategory::create([
                'name' => json_encode($cat['name']),
                'slug' => $cat['slug'],
                'status' => $cat['status'],
            ]);
        }

        // 2. Seed Posts (type = blog)
        $posts = [
            [
                'title' => ['vi' => 'Xu hướng thời trang hè 2026', 'en' => 'Summer Fashion Trends 2026'],
                'slug' => 'xu-huong-thoi-trang-he-2026',
                'content' => ['vi' => '<p>Nội dung bài viết về xu hướng thời trang hè...</p>', 'en' => '<p>Content about summer fashion trends...</p>'],
                'status' => 'published',
                'category_index' => 0,
            ],
            [
                'title' => ['vi' => 'Cách chọn size quần áo cực chuẩn', 'en' => 'How to Choose Your Clothes Size Perfectly'],
                'slug' => 'cach-chon-size-quan-ao-cuc-chuan',
                'content' => ['vi' => '<p>Hướng dẫn chọn size quần áo cho mọi vóc dáng...</p>', 'en' => '<p>Guide to choose clothes size for all body types...</p>'],
                'status' => 'published',
                'category_index' => 2,
            ],
            [
                'title' => ['vi' => 'Khai trương chi nhánh mới - Nhận ngàn ưu đãi', 'en' => 'Grand Opening of New Branch - Get Tons of Deals'],
                'slug' => 'khai-truong-chi-nhanh-moi-nhan-ngan-uu-dai',
                'content' => ['vi' => '<p>Thông tin chương trình khuyến mãi khai trương...</p>', 'en' => '<p>Information about the grand opening promotions...</p>'],
                'status' => 'published',
                'category_index' => 1,
            ],
        ];

        foreach ($posts as $postData) {
            $post = Post::create([
                'title' => json_encode($postData['title']),
                'slug' => $postData['slug'],
                'content' => json_encode($postData['content']),
                'status' => $postData['status'],
            ]);

            // Attach to post category
            $cat = $createdCategories[$postData['category_index']];
            $post->categories()->attach($cat->id);
        }

        // 3. Seed Policies (type = policy)
        $policies = [
            [
                'title' => ['vi' => 'Chính sách bảo mật', 'en' => 'Privacy Policy'],
                'slug' => 'chinh-sach-bao-mat',
                'content' => ['vi' => '<p>Chúng tôi cam kết bảo mật thông tin khách hàng...</p>', 'en' => '<p>We commit to protect customer information...</p>'],
                'status' => 'published',
            ],
            [
                'title' => ['vi' => 'Chính sách đổi trả', 'en' => 'Return & Refund Policy'],
                'slug' => 'chinh-sach-doi-tra',
                'content' => ['vi' => '<p>Chính sách đổi trả hàng trong vòng 7 ngày...</p>', 'en' => '<p>Return and refund policy within 7 days...</p>'],
                'status' => 'published',
            ],
            [
                'title' => ['vi' => 'Chính sách vận chuyển', 'en' => 'Shipping Policy'],
                'slug' => 'chinh-sach-van-chuyen',
                'content' => ['vi' => '<p>Miễn phí vận chuyển toàn quốc cho đơn hàng từ 500k...</p>', 'en' => '<p>Free national shipping for orders from 500k...</p>'],
                'status' => 'published',
            ],
        ];

        foreach ($policies as $policyData) {
            Policy::create([
                'title' => json_encode($policyData['title']),
                'slug' => $policyData['slug'],
                'content' => json_encode($policyData['content']),
                'status' => $policyData['status'],
            ]);
        }
    }
}

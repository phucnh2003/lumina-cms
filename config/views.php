<?php

// App-level source of truth for sidebar/system nav config — read via `config('views.*')`. Plugins
// (see `CmsServiceProvider::register()`) only merge their own `configs/views.php` as a fallback default
// for whichever top-level keys aren't already defined here.
return [
    'sidebar' => [
        'main' => [
            [
                'title' => 'Dashboard',
                'url' => '/',
                'icon' => 'LayoutDashboard',
            ],
            [
                'title' => 'Sản phẩm',
                'url' => '#',
                'icon' => 'ShoppingBag',
                'items' => [
                    [
                        'title' => 'Sản phẩm',
                        'url' => '/products',
                    ],
                    [
                        'title' => 'Danh mục sản phẩm',
                        'url' => '/product-categories',
                    ],
                    [
                        'title' => 'Thuộc tính sản phẩm',
                        'url' => '/options',
                    ],
                    [
                        'title' => 'Đánh giá',
                        'url' => '/ratings/products',
                    ],
                ],
            ],
            [
                'title' => 'Đơn hàng',
                'url' => '#',
                'icon' => 'ShoppingCart',
                'items' => [
                    [
                        'title' => 'Danh sách đơn hàng',
                        'url' => '/orders',
                    ],
                    [
                        'title' => 'Giao dịch',
                        'url' => '/transactions',
                    ],
                ],
            ],
            [
                'title' => 'Khách hàng',
                'url' => '#',
                'icon' => 'Users',
                'items' => [
                    [
                        'title' => 'Danh sách khách hàng',
                        'url' => '/users',
                    ],
                    [
                        'title' => 'Nhóm khách hàng',
                        'url' => '/customer-groups',
                    ],
                ],
            ],
            [
                'title' => 'Marketing',
                'url' => '#',
                'icon' => 'Megaphone',
                'items' => [
                    [
                        'title' => 'Phiếu giảm giá',
                        'url' => '/coupons',
                    ],
                    [
                        'title' => 'Biểu mẫu liên hệ',
                        'url' => '/form-submissions/contacts',
                    ],
                ],
            ],
            [
                'title' => 'Bài viết',
                'url' => '#',
                'icon' => 'Newspaper',
                'items' => [
                    [
                        'title' => 'Danh sách bài viết',
                        'url' => '/posts',
                    ],
                    [
                        'title' => 'Danh mục bài viết',
                        'url' => '/post-categories',
                    ],
                    [
                        'title' => 'Tags',
                        'url' => '/tags',
                    ],
                ],
            ],
            [
                'title' => 'website',
                'url' => '#',
                'icon' => 'GalleryHorizontal',
                'items' => [
                    [
                        'title' => 'Sliders',
                        'url' => '/sliders',
                    ],
                    [
                        'title' => 'Chính sách',
                        'url' => '/policies',
                    ],
                    [
                        'title' => 'Redirects',
                        'url' => '/redirects',
                    ],
                ],
            ],
        ],

        'footer' => [
            [
                'title' => 'File Manager',
                'url' => '/file-manager',
                'icon' => 'FolderOpen',
            ],
            [
                'title' => 'Hệ thống',
                'url' => '/system',
                'icon' => 'ShieldCheck',
            ],
        ],
    ],

    'systems' => [
        [
            'key' => 'admins',
            'title' => 'Quản lý Admin',
            'description' => 'Danh sách quản trị viên, phân quyền theo vai trò.',
            'url' => '/admins',
            'icon' => 'Users',
        ],
        [
            'key' => 'roles',
            'title' => 'Quản lý Vai trò',
            'description' => 'Vai trò, quyền hạn và phân quyền thư mục.',
            'url' => '/roles',
            'icon' => 'ShieldCheck',
        ],
        [
            'key' => 'activity-logs',
            'title' => 'Lịch sử hoạt động',
            'description' => 'Nhật ký tạo/sửa/xóa Admin, Vai trò và đăng nhập hệ thống.',
            'url' => '/activity-logs',
            'icon' => 'History',
        ],
        [
            'key' => 'general-settings',
            'title' => 'Cấu hình hệ thống',
            'description' => 'Tên website, logo và favicon hiển thị trên toàn hệ thống.',
            'url' => '/settings/general',
            'icon' => 'SlidersHorizontal',
        ],
        [
            'key' => 'log-viewer',
            'title' => 'Log Viewer',
            'confirmPassword' => true,
            'description' => 'Xem log lỗi/hoạt động của ứng dụng (laravel.log).',
            'url' => '/log-viewer',
            'icon' => 'FileText',
        ],
        [
            'key' => 'horizon',
            'title' => 'Horizon',
            'confirmPassword' => true,
            'description' => 'Giám sát queue/job chạy nền (yêu cầu QUEUE_CONNECTION=redis).',
            'url' => '/horizon',
            'icon' => 'Activity',
        ],
        [
            'key' => 'webhooks',
            'title' => 'Webhooks',
            'enable' => config('plugins.webhook.enable', false),
            'confirmPassword' => true,
            'description' => 'Quản lý endpoint webhook, sự kiện và secret key.',
            'url' => '/webhook-endpoints',
            'icon' => 'Webhook',
        ],
        [
            'key' => 'api-keys',
            'title' => 'Quản lý API Keys',
            'confirmPassword' => true,
            'description' => 'Tạo và quản lý Key xác thực / Key bảo mật cho API Public (/api/items).',
            'url' => '/api-keys',
            'icon' => 'Key',
        ],
    ],
];

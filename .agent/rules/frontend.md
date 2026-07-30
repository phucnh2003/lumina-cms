# Frontend Rules — TypeScript / React / Inertia

> Path filter: `resources/js/**/*.ts`, `resources/js/**/*.tsx`

## TypeScript — Non-negotiable

```typescript
// ❌ SAI
const data: any = response.data;

// ✅ ĐÚNG
interface ApiResponse<T> {
    data: T;
    meta: { total: number; page: number; limit: number };
}
const data: ApiResponse<Product[]> = response.data;
```

- **Không dùng `any`** — dùng `unknown` nếu không biết type, sau đó type guard
- `"strict": true` trong tsconfig — không hạ level

## File structure

```
resources/js/
├── Pages/           ← Inertia page components (route-level)
├── Components/      ← Reusable UI components
│   ├── ui/          ← shadcn/ui generated components (không tự sửa)
│   └── ...
├── hooks/           ← Custom React hooks (prefix `use`)
├── lib/             ← Utilities, helpers
└── types/           ← Shared TypeScript types/interfaces
```

## Naming

| Loại | Convention | Ví dụ |
|------|-----------|-------|
| Page component | `PascalCase.tsx` | `ProductList.tsx` |
| UI Component | `PascalCase.tsx` | `DataTable.tsx` |
| Hook | `useCamelCase.ts` | `useCart.ts` |
| Utility | `camelCase.ts` | `formatCurrency.ts` |
| Type/Interface | `PascalCase` | `interface CartItem {}` |

## Inertia Page pattern

```tsx
import { Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AppLayout from '@/Layouts/AppLayout';

interface Props {
    products: Product[];
    meta: PaginationMeta;
}

export default function ProductIndex({ products, meta }: Props) {
    const { t } = useTranslation();

    return (
        <AppLayout>
            <Head title={t('products.title')} />
            {/* content */}
        </AppLayout>
    );
}
```

## UI Components

- **shadcn/ui + Radix UI** cho mọi primitive (Button, Dialog, Select, Table…)
- **Không tự build** UI primitive từ đầu
- **Tabler Icons** (`@tabler/icons-react`) cho icons
- **TailwindCSS v4** cho styling — không dùng `style={{}}` inline

## Forms — React Hook Form + Zod

```tsx
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';

const schema = z.object({
    name: z.string().min(1),
    price: z.number().int().min(0),
});

type FormData = z.infer<typeof schema>;

function ProductForm() {
    const { register, handleSubmit, formState: { errors } } = useForm<FormData>({
        resolver: zodResolver(schema),
    });
    // ...
}
```

## URLs — Ziggy route()

```tsx
// ❌ SAI
fetch('/api/products')
<Link href="/dashboard">

// ✅ ĐÚNG
fetch(route('api.products.index'))
<Link href={route('dashboard')}>
```

## Internationalization — i18next

```tsx
// ❌ SAI
<h1>Danh sách sản phẩm</h1>
<button>Thêm mới</button>

// ✅ ĐÚNG
const { t } = useTranslation();
<h1>{t('products.list.title')}</h1>
<button>{t('common.add_new')}</button>
```

## Data Tables — TanStack Table

Dùng `@tanstack/react-table` cho mọi tabular data. Không tự build table logic.

## Lệnh trước khi commit frontend

```bash
npm run lint:check     # ESLint
npm run types:check    # TypeScript
npm run format:check   # Prettier
```

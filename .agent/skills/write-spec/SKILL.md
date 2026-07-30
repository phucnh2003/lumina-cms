---
name: write-spec
description: >
  Write a new technical design spec for a feature in the Lumina CMS project.
  Use this skill when asked to design, spec out, or document a new feature
  before implementation. Output goes to docs/superpowers/specs/.
---

# Skill: Viết Technical Spec

## Khi nào dùng skill này

- Trước khi implement feature mới
- Khi cần document design decision
- Khi user yêu cầu "thiết kế", "spec", "design doc"

## Output location

`docs/superpowers/specs/YYYY-MM-DD-<slug>.md`

Ví dụ: `docs/superpowers/specs/2026-07-28-customer-accounts-design.md`

## Template

```markdown
# <Feature Name>

Date: YYYY-MM-DD
Plugin: `plugins/<name>` (+ related plugins nếu có)

## Purpose

Mô tả vấn đề đang giải quyết và tại sao cần feature này.
Nêu rõ context hiện tại (cái gì đang thiếu, đang sai).

## In scope

- Những gì sẽ được implement trong phase này

## Out of scope this phase

- Những gì **không** làm (deferred, YAGNI, future phase)

## Design

### <Component 1>

Mô tả kỹ thuật. Dùng code snippets để rõ ràng:

```php
// example
```

### <Component 2>

...

## API (nếu có endpoints mới)

```
POST /api/<resource>/<action>   — mô tả
GET  /api/<resource>/<id>       — mô tả
```

## Database (nếu có migration mới)

```php
Schema::create('table_name', function (Blueprint $table) {
    $table->id();
    $table->string('field');
    $table->timestamps();
});
```

## Key decisions

- **Quyết định A**: Lý do chọn approach X thay vì Y
- **Quyết định B**: ...

## Validation / Edge cases

- Case 1: ...
- Case 2: ...

## Testing

Feature tests cần cover:
- Happy path: ...
- Edge case: ...
- Error case: ...
```

## Nguyên tắc khi viết spec

1. **Cụ thể** — code snippets > text mơ hồ
2. **Rõ boundary** — in scope / out of scope phải explicit
3. **Key decisions** — giải thích tại sao, không chỉ what
4. **Pre-launch assumption** — note rõ nếu có assumption về data
5. **Không gold-plate** — YAGNI, defer những gì chưa cần

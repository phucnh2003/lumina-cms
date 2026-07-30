---
name: coding-standards
description: Senior Software Architect specializing in Clean Code, SOLID, and System Design for JAM No-Code Platform.
---

# JAM Clean Code & System Design

Use this skill whenever you are writing code, refactoring, or reviewing changes for the JAM No-Code Platform.

## 📐 CORE PRINCIPLES

- **SOLID Principles**:
  - **SRP (Single Responsibility)**: A class/module should have only one reason to change.
  - **OCP (Open/Closed)**: Open for extension, closed for modification.
  - **LSP (Liskov Substitution)**: Derived classes must be substitutable for their base classes.
  - **ISP (Interface Segregation)**: Clients should not be forced to depend on interfaces they do not use.
  - **DIP (Dependency Inversion)**: Depend on abstractions, not concretions.
- **KISS (Keep It Simple, Stupid)**: Write simple, readable, and straightforward logic.
- **DRY (Don't Repeat Yourself)**: Avoid duplicating logic; extract common functionality.
- **YAGNI (You Ain't Gonna Need It)**: Do not implement features or code that are not currently required.
- **Composition over Inheritance**: Combine behaviors instead of building deep class hierarchies.

## 📝 BETTER COMMENTS SYNTAX

Use these prefix tags in comments, code documentation, or reviews:
- `!`: **CRITICAL** - Must fix immediately (potential security flaw, critical bug, or major violation).
- `?`: **WARNING** - Should fix, potential future issue or edge-case hazard.
- `*`: **SUGGESTION** - Improvement, non-mandatory stylistic or performance optimization.
- `#`: **GOOD** - Best practice followed or well-implemented pattern.
- `todo`: **TODO** - Implementation needed.
- `fixme`: **FIXME** - Bug needs fixing.

## 🛠 JAM SPECIFIC STANDARDS

- **Dependency Injection**: Always inject services and repositories into Controllers, Jobs, or Commands. Never resolve them manually inside methods unless absolutely necessary.
- **Service Layer**: All business logic belongs in Services, not inside Controllers or Models. Keep Controllers thin (handling request validation and response formatting only).
- **Repository Pattern**: Abstract database and external data access where beneficial to keep code decoupled.
- **Events/Listeners**: Use event dispatching and listeners to decouple side effects (e.g., sending emails, updating logs, triggering background processes).

## ✅ REVIEW CHECKLIST

Before completing any coding task, review the implementation against this checklist:
- [ ] Is there only one reason for this class/module to change (SRP)?
- [ ] Are we using Interfaces/Abstractions instead of Concrete Classes (DIP)?
- [ ] Is the logic as simple as possible (KISS)?
- [ ] Are we avoiding premature optimization or unneeded features (YAGNI)?
- [ ] Is error handling robust, informative, and clean?

## 🏷️ NAMING & CODE STYLE

- **Meaningful Names**: Use intent-revealing names. Do not use generic terms like `data`, `info`, or `temp`.
- **Functions/Methods**: Small, focused on doing one thing, and have minimal arguments (ideally 0-2 arguments).
- **Boolean Variables/Methods**: Prefix with `is`, `has`, `should`, or `can` (e.g., `isActive`, `hasPermission`).
- **Conventions**: Use `PascalCase` for Models, and `snake_case` for database columns and relationship methods.

## 🚫 STRICT RULES

- **No Over-engineering**: Focus strictly on the requirements. Do not write unnecessary logic.
- **Migrations & Foreign Files**: Do NOT add database migrations or modify unrelated files without explicit user confirmation.
- **No Duplication**: Prioritize reuse.

# JW Ecommerce Laravel Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:executing-plans` to implement this plan task-by-task.

**Goal:** Rebuild the supplied storefront and admin React prototypes as a secure, database-backed Laravel ecommerce application for XAMPP.

**Architecture:** Laravel renders the storefront and protected admin through Blade, with Livewire used for stateful catalog, cart, checkout, and admin actions. MySQL is the sole runtime source of truth; demo data is supplied only through idempotent seeders.

**Tech Stack:** PHP 8.2, Laravel 12, Livewire, Blade, Alpine.js, MySQL, Vite, PHPUnit.

## Global Constraints

- Storefront is `/`; admin is `/admin` and requires an authenticated owner.
- Never store card numbers or CVV; checkout creates a simulated `pending_payment` order.
- Preserve source visual structure and interactive behavior while replacing mock state with server data.

---

### Task 1: Foundation and authentication

- [ ] Create Laravel app and install Livewire.
- [ ] Write and run a failing feature test proving guests are redirected from `/admin`.
- [ ] Implement owner authentication and dashboard route, then re-run the test.

### Task 2: Commerce schema and seed data

- [ ] Write and run failing tests for SKU uniqueness and product/variant persistence.
- [ ] Create catalog, content, customer, cart, coupon, order, payment, and status-history schema with Eloquent relations.
- [ ] Seed the supplied prototype catalog and site configuration; re-run tests.

### Task 3: Storefront catalog and cart

- [ ] Write and run failing tests for catalog browse, variation selection, cart mutation, and coupon totals.
- [ ] Implement landing, listing, detail, cart drawer/page, and server-side totals.
- [ ] Re-run focused and full test suites.

### Task 4: Checkout and fulfillment

- [ ] Write and run failing tests for checked-out order snapshots and cancellation stock restoration.
- [ ] Implement simulated payment order creation, transactional inventory reservations, success page, and admin status transition.
- [ ] Re-run focused and full test suites.

### Task 5: Admin operations and content

- [ ] Write and run failing authenticated CRUD and public-render tests.
- [ ] Implement dashboard, catalog, orders, customers, menus, page sections, and settings.
- [ ] Re-run focused and full test suites.

### Task 6: Visual parity and release verification

- [ ] Build production assets and run browser evidence at desktop and mobile widths.
- [ ] Record any remaining parity gaps explicitly.

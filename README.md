# JW Ecommerce

A Laravel + Blade + MySQL migration of the supplied JW storefront and administration prototypes.

- Storefront: http://127.0.0.1:8500/
- Owner administration: http://127.0.0.1:8500/admin
- Stack: PHP 8.2, Laravel 12, Blade, Livewire, MySQL, Vite
- Runtime data: MySQL only. Seed records are demo data, not runtime mock state.

## Quick start with XAMPP

1. Start Apache and MySQL in XAMPP.
2. Create a UTF-8 MySQL database named jw_ecommerce in phpMyAdmin.
3. Copy .env.example to .env. Set DEMO_OWNER_EMAIL and DEMO_OWNER_PASSWORD there if you want the seed to create the local owner, then run:

    php artisan key:generate
    php artisan migrate --seed
    npm install
    npm run build

4. Point Apache to the project's public directory, or run locally:

    php artisan serve --host=127.0.0.1 --port=8500

Full Apache/XAMPP detail is in docs/xampp-setup.md.

## Hostinger EasyParcel cron

Create one Hostinger cron job that runs every minute with this exact command:

```sh
cd /home/u762348670/apps/jwecommerce && php artisan easyparcel:cron --no-interaction
```

The command uses a crash-safe overlap lock with a minimum 180-second lease,
runs Laravel's due schedules, and then drains the default database queue within
a bounded shared-hosting runtime. Production `CACHE_STORE` must support Laravel
atomic locks across separate PHP processes (the database cache store does).
EasyParcel pending-AWB reconciliation runs every minute; tracking for shipments
that already have an AWB remains scheduled every 15 minutes.

Create the owner account with your selected local credentials before using /admin.
Never commit passwords or a populated .env file. The owner can change their password
from **Login settings** after signing in.

## Functional scope

- Database-backed catalog, SKU variants, stock, product imagery, categories and collections.
- Public landing, PLP, PDP, cart, coupon validation, simulated checkout and success view.
- Server-side totals including configured shipping and tax.
- Transactional stock reservation and cancellation restock.
- Simulated payment and fulfillment state machine: pending payment -> paid -> processing -> shipped -> delivered.
- Owner administration for catalog, inventory, promotions, orders, customers, navigation, storefront sections, social links, store settings and owner login settings.

No payment card number or CVV is accepted or stored. The checkout creates only a simulated payment record.

## Verification

    php artisan test
    npm run build

Vite builds successfully with the current environment. It currently warns that Node 20.17.0 is below Vite's recommended 20.19.0; upgrade Node before production deployment.

## Source assets

The source logo and catalog images are local managed project assets. See
docs/source-assets.md for provenance and redistribution notes.

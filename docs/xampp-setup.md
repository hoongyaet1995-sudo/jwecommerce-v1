# XAMPP setup

1. In phpMyAdmin, create a UTF-8 database named `jw_ecommerce`.
2. Copy `.env.example` to `.env`. To create a local owner during seed, set both DEMO_OWNER_EMAIL and DEMO_OWNER_PASSWORD there, then run `php artisan key:generate`.
3. Run `php artisan migrate --seed` to create the schema and source-inspired demo catalog.
4. Set Apache's document root to this project's `public` directory, or run `php artisan serve --host=127.0.0.1 --port=8500` during development.

When both DEMO_OWNER_EMAIL and DEMO_OWNER_PASSWORD are set, the seeder creates or
updates that local owner. Credentials are not stored in project documentation. Use the
Login settings page to rotate the password after signing in.

No real payment card information is accepted or stored. Checkout persists a simulated pending-payment order only.

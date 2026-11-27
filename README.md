# Corevia AI

> **Note:** This is a temporary README. Detailed documentation will be added under `docs/`.

## Quick Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install && npm run build
```

*(Alternatively, run `composer setup`.)*

## Application Behavior

### Roles & Permissions
- Access control is managed via **Spatie Laravel Permission**.
- Internal management users access the Filament admin panel (`/management`) with role/policy-based permissions.
- Vendor users (`vendor-user` role linked to an active `Account`) access the Vendor Portal (`/portal`).

### Queue Jobs
- CSV ingestion runs asynchronously on the `integrations` queue:
  ```bash
  php artisan queue:work --queue=integrations --timeout=600 --tries=3
  ```

### Command Jobs & Scheduler
- Integration file discovery is scheduled every minute in `routes/console.php`. Run the scheduler locally with:
  ```bash
  php artisan schedule:work
  ```

### Integration Files & Examples
- Drop `.ready.csv` files (`accounts-`, `products-`, `sales-orders-`) into `storage/app/private/integration/in` (or `INTEGRATION_ROOT`).
- Sample CSVs are available in `examples/integration/`.
- See `docs/integration-engine.md` for the full CSV contract.

### AI Assistant Configuration
- Update the `VENDOR_ASSISTANT_*` variables in `.env` (such as `VENDOR_ASSISTANT_ENABLED`, `VENDOR_ASSISTANT_PROVIDER`, `VENDOR_ASSISTANT_MODEL`, and `VENDOR_ASSISTANT_API_KEY`) to enable the portal AI assistant.

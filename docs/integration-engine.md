# IntegrationEngine and DataHub CSV ingestion

## Boundaries

`app/Modules/IntegrationEngine` owns discovery, safe file claiming, CSV parsing, validation, ingestion history, queue orchestration, recovery, archiving, and quarantine. `app/Modules/DataHub/Actions` owns business-record upserts and the transaction boundary for a sales order and all lines supplied for it. DataHub does not know about paths or CSV structure.

The initial adapter is the explicitly configured `erp` source in `config/integration.php`. A future SAP or Oracle adapter should add a source configuration with its own header-to-canonical-field map. It should reuse the discovery and processing workflow rather than add source-specific logic to DataHub.

## Database schema

- `ingestions`: source, dataset, original and generated filenames, SHA-256 checksum, lifecycle status, timestamps, attempts, row counts, record counts, and last infrastructure error. `(source, dataset, checksum)` is unique.
- `ingestion_errors`: row number, field, stable error code, and readable message.
- `staged_sales_order_rows`: relational staging used to group non-adjacent lines and validate a whole order before writing it.
- `accounts`, `products`, and `sales_orders`: nullable `source` and `external_id`, unique per entity and source.
- `sales_order_lines`: nullable source identity unique within its parent sales order.
- Imported prices/totals are `decimal(18,2)` and quantities are `decimal(18,4)`. Currency is an ISO-style three-letter uppercase code.

Existing records remain valid because the integration identifiers are nullable. Imports never delete records, create portal users, or implicitly create missing customers/products.

## Private directory and delivery contract

The default root is `storage/app/private/integration`, overridable with `INTEGRATION_ROOT`. It must not be web-accessible.

```text
integration/
  in/erp/{customers,products,sales_orders}/
  processing/erp/{customers,products,sales_orders}/
  archive/erp/{customers,products,sales_orders}/YYYY-MM-DD/
  failed/erp/{customers,products,sales_orders}/YYYY-MM-DD/
```

The producer must:

1. Write a temporary file in the final `in/erp/<dataset>` directory on the same filesystem.
2. Close and flush it.
3. Atomically rename it to a name ending in `.ready.csv`.

Discovery ignores other suffixes, directories, and symbolic links. Corevia assigns a generated processing filename and retains the original name in the database. Source and dataset come only from configured directories; file contents never select classes or adapters.

Load deliveries in this order: customers, products, then sales orders. Discovery enforces that priority per source by not advancing while an earlier ready or active ingestion exists.

## CSV contracts

Files must be UTF-8, comma-delimited, and contain the exact configured header in the documented order. Dates use `YYYY-MM-DD`. Decimals use `.` without currency symbols or thousands separators. Blank optional fields are accepted.

Customers (stored as vendor-type `Account` records):

```text
customer_external_id,name,status
```

`status` is `active` or `inactive`.

Products:

```text
product_external_id,sku,name,description,price,currency
```

Sales orders, one line per row:

```text
order_external_id,order_number,order_date,customer_external_id,status,currency,total_amount,line_external_id,line_number,product_external_id,quantity,unit_price,line_total,description,sku,unit_of_measure
```

Order status is `pending`, `processing`, `completed`, or `canceled`. All repeated order-level values must match. Line external identifiers must be unique within the order. `customer_external_id` and `product_external_id` must already exist for the same source. If `sku` is supplied on an order line, it must match the referenced product.

Runnable examples are in `docs/integration-examples/erp`.

## Processing semantics

- Customer and product rows are independent. Valid rows are committed and invalid rows become precise ingestion errors.
- Order rows are staged so they need not be adjacent. Every row for an order is validated before DataHub starts its transaction.
- A valid order and all supplied lines commit together. No missing lines are deleted. If another order fails, already valid orders remain committed and the file is `partially_failed`.
- `completed` files are archived. `partially_failed` and `failed` files are quarantined with an adjacent `.errors.json` manifest while their successful writes and database history remain.
- Re-delivery of the same source/dataset/checksum does not run business writes again. The duplicate delivery is moved under `archive/.../duplicates`.
- Validation failures terminate normally and are not retried. Infrastructure, database, and filesystem exceptions use three bounded job attempts with 10, 60, and 180 second backoff.

`valid_rows` and `failed_rows` count source rows. For an accepted order, `created_records`/`updated_records` count the order plus its supplied lines.

## Installation and operation

Run additive migrations:

```bash
php artisan migrate --no-interaction
```

The scheduler definition is in `routes/console.php` and runs discovery every minute with overlap and single-server locks. Production needs one scheduler process and at least one worker for the integration queue:

```bash
php artisan schedule:work
php artisan queue:work --queue=integrations --timeout=600 --tries=3
```

Keep the worker timeout below `DB_QUEUE_RETRY_AFTER` (default `660`). Scheduler nodes must share a lock-capable cache store for `onOneServer()`.

Manual operations:

```bash
php artisan integration:discover
php artisan integration:status
php artisan integration:status --id=123 --errors
php artisan integration:recover
php artisan integration:recover --id=123
php artisan integration:recover --id=123 --retry-failed
```

`integration:recover` requeues stale active claims. Selecting an ID bypasses the stale-age wait. Failed and partially failed files require `--retry-failed`; the same history record is reset and reused, so checksum identity remains unique.

## Docker and Azure deployment contract

Mount one persistent private volume at the configured root into the scheduler and every integration worker. The ERP delivery process must mount the same filesystem so its final rename is atomic. Do not use separate volumes for `in` and `processing`.

Example service fragment:

```yaml
services:
  app:
    environment:
      INTEGRATION_ROOT: /var/lib/corevia/integration
    volumes:
      - integration-data:/var/lib/corevia/integration
  scheduler:
    environment:
      INTEGRATION_ROOT: /var/lib/corevia/integration
    volumes:
      - integration-data:/var/lib/corevia/integration
  worker:
    command: php artisan queue:work --queue=integrations --timeout=600 --tries=3
    environment:
      INTEGRATION_ROOT: /var/lib/corevia/integration
    volumes:
      - integration-data:/var/lib/corevia/integration
volumes:
  integration-data:
```

On Azure, use a private persistent mount shared by the delivery process, scheduler, and workers. Grant the application service account read/write/rename access only to this root. Monitor failed queue jobs, `failed` directory growth, stuck non-terminal ingestions older than the configured threshold, and structured log records containing `ingestion_id`.

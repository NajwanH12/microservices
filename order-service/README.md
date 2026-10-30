# Order Service

Laravel service for creating and managing orders in the microservices project.
The order database is owned by this service; user and product IDs are external
references and are not foreign keys.

## Database

The MySQL schema is created by Laravel migrations:

```powershell
docker compose up -d order-db
composer install
if (-not (Test-Path .env)) { Copy-Item .env.example .env }
php artisan key:generate
php artisan migrate
php artisan serve --port=3003
```

Configure the connection through the `DB_*` environment variables. The example
uses MySQL on `127.0.0.1:3307`, keeping the host port distinct from the
product-service database.

To run the integrated stack from the repository root:

```powershell
docker compose up --build
```

The order API is available at `http://localhost:3003`. Docker Compose starts a
dedicated MySQL database for this service and runs the migrations before
starting Laravel. If host ports are already in use, override
`PRODUCT_SERVICE_HOST_PORT`, `CART_SERVICE_HOST_PORT`, `CART_DB_HOST_PORT`,
`ORDER_SERVICE_HOST_PORT`, or `ORDER_DB_HOST_PORT` in the Compose environment.
The default local service URLs in `.env.example` point to
the product-service and cart-service ports on the host; Compose sets their
internal service URLs automatically.

The schema contains:

- `orders`: user reference, order status, total amount, and timestamps.
- `order_items`: order relation, product snapshot, quantity, unit price, and
  line subtotal.

Order items are deleted when their order is deleted. Product and user
references intentionally have no foreign keys because those records belong to
other services.

## API

| Method | Endpoint | Purpose |
| --- | --- | --- |
| `GET` | `/health` | Service health |
| `POST` | `/orders` | Check out a user's cart (`{"user_id": 7}`) |
| `GET` | `/orders/{id}` | Get an order and its item snapshots |
| `GET` | `/orders/user/{userId}?status=PENDING` | List a user's orders, optionally filtered by status |
| `PATCH` | `/orders/{id}/pay` | Simulate payment for a pending order |

Checkout validates product availability and stock, stores the order and its
item snapshots in one database transaction, then asks cart-service to clear
the cart. Stock is checked but not reserved or deducted.

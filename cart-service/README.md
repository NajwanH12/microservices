# Cart Service

Cart Service is a Laravel 13 API backed by PostgreSQL. It stores cart items
only; `user_id` and `product_id` are references, not foreign keys, because
their data belongs to other services.

## Run locally

Requirements: PHP 8.4.1+, Composer 2, Docker Compose, and the PHP `pdo_pgsql`
extension.

```powershell
Copy-Item .env.example .env
composer install
php artisan key:generate
docker compose up -d cart-db
php artisan migrate
php artisan serve --port=3001
```

The API is available at `http://localhost:3001`. The PostgreSQL credentials
and connection settings are in `.env`; Docker Compose overrides the database
host to `cart-db` when it runs the application in a container.

## Endpoints

| Method | Endpoint | Description |
| --- | --- | --- |
| `GET` | `/carts` | Get all cart items |
| `GET` | `/carts/{userId}` | Get a user's cart and total quantity |
| `POST` | `/carts` | Add a product; an existing product's quantity is increased |
| `PUT` | `/carts/items/{id}` | Set an item's quantity |
| `DELETE` | `/carts/items/{id}` | Remove one item |
| `DELETE` | `/carts/{userId}` | Empty a user's cart |

Example request bodies:

```json
{"user_id": 1, "product_id": 1, "quantity": 2}
```

`quantity` is optional when adding an item and defaults to `1`. Updating an
item requires:

```json
{"quantity": 5}
```

Invalid input returns JSON with status `422`; missing cart items return `404`.
Unexpected server errors are logged and return `500`. Error details are
included in the response only when `APP_DEBUG=true`.

## Run the full stack in Docker

```powershell
docker compose up --build
```

This starts the API on port `3001` and PostgreSQL on port `5432`. The service
runs migrations before starting Laravel.

## Tests

```powershell
php artisan test
```

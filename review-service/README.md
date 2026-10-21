# Review Service

Review Service is a Flask API backed by MongoDB. It checks that a product
exists through Product Service before creating a review; reviews remain
readable even if Product Service is unavailable later.

## Run with Docker Compose

Run the stack from the repository root:

```powershell
docker compose up --build
```

This starts Product Service and its MySQL database, Review Service on port
`3002`, and MongoDB on port `27017`. The root `docker-compose.yml` is the only
Compose file needed to run these services together.

## Run Flask locally

Start MongoDB and Product Service first, then from this directory:

```powershell
Copy-Item .env.example .env
py -m venv venv
.\venv\Scripts\Activate.ps1
pip install -r requirements.txt
python run.py
```

The API is available at `http://localhost:3002`. Set `MONGO_URI` and
`PRODUCT_SERVICE_URL` in `.env` if the dependencies use different addresses.

## Endpoints

| Method | Endpoint | Description |
| --- | --- | --- |
| `GET` | `/health` | Health check |
| `GET` | `/reviews/product/{product_id}` | List product reviews and rating summary |
| `GET` | `/reviews/{review_id}` | Get one review |
| `POST` | `/reviews` | Create a review |
| `PUT` | `/reviews/{review_id}` | Update a review's rating and/or comment |
| `DELETE` | `/reviews/{review_id}` | Delete a review |

Create a review with `product_id`, `user_id`, and `rating`; `comment` is
optional and limited to 1000 characters. IDs must be positive integers and
ratings must be integers from 1 through 5. A user can review a product only
once. Invalid input or a product that does not exist returns `422`; duplicate
reviews return `409`; a missing review returns `404`; an unavailable Product
Service returns `503`.

## Tests

From this directory, run:

```powershell
python -m unittest discover -s tests
```

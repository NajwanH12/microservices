import unittest
from unittest.mock import patch

from app import create_app
from app.exceptions import (
    DuplicateReviewError,
    ProductNotFoundError,
    ProductServiceUnavailableError,
    ReviewNotFoundError,
)


class ReviewApiTest(unittest.TestCase):
    def setUp(self):
        self.init_db_patcher = patch("app.init_db")
        self.init_db_patcher.start()
        self.addCleanup(self.init_db_patcher.stop)
        self.app = create_app().test_client()

    @patch("app.controllers.review_controller.review_service.get_product_reviews")
    def test_lists_reviews_and_summary_without_product_service(self, get_reviews):
        get_reviews.return_value = {
            "product_id": 1,
            "summary": {"average_rating": 4.5, "total_reviews": 2},
            "reviews": [],
        }

        response = self.app.get("/reviews/product/1")

        self.assertEqual(response.status_code, 200)
        self.assertEqual(response.json["data"]["summary"]["total_reviews"], 2)

    @patch("app.controllers.review_controller.review_service.create_review")
    def test_creates_review(self, create_review):
        create_review.return_value = {"id": "abc", "rating": 5}

        response = self.app.post(
            "/reviews",
            json={"product_id": 1, "user_id": 7, "rating": 5},
        )

        self.assertEqual(response.status_code, 201)
        self.assertEqual(response.json["data"]["id"], "abc")
        create_review.assert_called_once_with(
            {"product_id": 1, "user_id": 7, "rating": 5, "comment": ""}
        )

    def test_rejects_invalid_review_fields(self):
        response = self.app.post(
            "/reviews",
            json={"product_id": True, "user_id": 0, "rating": 6, "comment": 4},
        )

        self.assertEqual(response.status_code, 422)
        self.assertEqual(
            set(response.json["errors"]),
            {"product_id", "user_id", "rating", "comment"},
        )

    @patch("app.controllers.review_controller.review_service.create_review")
    def test_returns_validation_error_when_product_does_not_exist(self, create_review):
        create_review.side_effect = ProductNotFoundError(99)

        response = self.app.post(
            "/reviews",
            json={"product_id": 99, "user_id": 7, "rating": 5},
        )

        self.assertEqual(response.status_code, 422)
        self.assertIn("product_id", response.json["errors"])

    @patch("app.controllers.review_controller.review_service.create_review")
    def test_returns_service_unavailable_for_product_service_failure(
        self, create_review
    ):
        create_review.side_effect = ProductServiceUnavailableError("offline")

        response = self.app.post(
            "/reviews",
            json={"product_id": 1, "user_id": 7, "rating": 5},
        )

        self.assertEqual(response.status_code, 503)

    @patch("app.controllers.review_controller.review_service.create_review")
    def test_returns_conflict_for_duplicate_review(self, create_review):
        create_review.side_effect = DuplicateReviewError(7, 1)

        response = self.app.post(
            "/reviews",
            json={"product_id": 1, "user_id": 7, "rating": 5},
        )

        self.assertEqual(response.status_code, 409)

    @patch("app.controllers.review_controller.review_service.update_review")
    def test_updates_review(self, update_review):
        update_review.return_value = {"id": "abc", "rating": 4}

        response = self.app.put("/reviews/abc", json={"rating": 4})

        self.assertEqual(response.status_code, 200)
        update_review.assert_called_once_with("abc", {"rating": 4})

    @patch("app.controllers.review_controller.review_service.get_review")
    def test_returns_not_found_for_missing_review(self, get_review):
        get_review.side_effect = ReviewNotFoundError("missing")

        response = self.app.get("/reviews/missing")

        self.assertEqual(response.status_code, 404)

    def test_returns_json_for_unknown_endpoint(self):
        response = self.app.get("/unknown")

        self.assertEqual(response.status_code, 404)
        self.assertEqual(response.json, {"message": "Endpoint tidak ditemukan"})


if __name__ == "__main__":
    unittest.main()

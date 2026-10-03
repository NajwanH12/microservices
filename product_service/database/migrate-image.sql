-- Non-destructive migration for databases created before products.image existed.
-- Run against productdb with a MySQL account allowed to ALTER and UPDATE products.
SET @image_column_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'products'
      AND COLUMN_NAME = 'image'
);

SET @add_image_sql = IF(
    @image_column_exists = 0,
    'ALTER TABLE products ADD COLUMN image MEDIUMTEXT NULL',
    'SELECT 1'
);
PREPARE add_image_stmt FROM @add_image_sql;
EXECUTE add_image_stmt;
DEALLOCATE PREPARE add_image_stmt;

-- Give existing rows a small valid Base64 placeholder before enforcing NOT NULL.
UPDATE products
SET image = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/lxoAAAAASUVORK5CYII='
WHERE image IS NULL;

ALTER TABLE products MODIFY COLUMN image MEDIUMTEXT NOT NULL;

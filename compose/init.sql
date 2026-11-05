-- Extra databases for tests, created on the first start of each container.
-- `wordpress` (local development) is created by the image itself.
CREATE DATABASE IF NOT EXISTS wordpress_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;
CREATE DATABASE IF NOT EXISTS wordpress_conformance CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;
GRANT ALL PRIVILEGES ON wordpress_testing.* TO 'wordpress'@'%';
GRANT ALL PRIVILEGES ON wordpress_conformance.* TO 'wordpress'@'%';
-- The checker creates and drops a scratch schema to compare tables with WordPress's own.
GRANT ALL PRIVILEGES ON wm_schema_reference.* TO 'wordpress'@'%';
FLUSH PRIVILEGES;

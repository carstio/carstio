-- Carstio uses Doctrine's `dbname_suffix` ("_test") so functional tests run
-- against `carstio_test` instead of `carstio`. The MariaDB image only
-- grants the application user privileges on the database it created, so
-- without this script tests fail with "Access denied" before they can even
-- create the test database.
--
-- Granting on `carstio%`.* covers both `carstio` (production / dev) and
-- `carstio_test` (PHPUnit), and any future suffixed database (e.g.
-- `carstio_test_token` when running tests in parallel).
CREATE DATABASE IF NOT EXISTS carstio_test;
GRANT ALL PRIVILEGES ON `carstio%`.* TO 'carstio'@'%';
FLUSH PRIVILEGES;

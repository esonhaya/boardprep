# BoardPrep

BoardPrep is a PHP application with a file-backed JSON store by default. It
requires PHP 8.1 or newer and a readable, writable storage directory.

## Local configuration

Copy `.env.example` to `.env` and adjust it for the local machine. `.env` is
ignored and must never be committed. The supported local default is
`DB_DRIVER=json`; BoardPrep creates `APP_STORAGE_PATH` when it is missing.
Process environment variables take precedence over values in `.env`.

`APP_TIMEZONE` must be a PHP timezone identifier such as `UTC` or
`Asia/Manila`. MySQL is optional; selecting `DB_DRIVER=mysql` requires the
`pdo_mysql` extension and the documented `DB_*` connection settings.

For local HTTP use, serve `public/` as the document root. Configuration errors
fail startup with an HTTP 500 response without exposing details unless
`APP_DEBUG=true`.

## Verification

Run `php tools/function-test.php`, `php tools/http-test.php`, and
`php tools/doctor.php` from the repository root.

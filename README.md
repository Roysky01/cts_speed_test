# CTS Speed Test

A Laravel speed test that measures the path from a visitor's browser to this server.

Opening the page shows the IP address the server sees. Starting a test measures:

- **Ping** — median of eight small requests, after one warm-up request
- **Jitter** — average change between those ping samples
- **Download** — parallel streams of incompressible data, counted in the browser as bytes arrive
- **Upload** — parallel uploads back to this server
- **Loaded ping** — extra latency samples taken while the download is running

Throughput uses decimal megabits, the same unit as an internet plan:

```text
Mbps = (bytes × 8) ÷ (seconds × 1,000,000)
```

The first portion of each transfer is left out of the average so TCP slow-start does not decide the result. One TCP connection often cannot fill a link, so the test uses several streams. Measurement routes do not use the session, because a session lock would make those streams wait on each other.

Results are stored for the visitor's own IP. Other visitors' addresses are not listed.

## Requirements

- PHP 8.2+ with `sqlite`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, and `fileinfo`
- Composer
- For a real multi-stream result, PHP-FPM with more than one worker (or another process manager). `php artisan serve` handles one request at a time, so parallel streams queue and the number comes out low.

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan speedtest:payload
composer serve
```

Then open the `APP_URL` (by default `http://127.0.0.1:8000`).

## XAMPP

XAMPP for Windows includes PHP 8.2, which this app supports. From the project folder:

```powershell
composer install
copy .env.example .env
C:\xampp\php\php.exe artisan key:generate
New-Item -ItemType File -Force database\database.sqlite
C:\xampp\php\php.exe artisan migrate
```

Start Apache in the XAMPP Control Panel and open `http://localhost/cts_speed_test/public`. Set `APP_URL` to that address. In `C:\xampp\php\php.ini`, set `post_max_size` and `upload_max_filesize` to `64M`, and enable `pdo_sqlite`, `sqlite3`, `mbstring`, `openssl`, and `fileinfo`.

`composer serve` raises PHP's upload limit for the built-in server. Behind PHP-FPM, `public/.user.ini` sets `post_max_size` and `upload_max_filesize` to 64M. The browser sends 2 MB upload chunks, which also fits the default 8M limit.

## Production

Put nginx or another web server in front of PHP-FPM. Give the pool several workers (`pm.max_children` of at least 8) so download and upload streams run together. Turn gzip off for the measurement URLs, and do not buffer the download:

```nginx
location ^~ /api/download {
    gzip off;
    fastcgi_buffering off;
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $realpath_root/index.php;
    fastcgi_pass unix:/run/php/php-fpm.sock;
}

location ^~ /api/upload {
    gzip off;
    client_max_body_size 64m;
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $realpath_root/index.php;
    fastcgi_pass unix:/run/php/php-fpm.sock;
}
```

If the app sits behind a reverse proxy, set `TRUSTED_PROXIES` to those proxy addresses (comma-separated), or `*` only when clients cannot reach PHP directly. Behind Cloudflare, use Cloudflare's IP ranges. When proxies are trusted, `CF-Connecting-IP` is the address shown to the visitor.

`SPEEDTEST_SERVER_NAME` is the label shown on the page.

## Tests

```bash
php artisan test
```

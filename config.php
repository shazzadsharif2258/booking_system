<?php
// config.php
if (!defined('CONFIG_LOADED')) {
  define('CONFIG_LOADED', true);

  // DEV errors (turn off in prod)
  ini_set('display_errors', 1);
  ini_set('display_startup_errors', 1);
  error_reporting(E_ALL);

  /* ========= App base & version ========= */
  define('APP_BASE', '/booking/event');   // change if you move the app
  define('ASSET_VER', '1.0.6');

  /* ========= Paths & URLs ========= */
  define('BASE_DIR', str_replace('\\','/', __DIR__)); // absolute filesystem path

  if (isset($_SERVER['HTTP_HOST'])) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    define('BASE_URL', $scheme . '://' . $_SERVER['HTTP_HOST'] . APP_BASE);
  } else {
    define('BASE_URL', 'http://localhost' . APP_BASE);
  }

  // Helper for absolute URLs inside the app
  function app_url(string $path = ''): string {
    $base = rtrim(BASE_URL, '/');
    return $base . '/' . ltrim($path, '/');
  }

  /* ========= Site ========= */
  define('SITE_NAME', 'ParlourLink');
  define('SITE_URL', BASE_URL);

  /* ========= Database ========= */
  define('DB_HOST', 'localhost');
  define('DB_USER', 'root');
  define('DB_PASS', '');
  define('DB_NAME', 'event_management_system');

  /* ========= Mail (update for production) ========= */
  define('MAIL_HOST', 'smtp.gmail.com');
  define('MAIL_PORT', 587);
  define('MAIL_USERNAME', 'your-email@gmail.com');
  define('MAIL_PASSWORD', 'your-app-password');
  define('MAIL_FROM', 'your-email@gmail.com');
  define('MAIL_FROM_NAME', 'ParlourLink');

  /* ========= Sessions & Security ========= */
  define('SESSION_TIMEOUT', 30 * 60); // 30 mins
  define('HASH_COST', 10);

  /* ========= Currency ========= */
  define('CURRENCY_SYMBOL', '৳');

  /* ========= Upload directories (absolute paths) ========= */
  $uploadDirs = [
    'profiles' => BASE_DIR . '/uploads/profiles/',
    'events'   => BASE_DIR . '/uploads/events/',
  ];
  foreach ($uploadDirs as $dir) {
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
  }

  // Optional: public URLs to uploaded files
  $uploadUrls = [
    'profiles' => app_url('uploads/profiles/'),
    'events'   => app_url('uploads/events/'),
  ];
}

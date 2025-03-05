<?php
defined('ROOT_DIR') or die(header('HTTP/1.1 403 Forbidden'));

/*
 * Rainbow PHP Framework
 * Updated configuration file (2025)
 * Author: Balaji / Updated by You
 *
 * This file now sets session cookie parameters conditionally based on the environment.
 * In development (localhost) the cookie domain is left empty,
 * while in production it will be set (e.g., ".yourmaindomain.com") for proper subdomain handling.
 */

// Set environment constant (change to 'production' in production environment)
if (!defined('ENVIRONMENT')) {
    define('ENVIRONMENT', 'development'); // or 'production'
}

// Set cookie domain based on environment.
// In production, set the domain (with a leading dot) so cookies work across subdomains.
// In development (localhost) leave it empty.
if (ENVIRONMENT === 'production') {
    $cookieDomain = '.yourmaindomain.com';  // change this to your production domain
} else {
    $cookieDomain = '';
}

// Set session cookie parameters. Must be set before session_start().
session_set_cookie_params([
    'lifetime' => 0,     // session cookie lasts until the browser is closed
    'path'     => '/',   // available site-wide
    'domain'   => $cookieDomain,
    'secure'   => false, // change to true if using HTTPS in production
    'httponly' => true,
    'samesite' => 'Lax'   // or 'Strict' if needed
]);
session_start();

// System Default Error Reporting
error_reporting(1);
//error_reporting(E_ALL);
//ini_set("display_errors", "1");

// Database Configuration
require CONFIG_DIR . 'db.config.php';

// Application Configuration
require CONFIG_DIR . 'app.config.php';

// OS Directory Separator
if (!defined('D_S')) {
    define('D_S', DIRECTORY_SEPARATOR);
}

// Define Directories
// Ensure APP_DIR is defined. If not, define it as the current directory.
if (!defined('APP_DIR')) {
    define('APP_DIR', __DIR__ . D_S);
}

define('CON_DIR', APP_DIR . 'controllers' . D_S);
define('HEL_DIR', APP_DIR . 'helpers' . D_S);
define('LIB_DIR', APP_DIR . 'library' . D_S);
define('MOD_DIR', APP_DIR . 'models' . D_S);
define('PLG_DIR', APP_DIR . 'plugins' . D_S);
define('LOG_DIR', APP_DIR . 'logs' . D_S);
define('ROU_DIR', APP_DIR . 'routes' . D_S);
define('TMP_DIR', APP_DIR . 'temp' . D_S);

// Secure Hash Code (using your purchase code)
define('HASH_CODE', md5($item_purchase_code));

// mod_rewrite enabled
define("MOD_REWRITE", true);

// Language Translation enabled
define("LANG_TRANS", true);

// Application Error Reporting enabled
define("ERR_R", true);

// Error Reporting File
define("ERR_R_FILE", "error.tdata");

// Set system error log file
ini_set("error_log", LOG_DIR . ERR_R_FILE);

// Set Default Time Zone
date_default_timezone_set('Asia/Calcutta');

// Custom Router enabled
define("CUSTOM_ROUTE", true);

// Enable Plugin System
define('PLUG_SYS', false);

// CURL Settings
define('CURL_UA', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36');
define('CURL_TIMEOUT', 30);
define('LOG_CURL_ERR', false);

// Outgoing Mail Settings
define('DEFAULT_FROM_ADDRESS', '');
define('DEFAULT_FROM_NAME', '');

// Admin Path
define('ADMIN_DIR_NAME', 'admin');
define('ADMIN_PATH', ADMIN_DIR_NAME . '/');

// ------------------------------------------------------------------
// Additional definitions added by Piyush (if not already defined above)
// ------------------------------------------------------------------
if (!defined('CONT_DIR')) {
    define('CONT_DIR', APP_DIR . 'controllers' . D_S);
}
if (!defined('ROU_DIR')) {
    define('ROU_DIR', APP_DIR . 'routes' . D_S);
}

// Cloudflare Fixes
if (isset($_SERVER['HTTP_CF_VISITOR'])) {
    $visitor = json_decode($_SERVER['HTTP_CF_VISITOR']);
    if ($visitor->scheme == 'https')
        $_SERVER["HTTPS"] = 'on';
}
if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
    $_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_CF_CONNECTING_IP'];
}

// HTTP Proxy Fix
if (isset($_SERVER['HTTP_HTTPS']))
    $_SERVER["HTTPS"] = $_SERVER['HTTP_HTTPS'];
if (isset($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
    if ($_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
        $_SERVER["HTTPS"] = 'on';
}

// Fix HTTPS flag
if (isset($_SERVER["HTTPS"])) {
    if ($_SERVER["HTTPS"] === 'off')
        unset($_SERVER["HTTPS"]);
}

// GZIP Fix
if (ini_get('zlib.output_compression'))
    header('Content-Encoding: gzip');

// Determine Base URL and Active Link
// Assume $baseURL is already set in your app.config.php or similar
$parseHost = explode('/', $baseURL);
$serverHost = $parseHost[0];
$subPath = str_replace($serverHost . '/', '', $baseURL);

// Build Base URL
$protocol = (isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] === 'on') ? 'https://' : 'http://';
$www = (strpos($_SERVER['HTTP_HOST'], 'www.') === false) ? '' : 'www.';
$baseURL = $protocol . $www . $baseURL;
define('BASEURL', $baseURL);

// Active Link (current URL)
$currentLink = $protocol . $www . $serverHost . $_SERVER["REQUEST_URI"];

// Hide PHP Version
header('X-Powered-By: Rainbow Framework');

// End of configuration file

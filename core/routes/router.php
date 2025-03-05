<?php
defined('APP_NAME') or die(header('HTTP/1.1 403 Forbidden'));

// Make sure our configuration is loaded so that APP_DIR, CONT_DIR, etc. are defined.
require_once APP_DIR . 'config/config.php';

// Define the temporary directory.
define('TEMP_DIR', APP_DIR . 'temp' . D_S);

// ------------------ Subdomain Detection ------------------

// Get the HTTP host
$host = $_SERVER['HTTP_HOST'] ?? '';
$subdomainRoute = false;

if (!empty($host)) {
 // For local testing, assume subdomains follow the pattern: slug.localhost (e.g. "jaipurroutes-com.localhost")
 if (preg_match('/^([a-z0-9-]+)\.localhost$/i', $host, $matches)) {
     $slug = $matches[1];
     // Use your helper function getDomainBySlug() to fetch the domain record using the slug.
     // For example: SELECT * FROM domains_data WHERE slug = ? LIMIT 1
     $domainRecord = getDomainBySlug($con, $slug);
 
     if ($domainRecord) {
         // Set the controller and pointOut values for a subdomain route.
         $controller = 'domain';
         $pointOut = $domainRecord['domain']; // e.g. "jaipurroutes.com"
         $args = array();
         $subdomainRoute = true;
         define('SUBDOMAIN_ROUTE', true);
         log_message('debug', "Subdomain detected: $slug, loading domain record: " . $pointOut);
     }
 }
}

// ------------------ Normal Routing ------------------
if (!$subdomainRoute) {
 $controller = $route = $pointOut = null;
 $args = $custom_route = array();

 if (isset($_GET['route'])) {
     $route = escapeTrim($con, $_GET['route']);
     $route = explode('/', $route);
     
     /// ------ Load Language Data START ------ 
     if (strlen($route[0]) == 2) {
         if ($route[0] === DEFAULT_LANG)
             redirectTo(str_replace('/' . DEFAULT_LANG, '', $currentLink));
         if (isLangExists(strtolower($route[0]), $con)) {
             define('LANG_SHORT_CODE', strtolower($route[0]));
             define('ACTIVE_LANG', strtolower($route[0]));
             $lang = getLangData(LANG_SHORT_CODE, $con);
             $route = array_slice($route, 1);
         }
     } else {
         if (isset($_SESSION['twebUserSelectedLang'])) {
             // User Selected Language
             $loadLangCode = strtolower(raino_trim($_SESSION['twebUserSelectedLang']));
             define('ACTIVE_LANG', $loadLangCode);
             $lang = getLangData($loadLangCode, $con);
         } else {
             // Default Language
             $defaultLang = DEFAULT_LANG;
             define('ACTIVE_LANG', $defaultLang);
             $lang = getLangData($defaultLang, $con);
         }
     }
     /// ------ Load Language Data END ------ 
     
     if (isset($route[0]) && $route[0] != '') {
         $controller = $route[0];
         if (isset($route[1]))
             $pointOut = $route[1];
         $args = array_slice($route, 2);
         $argWithPointOut = array_slice($route, 1);
         
         if (CUSTOM_ROUTE) {
             require ROU_DIR . 'custom_router.php';
             foreach ($custom_route as $customRouteKey => $customRouteVal) {
                 $customRouteKey = explode('/', $customRouteKey);
                 if ($controller == Trim($customRouteKey[0])) {
                     if (isset($customRouteKey[1])) {
                         if ($pointOut != null) {
                             if ($customRouteKey[1] == "[:any]") {
                                 $route = explode('/', $customRouteVal);
                                 $controller = $route[0];
                                 if (isset($route[1]))
                                     $pointOut = $route[1];
                                 $args = $argWithPointOut;
                                 break;
                             } else {
                                 if ($pointOut == $customRouteKey[1]) {
                                     $route = explode('/', $customRouteVal);
                                     $controller = $route[0];
                                     if (isset($route[1]))
                                         $pointOut = $route[1];
                                     $args = array_merge(array_slice($route, 2), $args);
                                     break;
                                 }
                             }
                         }
                     } else {
                         $route = explode('/', $customRouteVal);
                         $controller = $route[0];
                         if (isset($route[1])) {
                             $pointOut = $route[1];
                             $args = array_merge(array_slice($route, 2), $argWithPointOut);
                         } else {
                             $args = array_merge(array_slice($route, 2), $args);
                         }
                         break;
                     }
                 }
             }
         }
     } else {
         $controller = CON_MAIN;
     }
 } else {
     $controller = CON_MAIN;
 }
}

// ------------------ Include the Controller ------------------

// IMPORTANT: Ensure that $controller is not empty. If it is empty, set it to a default (e.g. CON_MAIN)
if (empty($controller)) {
 $controller = CON_MAIN;
}
// Include the controller file from CONT_DIR.
// For subdomain routes, $controller is 'domain' (and CONT_DIR . 'domain.php' will be loaded).
require_once CONT_DIR . $controller . '.php';

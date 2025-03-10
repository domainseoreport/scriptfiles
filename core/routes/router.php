<?php
// router.php
defined('APP_NAME') or die(header('HTTP/1.1 403 Forbidden'));

// Load configuration so that APP_DIR, CONT_DIR, etc. are defined.
require_once APP_DIR . 'config/config.php';

// Define the temporary directory.
define('TEMP_DIR', APP_DIR . 'temp' . D_S);

// ------------------ Subdomain Detection ------------------
$host = $_SERVER['HTTP_HOST'] ?? '';
$subdomainRoute = false;

if (!empty($host)) {
    // For local testing, assume subdomains follow the pattern: slug.localhost 
    // e.g. "silverandgem-com.localhost"
    if (preg_match('/^([a-z0-9-]+)\.localhost$/i', $host, $matches)) {
        $slug = $matches[1];
        // Try to fetch the domain record using the slug.
        $domainRecord = getDomainBySlug($con, $slug);
        $subdomainRoute = true;
        define('SUBDOMAIN_ROUTE', true);
        
        // Default controller for subdomain requests is "domain"
        $controller = 'domain';
        if ($domainRecord) {
            $pointOut = $domainRecord['domain']; // e.g. "jaipurroutes.com"
        } else {
            // Fallback: assume the slug represents the domain, replacing dashes with dots.
            $pointOut = str_replace('-', '.', $slug);
        }
        
        // Parse the additional URI segments from REQUEST_URI.
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $uriPath = parse_url($uri, PHP_URL_PATH);
        $uriParts = array_filter(explode('/', trim($uriPath, '/')));
        $route = array_values($uriParts); // reindex numerically
        $args = []; // additional arguments if needed
        log_message('debug', "Subdomain detected: $slug, using domain: " . $pointOut);
        
        // If the first route segment is "domains", then force controller "domains" (for AJAX requests).
        if (!empty($route) && strtolower(raino_trim($route[0])) === 'domains') {
            $controller = 'domains';
            log_message('debug', "AJAX route detected: forcing controller 'domains'.");
            // Optionally, remove "domains" from the route array:
            array_shift($route);
        }
        // Else, if the first segment is "update", then set update flag.
        elseif (!empty($route) && strtolower(raino_trim($route[0])) === 'update') {
            $updateFound = true;
            log_message('debug', "Update flag set (subdomain route).");
            // Remove "update" from route if additional segments exist.
            if (count($route) > 1) {
                array_shift($route);
            }
        }
    }
}

// ------------------ Normal Routing ------------------
if (!$subdomainRoute) {
    // No subdomain detected; use standard (GET‑based) routing.
    $controller = $route = $pointOut = null;
    $args = $custom_route = array();

    if (isset($_GET['route'])) {
        $route = escapeTrim($con, $_GET['route']);
        $route = explode('/', $route);
        
        // ------ Load Language Data START ------
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
                $loadLangCode = strtolower(raino_trim($_SESSION['twebUserSelectedLang']));
                define('ACTIVE_LANG', $loadLangCode);
                $lang = getLangData($loadLangCode, $con);
            } else {
                $defaultLang = DEFAULT_LANG;
                define('ACTIVE_LANG', $defaultLang);
                $lang = getLangData($defaultLang, $con);
            }
        }
        // ------ Load Language Data END ------

        if (isset($route[0]) && $route[0] != '') {
            $controller = $route[0];
            if (isset($route[1])) {
                $pointOut = $route[1];
            }
            $args = array_slice($route, 2);
            $argWithPointOut = array_slice($route, 1);
            
            if (CUSTOM_ROUTE) {
                require ROU_DIR . 'custom_router.php';
                foreach ($custom_route as $customRouteKey => $customRouteVal) {
                    $customRouteKey = explode('/', $customRouteKey);
                    if ($controller == trim($customRouteKey[0])) {
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

// Ensure that $controller is not empty.
if (empty($controller)) {
    $controller = CON_MAIN;
}
// echo "Controller: " . $controller . "<br>";
// die();  
// Include the controller file from CONT_DIR.
// For subdomain routes, the controller will be either "domain" (for report/update) or "domains" (for AJAX).
require_once CONT_DIR . $controller . '.php';

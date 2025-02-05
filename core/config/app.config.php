<?php
defined('ROOT_DIR') or die(header('HTTP/1.1 403 Forbidden'));

/*
 * @author Balaji
 * @name: Rainbow PHP Framework
 * @copyright 2024 ProThemes.Biz
 *
 */
 
// --- Application Settings ---

//Define Application Name
define('APP_NAME','Turbo Website Reviewer');
define('HTML_APP_NAME','<b>Turbo</b> Reviewer');

//Define Version Number of Application
define('VER_NO','3.1');

//Define Native App Name
define('N_APP','tweb');

//Define Native App Name
define('NATIVE_APP_NAME','');

//Define Native Sign
define('NATIVE_SIGN','');

//Define Native Application Sign
define('NATIVE_APP_SIGN','');

//Set Default Controller
define('CON_MAIN','main');

//Set Default Error Controller
define('CON_ERR','error');

//MySQLi Error Reporting
mysqli_report(MYSQLI_REPORT_ALL ^ MYSQLI_REPORT_STRICT);
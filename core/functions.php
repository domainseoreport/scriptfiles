<?php
define('LOG_PATH', LOG_DIR); 
require_once dirname(__DIR__) . '/vendor/autoload.php';
/**
 * @author Balaji
 * @name: Rainbow PHP Framework
 * @copyright 2024 ProThemes.Biz
 *
 */

function dbConncet($mysql_host,$mysql_user,$mysql_pass,$mysql_database){
    $con = mysqli_connect($mysql_host,$mysql_user,$mysql_pass,$mysql_database);
    if (mysqli_connect_errno())
        stop("Unable to connect to Mysql Server");
    mysqli_set_charset($con,"utf8");
    mysqli_query($con, "SET time_zone = '".date('P')."'");
    return $con; 
}

function isValidUsername($str){
    return !preg_match('/[^A-Za-z0-9.#\\-$]/', $str);
}

function isValidEmail($email){
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function isValidSite($site) {
    return !preg_match('/^[a-z0-9\-]+\.[a-z]{2,100}(\.[a-z]{2,14})?$/i', $site);
}

function isValidIPv4($ip){
    if(filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) 
        return true;
    return false;
}

function isValidIPv6($ip){
    if(filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6))
        return true;
    return false;
}

if(!function_exists('str_contains')) {
    function str_contains($data, $searchString, $ignoreCase = false){
        if ($ignoreCase) {
            $data = strtolower($data);
            $searchString = strtolower($searchString);
        }
        $needlePos = strpos($data, $searchString);
        return ($needlePos === false ? false : ($needlePos + 1));
    }
}

function check_str_contains($data, $searchString, $ignoreCase = false){
    if ($ignoreCase) {
        $data = strtolower($data);
        $searchString = strtolower($searchString);
    }
    $needlePos = strpos($data, $searchString);
    return ($needlePos === false ? false : ($needlePos + 1));
}

function raino_trim($str){
    $str = Trim(htmlspecialchars($str));
    return $str;
}

function randomPassword(){
    $alphabet = "abcdefghijklmnopqrstuwxyzABCDEFGHIJKLMNOPQRSTUWXYZ0123456789";
    $pass = array();
    $alphaLength = strlen($alphabet) - 1;
    for ($i = 0; $i < 9; $i++) {
        $n = rand(0, $alphaLength);
        $pass[] = $alphabet[$n];
    }
    return implode($pass);
}

function escapeMe($con,$data){
     return mysqli_real_escape_string($con, $data);
}

function escapeTrim($con,$data){
     $data = Trim(htmlspecialchars($data));
     return mysqli_real_escape_string($con, $data);
}

function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

function formatBytesWithUnit($bytes, $unit = "", $decimals = 2, $returnNoUnit=false) {
    $units = array('B' => 0, 'KB' => 1, 'MB' => 2, 'GB' => 3, 'TB' => 4, 'PB' => 5, 'EB' => 6, 'ZB' => 7, 'YB' => 8);

    $value = 0;
    if ($bytes > 0) {
        if (!array_key_exists($unit, $units)) {
            $pow = floor(log($bytes)/log(1024));
            $unit = array_search($pow, $units);
        }
        $value = ($bytes/pow(1024,floor($units[$unit])));
    }
    if (!is_numeric($decimals) || $decimals < 0) {
        $decimals = 2;
    }
    if($returnNoUnit)
        return sprintf('%.' . $decimals . 'f', $value);
    return sprintf('%.' . $decimals . 'f '.$unit, $value);
}

function file_upload_max_size() {
    static $max_size = -1;
    if ($max_size < 0) {
        $post_max_size = parse_size(ini_get('post_max_size'));
        if ($post_max_size > 0)
            $max_size = $post_max_size;

        $upload_max = parse_size(ini_get('upload_max_filesize'));
        if ($upload_max > 0 && $upload_max < $max_size)
            $max_size = $upload_max;
    }
    return $max_size;
}

function parse_size($size) {
    $unit = preg_replace('/[^bkmgtpezy]/i', '', $size);
    $size = preg_replace('/[^0-9\.]/', '', $size);
    if ($unit)
        return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])));
    else
        return round($size);
}

function roundSize($size){
    $i = 0;
    $iec = array("B", "Kb", "Mb", "Gb", "Tb");
    while (($size / 1024) > 1) {
        $size = $size / 1024;
        $i++;
    }
    return (round($size, 1) . " " . $iec[$i]);
}

function encrypt($string,$secretKey,$secretIv) {
    $encrypt_method = "AES-256-CBC";
    $key = hash('sha256', $secretKey);
    $iv = substr(hash('sha256', $secretIv), 0, 16);
    $output = openssl_encrypt($string, $encrypt_method, $key, 0, $iv);
    $output = base64_encode($output);
    return $output;
}

function decrypt($encryptedString,$secretKey,$secretIv) {
    $encrypt_method = "AES-256-CBC";
    $key = hash('sha256', $secretKey);
    $iv = substr(hash('sha256', $secretIv), 0, 16);
    $output = openssl_decrypt(base64_decode($encryptedString), $encrypt_method, $key, 0, $iv);
    return $output;
}

function truncate($input, $maxWords, $maxChars){
    $words = preg_split('/\s+/', $input);
    $words = array_slice($words, 0, $maxWords);
    $words = array_reverse($words);

    $chars = 0;
    $truncated = array();

    while(count($words) > 0) {
        $fragment = trim(array_pop($words));
        $chars += strlen($fragment);

        if($chars > $maxChars) break;

        $truncated[] = $fragment;
    }

    $result = implode(' ', $truncated);

    return $result . ($input == $result ? '' : '...');
}

function strInt($input) {
    $output = null;
    $inputlen = strlen($input);
    $randkey = rand(1, 9);
 
    $i = 0;
    while ($i < $inputlen){
        $inputchr[$i] = (ord($input[$i]) - $randkey);
        $i++;
    }
    
    $output = implode('.', $inputchr) . '.' . (ord($randkey)+50);
    return $output;
}

function intStr($input) {
  $output = null;
  $input_count = strlen($input);
 
  $dec = explode(".", $input);
  $x = count($dec);
  $y = $x-1;
 
  $calc = $dec[$y]-50;
  $randkey = chr($calc);
 
  $i = 0;
 
  while ($i < $y) {
 
    $array[$i] = $dec[$i]+$randkey;
    $output .= chr($array[$i]);
 
    $i++;
  };
  return $output;
}

function makeUrlFriendly($input){
    $output = preg_replace("/\s+/" , "_" , raino_trim($input));
    $output = preg_replace("/\W+/" , "" , $output);
    $output = preg_replace("/_/" , "-" , $output);
    return strtolower($output);
}

function rgb2hex(array $rgb=array(0,0,0)){
    $hex = '#';
    $hex .= str_pad(dechex($rgb[0]), 2, "0", STR_PAD_LEFT);
    $hex .= str_pad(dechex($rgb[1]), 2, "0", STR_PAD_LEFT);
    $hex .= str_pad(dechex($rgb[2]), 2, "0", STR_PAD_LEFT);
    return $hex;
}

function hex2rgb($hex){
    $hex = str_replace('#', '', $hex);
    if (strlen($hex) == 3) {
        $r = hexdec(substr($hex, 0, 1) . substr($hex, 0, 1));
        $g = hexdec(substr($hex, 1, 1) . substr($hex, 1, 1));
        $b = hexdec(substr($hex, 2, 1) . substr($hex, 2, 1));
    } else {
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
    }
    $rgb ="$r,$g,$b";
    return $rgb;
}

function getFrameworkVersion() {
    return '1.4';
}

function getServerMemoryUsage(){
    $memory_usage = 'Unavailable';
    if(function_exists('shell_exec')) {
        $free = shell_exec('free');
        if (!nullCheck($free)) {
            $free = (string)trim($free);
            $free_arr = explode("\n", $free);
            $mem = explode(" ", $free_arr[1]);
            $mem = array_filter($mem);
            $mem = array_merge($mem);
            $memory_usage = round($mem[2] / $mem[1] * 100);
        }
    }
    return $memory_usage;
}

function getServerCpuUsage() {
    if(function_exists('sys_getloadavg')){
        $load = sys_getloadavg();
        return $load[0];
    }else {
        return 'Unavailable';
    }
}

function clean_url($site) {
    $site = strtolower($site);
    $site = str_replace(array(
        'http://',
        'https://',
        'www.'), '', $site);
    return $site;
}

function clean_with_www($site) {
    $site = strtolower($site);
    $site = str_replace(array(
        'http://',
        'https://'), '', $site);
    return $site;
}

function getTimeZone(){
    return date_default_timezone_get();
}

function setTimeZone($value) {
    date_default_timezone_set($value);
    return true;
}

function getDaysOnThisMonth($month = 5, $year = '2015'){
  if ($month < 1 OR $month > 12) {
	  return 0;
  }

  if ( ! is_numeric($year) OR strlen($year) != 4) {
	  $year = date('Y');
  }

  if ($month == 2) {
	  if ($year % 400 == 0 OR ($year % 4 == 0 AND $year % 100 != 0)) {
		  return 29;
	  }
  }

  $days_in_month	= array(31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31);
  return $days_in_month[$month - 1];
}
 
function getDomainName($site){
    $site = clean_url($site);
    $site = parse_url('http://'.trim($site));
    return $site['host'];
}

function getUserIP(){
    $ip = '127.0.0.1';
    if(isset($_SERVER['REMOTE_ADDR'])) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }elseif (!empty($_SERVER['HTTP_CLIENT_IP'])){
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])){
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        $tmp = explode(',',$ip);
        $ip = end($tmp);
    }
    if(filter_var($ip, FILTER_VALIDATE_IP)) 
        return $ip;
    else
        return '';
}

function getUA(){
    return raino_trim($_SERVER ['HTTP_USER_AGENT']);
}

function getUserLang($default='en'){
	if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
		$langs = explode(',',$_SERVER['HTTP_ACCEPT_LANGUAGE']);

		foreach ($langs as $value){
			$choice=substr($value,0,2);
            return $choice;
		}
	} 
	return $default;
}

function delDir($dir) {
    $files = array_diff(scandir($dir), array('.', '..'));

    foreach ($files as $file)
    {
        (is_dir("$dir/$file")) ? delDir("$dir/$file") : unlink("$dir/$file");
    }
    rmdir($dir);
    return 1;
}

function delFile($file){
    return unlink($file);
}

function getCenterText($str1,$str2,$data){
    $data = explode($str1,$data);
    $data = explode($str2,$data[1]);
    return Trim($data[0]);
}

function nullCheck($str){
    $str = strtolower($str);
    if($str == 'none' || $str == 'null' || $str == 'n/a' || $str == '' || $str == null)
        return true;
    else
        return false;
}

function copyDir($src,$dst) {
    $dir = opendir($src);
    @mkdir($dst);
    while(false !== ( $file = readdir($dir)) ) {
        if (( $file != '.' ) && ( $file != '..' )) {
            if ( is_dir($src . '/' . $file) ) {
                copyDir($src . '/' . $file,$dst . '/' . $file);
            }
            else {
                if(!copy($src . '/' . $file,$dst . '/' . $file)){
                    //Error - File Copy Failed!
                }
            }
        }
    }
    closedir($dir);
}

function fixSpecialChar($plainTxt){
    return mb_convert_encoding($plainTxt, 'UTF-8', 'UTF-8');
}

function getLastID($con,$table) {
    $table = escapeTrim($con,$table);
    $query = "SELECT @last_id := MAX(id) FROM $table";
    $result = mysqli_query($con, $query);
    $row = mysqli_fetch_array($result);
    $last_id = $row['@last_id := MAX(id)'];
    return $last_id;
}

function getMyData($site){
    return file_get_contents($site);
}

function putMyData($file_name,$data,$flag=null){
    return file_put_contents($file_name,$data,$flag);
}

function baseURL($atRoot=FALSE, $atCore=FALSE, $parse=FALSE){
    if (isset($_SERVER['HTTP_HOST'])) {
        $http = isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off' ? 'https' : 'http';
        $hostname = $_SERVER['HTTP_HOST'];
        $dir =  str_replace(basename($_SERVER['SCRIPT_NAME']), '', $_SERVER['SCRIPT_NAME']);

        $core = preg_split('@/@', str_replace($_SERVER['DOCUMENT_ROOT'], '', realpath(dirname(__FILE__))), NULL, PREG_SPLIT_NO_EMPTY);
        $core = $core[0];

        $tmplt = $atRoot ? ($atCore ? "%s://%s/%s/" : "%s://%s/") : ($atCore ? "%s://%s/%s/" : "%s://%s%s");
        $end = $atRoot ? ($atCore ? $core : $hostname) : ($atCore ? $core : $dir);
        $base_url = sprintf( $tmplt, $http, $hostname, $end );
    }
    else $base_url = 'http://localhost/';

    if ($parse) {
        $base_url = parse_url($base_url);
        if (isset($base_url['path'])) if ($base_url['path'] == '/') $base_url['path'] = '';
    }

    return $base_url;
}

function passwordHash($str=null){
    if($str === NULL)
        $str =rand(1,999999);
    return md5(crypt(Md5($str),HASH_CODE));
}

function redirectTo($path){
    header('Location: '. $path);
    exit();
}

function redirectToWithMeta($path,$sec=1){
    header('Location: '. $path);
    echo '<meta http-equiv="refresh" content="'.$sec.';url='.$path.'">';
    exit();
}

function array_map_recursive($callback, $array) {
    foreach ($array as $key => $value) {
        if (is_array($array[$key])) {
            $array[$key] = array_map_recursive($callback, $array[$key]);
        }
        else {
            $array[$key] = call_user_func($callback, $array[$key]);
        }
    }
    return $array;
}

function metaRefresh($path=null,$sec=1,$exit=false){
    if($path!=null)
    echo '<meta http-equiv="refresh" content="'.$sec.';url='.$path.'">';
    else
    echo '<meta http-equiv="refresh" content="'.$sec.'">';
    if($exit)
    exit();
    else
    return true;
}

function stop($msg=null,$disMsg=true,$logMsg=true){
    if(ERR_R){
        if($logMsg){
            if($msg != null){
                $msgWithDate = '['. date('d-M-Y H:i:s') . ' ' . getTimeZone() .']' . " App Notice:  " . $msg . " | Request From ". getUserIP();
                $errFile = LOG_DIR.ERR_R_FILE;
                putMyData($errFile,$msgWithDate."\r\n\n",FILE_APPEND);
            }
        }
    }
    if($disMsg)
        die("$msg"); 
    else
        die();
}

function writeLog($msg=null){
    if(ERR_R){
            if($msg != null){
                $msgWithDate = '['. date('d-M-Y H:i:s') . ' ' . getTimeZone() .']' . " App Notice:  " . $msg . " | Request From ". getUserIP();
                $errFile = LOG_DIR.ERR_R_FILE;
                putMyData($errFile,$msgWithDate."\r\n\n",FILE_APPEND);
            }
    }
}

function simpleCurlGET($url){
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_VERBOSE, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, CURL_TIMEOUT);
    curl_setopt($ch, CURLOPT_TIMEOUT, CURL_TIMEOUT);
    $html=curl_exec($ch);
    if(LOG_CURL_ERR) {
        if (curl_errno($ch))
            writeLog('CURL ERROR | URL: '.$url.' | Error Message: '.curl_error($ch));
    }
    curl_close($ch);
    return $html;
}

function curlGET($url,$ref_url = "https://www.google.com/",$agent = CURL_UA){
    $cookie = TMP_DIR.unqFile(TMP_DIR, randomPassword().'_curl.tdata');
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, $agent);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 100);
    curl_setopt($ch, CURLOPT_HTTPHEADER, Array(
        "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8",
        "Accept-Language: en-US,en;q=0.5",
        "Accept-Encoding: gzip, deflate",
    ));
    curl_setopt($ch, CURLOPT_VERBOSE, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, CURL_TIMEOUT);
    curl_setopt($ch, CURLOPT_TIMEOUT, CURL_TIMEOUT);
    curl_setopt($ch, CURLOPT_REFERER, $ref_url);
    curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
    $html=curl_exec($ch);
    if(LOG_CURL_ERR) {
        if (curl_errno($ch))
            writeLog('CURL ERROR | URL: '.$url.' | Error Message: '.curl_error($ch));
    }
    curl_close($ch);
    return $html;
}

function curlGETDebug($url){
    $cookie = TMP_DIR.unqFile(TMP_DIR, randomPassword().'_curl.tdata');
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, CURL_UA);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 100);
    curl_setopt($ch, CURLOPT_HTTPHEADER, Array(
        "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8",
        "Accept-Language: en-US,en;q=0.5",
        "Accept-Encoding: gzip, deflate",
    ));
    curl_setopt($ch, CURLOPT_VERBOSE, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, CURL_TIMEOUT);
    curl_setopt($ch, CURLOPT_TIMEOUT, CURL_TIMEOUT);
    curl_setopt($ch, CURLOPT_REFERER, BASEURL);
    curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
    $html=curl_exec($ch);
    if (curl_errno($ch))
        $html = curl_error($ch);
    curl_close($ch);
    return $html;
}

function curlPOST($url,$post_data,$ref_url = "https://www.google.com/",$agent = CURL_UA){
    $cookie = TMP_DIR.unqFile(TMP_DIR, randomPassword().'_curl.tdata');
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, CURL_TIMEOUT);
    curl_setopt($ch, CURLOPT_TIMEOUT, CURL_TIMEOUT);
    curl_setopt($ch, CURLOPT_USERAGENT, $agent);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, Array(
        "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8",
        "Accept-Language: en-US,en;q=0.5",
        "Accept-Encoding: gzip, deflate",
    ));
    curl_setopt($ch, CURLOPT_VERBOSE, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_REFERER, $ref_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS,$post_data);
    curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
    $html=curl_exec($ch);
    if(LOG_CURL_ERR) {
        if (curl_errno($ch))
            writeLog('CURL ERROR | URL: '.$url.' | Error Message: '.curl_error($ch));
    }
    curl_close($ch);
    return $html;
}

function getHeaders($site) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_URL, $site);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, CURL_TIMEOUT);
    curl_setopt($ch, CURLOPT_TIMEOUT, CURL_TIMEOUT);
    curl_setopt($ch, CURLOPT_USERAGENT, CURL_UA);
    $headers=curl_exec($ch);
    curl_close($ch);
    return $headers;
}

function getHttpCode($site,$followRedirect=true) {
    $ch = curl_init($site);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, CURL_TIMEOUT);
    curl_setopt($ch, CURLOPT_TIMEOUT, CURL_TIMEOUT);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, $followRedirect);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_USERAGENT, CURL_UA);
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $httpCode;
}

function getHeader($myheader) {
  if (isset($_SERVER[$myheader])) {
    return $_SERVER[$myheader];
  } else {
    if(function_exists('apache_request_headers') ) {
    $headers = apache_request_headers();
    if (isset($headers[$myheader])) {
      return $headers[$myheader];
    }
    }
  }
  return '';
}

function createZip($source,$des,$filename) {
    $filename = str_replace(".zip","",$filename);
    $zip = new ZipArchive();
    $zip->open($des.$filename.".zip", ZipArchive::CREATE);
    if (is_dir($source) === true){
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source), RecursiveIteratorIterator::SELF_FIRST);
    foreach ($files as $file){
        if (is_dir($file) === true){
            
        }else if (is_file($file) === true){
            $zip->addFromString(str_replace($source . '/', '', $file), getMyData($file));
        }
    }
    }
    $zip->close();
    return true;
}

function extractZip($sourceFile,$desPath){
    $zip = new ZipArchive;
    $res = $zip->open($sourceFile);
    if ($res === TRUE) {
        $zip->extractTo($desPath);
        $zip->close();
        return true;
    } else {
        return false;
    }
}

if(!function_exists('disk_total_space')){
    function disk_total_space($var){
        return 0;
    }
}

if(!function_exists('disk_free_space')){
    function disk_free_space($var){
        return 0;
    }
}

function classAutoLoader($class){
    $filepath = MOD_DIR.$class.'.php';
    $filepath1 = MOD_DIR.strtolower($class).'.php';
    if(file_exists($filepath)){
        if(is_file($filepath)&&!class_exists($class)) require $filepath;
    } elseif(file_exists($filepath1)) {
        if(is_file($filepath1)&&!class_exists($class)) require $filepath1;
    }
}
spl_autoload_register('classAutoLoader');

foreach (glob(HEL_DIR."*{_helper,_help}.php",GLOB_BRACE) as $filename) {
    if(file_exists($filename))
        require $filename;
}

function slugify($text)
{
    // Convert to lowercase and trim spaces.
    $text = strtolower(trim($text));
    // Replace non-alphanumeric characters with hyphens.
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    // Remove any leading or trailing hyphens.
    $slug = trim($text, '-'); 
    return $slug;
}

/**
 * Check if the domain is accessible via HTTP/HTTPS.
 * It tests various URL variations and returns the best accessible URL.
 *
 * @param string $domain
 * @return string|null
 */
function isDomainAccessible($domain)
{
    // If a full URL is passed, extract the host.
    if (filter_var($domain, FILTER_VALIDATE_URL)) {
        $parsedUrl = parse_url($domain);
        if (isset($parsedUrl['host'])) {
            $domain = $parsedUrl['host'];
        }
    }

    // Use checkdnsrr to verify that the domain has either an A or AAAA record.
    if (!checkdnsrr($domain, 'A') && !checkdnsrr($domain, 'AAAA')) {
        log_message('debug', "DNS resolution failed for domain: {$domain}");
        return null;
    }
    
    $protocols    = ['https://', 'http://'];
    $timeout      = 10;
    $maxRedirects = 5;
    $userAgent    = 'Mozilla/5.0 (compatible; SEO-Checker/1.0)';

    // For simple domains (e.g., example.com), also check www variations.
    $domainParts = explode('.', $domain);
    $checkWWW = (count($domainParts) <= 2);

    $checkedUrls   = [];
    $effectiveUrls = [];
     
    // Test each protocol and its variations.
    foreach ($protocols as $protocol) {
        // Always check the domain as-is.
        $variations = [$protocol . $domain];
        if ($checkWWW) {
            // Also test with the www prefix.
            $variations[] = $protocol . 'www.' . $domain;
        }

        log_message('debug', "Checked variations: " . print_r($variations, true));
        foreach ($variations as $url) {
            if (in_array($url, $checkedUrls)) {
                continue;
            }
            $checkedUrls[] = $url;

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => $timeout,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => $maxRedirects,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_USERAGENT      => $userAgent,
                CURLOPT_NOBODY         => false, // Perform a full GET request.
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
            $curlError = curl_error($ch);
            $redirectCount = curl_getinfo($ch, CURLINFO_REDIRECT_COUNT);
            curl_close($ch);

            log_message('debug', "Checked: {$url} => {$effectiveUrl} [{$httpCode}]");

            // If no cURL error and HTTP status is acceptable.
            if (!$curlError && $httpCode >= 200 && $httpCode < 400) {
                $effectiveUrl = rtrim($effectiveUrl, '/');
                $effectiveUrls[$effectiveUrl] = [
                    'https'     => (parse_url($effectiveUrl, PHP_URL_SCHEME) === 'https'),
                    'www'       => (strpos(parse_url($effectiveUrl, PHP_URL_HOST), 'www.') === 0),
                    'redirects' => $redirectCount,
                ];
            }
        }
    }
   
    // Sort accessible URLs based on HTTPS preference, then www preference (favoring www), then fewer redirects.
    uasort($effectiveUrls, function($a, $b) {
        // Compare HTTPS status.
        if ($a['https'] !== $b['https']) {
            return ($b['https'] === true) ? -1 : 1;
        }
        // Compare "www" status (favor URLs with "www").
        if ($a['www'] !== $b['www']) {
            return ($b['www'] === true) ? -1 : 1;
        }
        // Compare number of redirects: fewer redirects are preferred.
        if ($a['redirects'] == $b['redirects']) {
            return 0;
        }
        return ($a['redirects'] < $b['redirects']) ? -1 : 1;
    });
    
    if (!empty($effectiveUrls)) {
        $bestUrl = array_key_first($effectiveUrls);
        log_message('debug', "Selected best URL: {$bestUrl}");
        return $bestUrl;
    }

    log_message('debug', "No accessible URL found for domain: {$domain}");
    return null;
}



if (!function_exists('log_message')) {
    /**
     * Write a log message to a log file.
     *
     * @param string $level   The log level (e.g., 'debug', 'info', 'error').
     * @param string $message The message to log.
     */
    function log_message($level, $message)
    {
        // Use a defined constant for log path if available; otherwise, use a default path.
        $logPath = defined('LOG_PATH') ? LOG_PATH : __DIR__ . '/../logs/';

        // Ensure the log directory exists; if not, create it.
        if (!is_dir($logPath)) {
            mkdir($logPath, 0777, true);
        }

        // Prepare the log message.
        $date = date('Y-m-d H:i:s');
        $logMessage = "[$date] [$level] $message" . PHP_EOL;

        // Write (append) the message to app.log.
        file_put_contents($logPath . 'app.log', $logMessage, FILE_APPEND);
    }
}


/**
 * Fetch HTML content using multiple fallback methods:
 *  1) Custom curlGET() function.
 *  2) file_get_contents() if allow_url_fopen is enabled.
 *  3) Guzzle HTTP client (if available via Composer).
 *
 * @param string $url      The target URL.
 * @param string $ref_url  The referrer URL (default is "https://www.google.com/").
 * @param string $agent    The user agent string (default defined by CURL_UA constant).
 * @return string|false    Returns the fetched HTML content, or false if all methods fail.
 */
function robustFetchHtml($url, $ref_url = "https://www.google.com/", $agent = CURL_UA) {
    // 1. Try using the custom curlGET() function.
    $html = curlGET($url, $ref_url, $agent);
    if ($html !== false && !empty($html)) {
        return $html;
    }

    // 2. Fallback to file_get_contents() if allow_url_fopen is enabled.
    if (ini_get('allow_url_fopen')) {
        $contextOptions = [
            "http" => [
                "method" => "GET",
                "header" => "User-Agent: " . $agent . "\r\n" . 
                            "Referer: " . $ref_url . "\r\n" .
                            "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8\r\n"
            ]
        ];
        $context = stream_context_create($contextOptions);
        $html = @file_get_contents($url, false, $context);
        if ($html !== false && !empty($html)) {
            return $html;
        }
    }

    // 3. Fallback to using Guzzle if it is available.
    if (class_exists('GuzzleHttp\Client')) {
        try {
            $client = new \GuzzleHttp\Client(['timeout' => 10]);
            $response = $client->request('GET', $url, [
                'headers' => [
                    'User-Agent'        => $agent,
                    'Referer'           => $ref_url,
                    'Accept-Encoding'   => 'gzip, deflate',
                    'Accept'            => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8'
                ]
            ]);
            if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 400) {
                return (string) $response->getBody();
            }
        } catch (\Exception $e) {
            // Optionally log the exception using your custom logging function.
            writeLog('CURL ERROR | Guzzle Fallback: ' . $e->getMessage());
        }
    }

    // If all methods fail, return false.
    return false;
}

/**
 * Filter out pages containing restricted words in title, description, or keywords.
 *
 * @param string $html      The HTML source data.
 * @param array  $badLists  An array of bad words arrays; index 1 for title, 2 for description, 3 for keywords.
 * @return bool             Returns true if bad words are found; otherwise, false.
 */
function hasRestrictedWords($html, $badLists) {
    $doc = new DOMDocument();
    // Suppress warnings if HTML is not well formed.
    @$doc->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
    
    // Get title.
    $title = '';
    $nodes = $doc->getElementsByTagName('title');
    if ($nodes->length > 0) {
        $title = strtolower($nodes->item(0)->nodeValue);
    }
    
    // Get meta description and keywords.
    $description = $keywords = '';
    $metas = $doc->getElementsByTagName('meta');
    foreach ($metas as $meta) {
        $name = strtolower($meta->getAttribute('name'));
        if ($name === 'description') {
            $description = strtolower($meta->getAttribute('content'));
        } elseif ($name === 'keywords') {
            $keywords = strtolower($meta->getAttribute('content'));
        }
    }
    
    // Check bad words in title.
    foreach ($badLists[1] as $badWord) {
        if (stripos($title, trim($badWord)) !== false) {
            return true;
        }
    }
    // Check in description.
    foreach ($badLists[2] as $badWord) {
        if (stripos($description, trim($badWord)) !== false) {
            return true;
        }
    }
    // Check in keywords.
    foreach ($badLists[3] as $badWord) {
        if (stripos($keywords, trim($badWord)) !== false) {
            return true;
        }
    }
    return false;
}

/**
 * Insert a new domain record along with meta data (using serBase encryption).
 *
 * @param resource $con         Your database connection.
 * @param string   $domainStr   The domain name.
 * @param string   $nowDate     The current date.
 * @param string   $title       Page title.
 * @param string   $description Page description.
 * @param string   $keywords    Page keywords.
 * @return mixed                Returns true on success, or error message on failure.
 */
function createDomainRecord($con, $domainStr,$accessurl,$nowDate, $title, $description, $keywords) {

    $meta['title']=trim($title);
    $meta['description'] = trim($description);
    $meta['keywords'] = trim($keywords);
    // Encrypt the meta data using serBase()
    $metaEncrypted = jsonEncode($meta);
 
    $data = [
        'domain'    => $domainStr,
        'domain_access_url' => $accessurl,
        'slug'      => slugify($domainStr),
        'date'      => $nowDate,
        'meta_data' => $metaEncrypted // Storing the encrypted meta data
    ];
   

    // Check if there is an error returned from the insert.
    $error = insertToDbPrepared($con, 'domains_data', $data);
    if ($error !== '') {
        return trans('Database Error - Contact Support!', 'Error Message', true);
    }
    
    return true;
}


/**
 * Extract basic meta data (title, description, keywords) from HTML content.
 *
 * @param string $html The HTML source data.
 * @return array       Returns an array with extracted meta data.
 */
function extractMetaData($html) {
    $metaData = [
        'title'       => '',
        'description' => '',
        'keywords'    => ''
    ];

    $doc = new DOMDocument();
    @$doc->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));

    // Get title
    $nodes = $doc->getElementsByTagName('title');
    if ($nodes->length > 0) {
        $metaData['title'] = strtolower($nodes->item(0)->nodeValue);
    }

    // Get meta description and keywords
    $metas = $doc->getElementsByTagName('meta');
    foreach ($metas as $meta) {
        $name = strtolower($meta->getAttribute('name'));
        if ($name === 'description') {
            $metaData['description'] = strtolower($meta->getAttribute('content'));
        } elseif ($name === 'keywords') {
            $metaData['keywords'] = strtolower($meta->getAttribute('content'));
        }
    }

    return $metaData;
}

// A simple helper function for suggestions:
    function getHeadingSuggestion($tag, $count) {
        // Return a suggestion string based on tag & count
        $tagUpper = strtoupper($tag);
        if ($count === 0) {
            if ($tag === 'h1') {
                return "No {$tagUpper} found. We recommend having at least one H1 tag for SEO.";
            } else {
                return "No {$tagUpper} found. Consider adding if needed for structure.";
            }
        }
        // Example: If user has more than 2 H1 tags
        if ($tag === 'h1' && $count > 2) {
            return "You have more than 2 H1 headings. Typically, only one H1 is recommended.";
        }
        // Otherwise, no special message
        return "Looks good for {$tagUpper}.";
    }


    function processUrlInput($input) {
        $input = raino_trim($input);
        log_message('debug', "Received URL input: $input");
        // Clean and enforce lowercase (clean_url removes protocol and www)
        $cleaned = clean_url($input);
        // If protocol is missing, prepend http://
        if (!preg_match('#^https?://#i', $input)) {
            $fullUrl = 'http://' . $cleaned;
        } else {
            $fullUrl = $input;
        }
        log_message('debug', "Full URL after cleaning: $fullUrl");
        if (!filter_var($fullUrl, FILTER_VALIDATE_URL)) {
            return false;
        }
        $parsed = parse_url($fullUrl);
        if (empty($parsed['host'])) {
            return false;
        }
        // Normalize host: remove www and force lowercase.
        $host = strtolower(str_replace('www.', '', $parsed['host']));
        return [
            'fullUrl' => $fullUrl,
            'parsed'  => $parsed,
            'host'    => $host
        ];
    }
    
    /**
     * Checks for banned or restricted domains.
     */
    function enforceDomainRestrictions($domainStr, $restrictionList) {
        // $restrictionList[4] contains banned domains.
        if (in_array($domainStr, $restrictionList[4])) {
            log_message('error', "Domain restricted (banned): $domainStr");
            redirectTo(createLink('warning/restricted-domains/' . $domainStr, true));
            die();
        }
        // Check for restricted words.
        foreach ($restrictionList[0] as $badWord) {
            if (check_str_contains($domainStr, trim($badWord), true)) {
                log_message('error', "Domain contains restricted word: $badWord in $domainStr");
                redirectTo(createLink('warning/restricted-words', true));
                die();
            }
        }
    }
    
    /**
     * Fetch the HTML content using robustFetchHtml() with a fallback to getMyData().
     */
    function fetchHtmlContent($url) {
        $html = robustFetchHtml($url);
        if ($html === false || empty($html)) {
            $html = getMyData($url);
        }
        return $html;
    }
    
    /**
     * Centralized error handler.
     */
    function handleErrorAndRedirect($msg, $langKey) {
        log_message('error', $msg);
        $_SESSION['TWEB_CALLBACK_ERR'] = trans('Input Site is not valid!', $langKey, true);
        redirectTo(createLink('', true));
        die();
    }
    
    function getDomainBySlug($con, $slug) {
        $sql = "SELECT * FROM domains_data WHERE slug = ? LIMIT 1";
        $stmt = mysqli_prepare($con, $sql);
        mysqli_stmt_bind_param($stmt, 's', $slug);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $domainRecord = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        return $domainRecord;
    }
    
    function getDomainByHost($con, $host) {
        $query = "SELECT * FROM domains_data WHERE domain = ? LIMIT 1";
        $result = mysqliPreparedQuery($con, $query, 's', array($host));
        return $result !== false ? $result : false;
    }
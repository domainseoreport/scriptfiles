<?php
defined('APP_NAME') or die(header('HTTP/1.1 403 Forbidden'));
define("ATOZ_PHP8", true);

/**
* @author Balaji
* @name: Rainbow PHP Framework
* @copyright 2024 ProThemes.Biz
*
*/

$pageTitle = "Manage Addons";
$subTitle = "Install Add-on";
$fullLayout = 1; $footerAdd = false;

$activeTheme = getTheme($con);

if(isset($_POST['addon'])) {

    //Enter addon filename
    $addonFileName = raino_trim($_POST['addon']);

    //Addon Uploaded Path
    $target_dir = ADMIN_DIR . 'addons' . D_S;

    //Package File Path
    $file_path = $target_dir . $addonFileName;

    if (file_exists($file_path)) {

        //Temporarily extract Addons Data
        $addon_path = ADMIN_DIR . "addons/" . "ad_" . rand(1000, 999999);
        extractZip($file_path, $addon_path);

        //Check Addons Installer is exists
        if (file_exists($addon_path . "/atozseov3.tdata")){
            if (file_exists($addon_path . "/install.php")) {
                #--------------------------------------------------------------
                //DB Lang Fix
                $dbLang = $addon_path.'/db.sql';
                if(file_exists($dbLang)){
                    $output = '';
                    $arr = file($dbLang);
                    foreach($arr as $line)
                        $output .= str_replace(array("`lang_en`", "('AD"), array("`en`", "('ATOZAD"), $line);
                    file_put_contents($dbLang, $output);
                }

                $atozData =  $addon_path.'/atozseov3.tdata';
                if(file_exists($atozData)){
                    $contents = file_get_contents($atozData);
                    $contents = str_replace('$lang[\'','$lang[\'ATOZ',$contents);
                    $contents = str_replace('$lang["','$lang["ATOZ',$contents);
                    file_put_contents($atozData,$contents);
                }

                foreach (glob($addon_path . D_S . 'theme' . D_S . 'default' . D_S . '*.php', GLOB_BRACE) as $filename) {
                    if (file_exists($filename)){
                        $contents = file_get_contents($filename);
                        $contents = str_replace('$lang[\'','$lang[\'ATOZ',$contents);
                        $contents = str_replace('$lang["','$lang["ATOZ',$contents);
                        file_put_contents($filename,$contents);
                    }
                }
                foreach (glob($addon_path . D_S . 'theme' . D_S . 'default' . D_S . 'output' . D_S  . '*.php', GLOB_BRACE) as $filename) {
                    if (file_exists($filename)){
                        $contents = file_get_contents($filename);
                        $contents = str_replace('$lang[\'','$lang[\'ATOZ',$contents);
                        $contents = str_replace('$lang["','$lang["ATOZ',$contents);
                        file_put_contents($filename,$contents);
                    }
                }

                foreach (glob($addon_path . D_S . 'core' . D_S . 'controllers' . D_S . '*.php', GLOB_BRACE) as $filename) {
                    if (file_exists($filename)){
                        $contents = file_get_contents($filename);
                        $contents = str_replace('$lang[\'','$lang[\'ATOZ',$contents);
                        $contents = str_replace('$lang["','$lang["ATOZ',$contents);
                        file_put_contents($filename,$contents);
                    }
                }

                #--------------------------------------------------------------

                //Found - Process Installer
                require_once ($addon_path . "/install.php");

                if($activeTheme != 'default' && $activeTheme != 'simpleX'){
                    $addonRes.= "Copying Theme Files to $activeTheme<br>";
                    recurse_copy($addon_path."/theme/default",ROOT_DIR."/theme/$activeTheme");
                }
            }else{
                //Not Found
                $addonRes = "Addons Installer is not detected!";
                $addonError = true;
                $errType = 1;
            }
        } elseif (file_exists($addon_path . "/atozseo.tdata")){
            $addonRes = "Incompatible with your version. <br>Update your addon into the latest version!";
            $addonError = true;
            $errType = 1;
        }else{
            //Not Found
            $addonRes = "Not compatible add-on!";
            $addonError = true;
            $errType = 1;
        }
        $addonRes = str_replace(array("<br>", "<br/>", "<br />"), PHP_EOL, $addonRes);
        //Delete the Addons Data
        delDir($addon_path);

        //Delete the package file
        delFile($file_path);
    } else {
        $addonRes = 'File Not Found!';
        $addonError = true;
        $errType = 1;
    }
} else {
    $addonRes = 'No Input!';
    $addonError = true;
    $errType = 1;
}

$controller = 'process-addon';
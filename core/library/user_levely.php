<?php

defined('PLUG_SYS') or die(header('HTTP/1.0 403 Forbidden'));

/*
 * @author Balaji
 * @name: Rainbow PHP Framework
 * @copyright � 2017 ProThemes.Biz
 *
 */
 
if(PLUG_SYS){
    $executionLevel = "levely";
    $query = "SELECT * FROM plugins"; 
    $result = mysqli_query($con, $query);
    
    while($row = mysqli_fetch_array($result)) {
        $execution_type = strtolower(Trim($row['execution_type']));
        $privilege = strtolower(Trim($row['privilege']));
        $plugin_active = filter_var($row['plugin_active'], FILTER_VALIDATE_BOOLEAN);
        $plugin_con_name = Trim($row['plugin_con_name']);
        $plugin_info = unserialize(Trim($row['plugin_info']));
        
        if($privilege == "user"){
            if($plugin_active){
                if($execution_type == "levely" || $execution_type == "levelz"){
                    $filepath = PLG_DIR.$plugin_con_name.".php";
                    $filepath1 = PLG_DIR.strtolower($plugin_con_name).".php";
                    if(file_exists($filepath))
                    {
                        require $filepath;
                    }
                    elseif(file_exists($filepath1))
                    {
                        require $filepath1;
                    }else{
                        stop("Failed to load plugin - $plugin_info[0]!");
                    }
                }
            }
        }
    }
}

?>
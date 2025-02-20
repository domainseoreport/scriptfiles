<?php
//database_helper.php File Code
/*
 * @author Balaji
 * @name: Rainbow PHP Framework
 * @copyright 2019 ProThemes.Biz
 *
 */


function importMySQLdb($con, $filePath, $gzip=false){
    
    $templine = ''; $lines = array();
    
    if($gzip){
        $dbData = gzdecode(file_get_contents($filePath));
        $lines = explode("\n", $dbData);
    }else{
        $lines = file($filePath);
    }
    
    foreach ($lines as $line){
        if (substr($line, 0, 2) == '--' || $line == '')
            continue;
            
        $templine .= $line;
        
        if (substr(trim($line), -1, 1) == ';'){
            mysqli_query($con, $templine) or print('Error performing query' . mysqli_error($con) . '<br />');
            $templine = '';
        }
    
    }
}

function installMySQLdb($con, $filePath, $gzip=false){
    
    $templine = ''; $lines = array();
    $completed = true;
    if($gzip){
        $dbData = gzdecode(file_get_contents($filePath));
        $lines = explode("\n", $dbData);
    }else{
        $lines = file($filePath);
    }
    
    foreach ($lines as $line){
        if (substr($line, 0, 2) == '--' || $line == '')
            continue;
            
        $templine .= $line;
        
        if (substr(trim($line), -1, 1) == ';'){
            mysqli_query($con, $templine);
            if (mysqli_errno($con)){
                echo 'Error performing query' . mysqli_error($con) . '<br />';
                $completed = false;
            }else{
                if(strtolower(substr(trim($templine),0,6)) == 'create'){
                   $d = explode('`',trim($templine));
                   echo '"'.$d[1].'" table created successfully <br>'; 
                }
            }
            $templine = '';
        }
    
    }
    return $completed;
}

function writeBackupFile($fp, $content, $backupFileName){
    if (fwrite($fp, $content) === FALSE) {
        echo "Cannot write to file ($backupFileName)";
        die();
    }
}

function gzCompressFile($source, $level = 9){ 
    $dest = $source . '.gz'; 
    $mode = 'wb' . $level; 
    $error = false; 
    if ($fp_out = gzopen($dest, $mode)) { 
        if ($fp_in = fopen($source,'rb')) { 
            while (!feof($fp_in)) 
                gzwrite($fp_out, fread($fp_in, 1024 * 512)); 
            fclose($fp_in); 
        } else {
            $error = true; 
        }
        gzclose($fp_out); 
    } else {
        $error = true; 
    }
    if ($error)
        return false; 
    else
        return true; 
} 

function backupMySQLdb($con, $dbName, $backupPath, $gzip=false){

    $date = date( "d-m-Y-h-i-s");
    $dbTables = array();
    $defaultRowLimit = 100;
    $defaultRowSizeLimit = 9999;
    $backupOkay = false;

    if (!is_dir($backupPath))
        mkdir($backupPath, 0777, true);

    $backupFileName = $backupPath.$dbName.'-'.$date.'.sql';
    
    $fp = fopen($backupFileName ,'w+');
        
$contents = 
"-- ---------------------------------------------------------
--
-- Rainbow PHP Framework - Database Backup Tool
-- 
--
-- Host Connection Info: ".mysqli_get_host_info($con)."
-- Generation Time: ".date('F d, Y \a\t H:i A')."
-- Server version: ".mysqli_get_server_info($con)."
-- PHP Version: ".PHP_VERSION."
--
-- ---------------------------------------------------------\n

SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";
SET time_zone = \"+00:00\";

--
-- Database: `".$dbName."` --
--
\n";
    writeBackupFile($fp, $contents, $backupFileName);
   
    $results = mysqli_query($con, 'SHOW TABLES');
   
    while($row = mysqli_fetch_array($results)) {
        $dbTables[] = $row[0];
    }

    foreach($dbTables as $table){
        $contents = "-- Table `".$table."` --\n";
        writeBackupFile($fp, $contents, $backupFileName);
        
        $results = mysqli_query($con, 'SHOW CREATE TABLE '.$table);
        while($row = mysqli_fetch_array($results)) {
            $contents = $row[1].";\n\n";
            writeBackupFile($fp, $contents, $backupFileName);
        }
        
        $results = mysqli_query($con, 'SELECT * FROM '.$table. ' LIMIT 1');
        $fields =  mysqli_fetch_fields($results);
        $fields_count = count($fields);
        
        $handleRow = 0;
        
        do {    
            $results = mysqli_query($con, 'SELECT * FROM '.$table.' LIMIT '.$handleRow.', '.$defaultRowLimit);
            $handleRow += $defaultRowLimit;
            $row_count = mysqli_num_rows($results);
                           
            $insert_head = "INSERT INTO `".$table."` (";
            for($i=0; $i < $fields_count; $i++){
                $insert_head  .= "`".$fields[$i]->name."`";
                    if($i < $fields_count-1){
                            $insert_head  .= ', ';
                        }
            }
            $insert_head .=  ")";
            $insert_head .= " VALUES\n";       
                   
            if($row_count>0){
                $r = $limit = $rowSize = $divisionBalaji = 0;
                while($row = mysqli_fetch_array($results)){
                    if($rowSize > $defaultRowSizeLimit){
                        $limit = 0;
                        $divisionBalaji = 0;
                    }else{
                        $limit = $defaultRowLimit;
                        $divisionBalaji = $r % $limit;
                    }
                    $rowSize = 0;
                    if($divisionBalaji == 0){
                        $contents = $insert_head;
                        writeBackupFile($fp, $contents, $backupFileName);
                    }
                    $contents = "(";
                    writeBackupFile($fp, $contents, $backupFileName);
                    
                    for($i=0; $i < $fields_count; $i++){
                        $row_content =  str_replace("\n","\\n",mysqli_real_escape_string($con,$row[$i])); 
                        $rowSize = $rowSize + strlen($row_content);
                        switch($fields[$i]->type){
                            case 8: case 3:
                                writeBackupFile($fp, $row_content, $backupFileName);
                                break;
                            default:
                                writeBackupFile($fp, "'". $row_content ."'", $backupFileName);
                        }
                        if($i < $fields_count-1){
                                $contents  = ', ';
                                writeBackupFile($fp, $contents, $backupFileName);
                            }
                    }
                    if($rowSize > $defaultRowSizeLimit){
                        $contents = ");\n\n";
                        writeBackupFile($fp, $contents, $backupFileName);
                    }else{
                        if(($r+1) == $row_count || ($divisionBalaji) == $limit-1){
                            $contents = ");\n\n";
                            writeBackupFile($fp, $contents, $backupFileName);
                        }else{
                            $contents = "),\n";
                            writeBackupFile($fp, $contents, $backupFileName);
                        }
                    }
                    $r++;
                }
            }
        
        } while($row_count !== 0);
    }
    fclose($fp);
    $backupOkay = true;
    
    if($gzip){
        ini_set('zlib.output_compression','Off');
        $backupOkay = gzCompressFile($backupFileName, 9);
        delFile($backupFileName);
        $backupFileName = $backupFileName.'.gz';
    }

    if($backupOkay)
        return $backupFileName;
    else
        return '';
}

function dbCountRows($con, $tableName){
    $result = mysqli_query($con, 'SELECT COUNT(*) FROM '.$tableName);
    $row = mysqli_fetch_array($result);
    return $row[0];
}

function insertToDb($con,$tableName,$arr){
    $part1 = $part2 = '';
    $part1 .= 'INSERT INTO '.$tableName.' (';
    $part2 .= ' VALUES (';
    $queryCount = count($arr); $i = 0;
    foreach($arr as $key=>$val){
        if(++$i === $queryCount) {
            $part1 .= $key.')';
            $part2 .= "'".$val."')";
        }else{
            $part1 .= $key.',';
            $part2 .= "'".$val."',";
        }
    }
    $buildQuery = $part1.$part2;
    mysqli_query($con,$buildQuery); 
        
    return mysqli_error($con);
}

function insertToDbPrepared($con,$tableName,$arr){

  
    $params = array();
    $error = $typeDef = $part1 = $part2 = '';
    $part1 .= 'INSERT INTO '.$tableName.' (';
    $part2 .= ' VALUES (';
    $queryCount = count($arr); $i = 0;
    foreach($arr as $key=>$val){
        $params[$i] = &$arr[$key];
        $typeDef .= 's';
        if(++$i === $queryCount) {
            $part1 .= $key.')';
            $part2 .= "?)";
        }else{
            $part1 .= $key.',';
            $part2 .= "?,";
        }
    }
    $buildQuery = $part1.$part2;
 
    $stmt = mysqli_prepare($con,$buildQuery);
    
    if (false===$stmt)
        return mysqli_error($con);
        
    call_user_func_array("mysqli_stmt_bind_param",array_merge(array(&$stmt, &$typeDef), $params));
    mysqli_stmt_execute($stmt);
    $error = mysqli_stmt_error($stmt);
    mysqli_stmt_close($stmt);
    
    return $error;
}

function updateToDb($con,$tableName,$arr,$where){
    $part1 = $part2 = '';
    $part1 .= 'UPDATE '.$tableName.' SET ';
    $part2 .= ' WHERE ';
    $queryCount = count($arr); $i = 0;
    foreach($arr as $key=>$val){
        if(++$i === $queryCount) {
            $part1 .= $key."='".$val."' ";
        }else{
            $part1 .= $key."='".$val."', ";
        }
    }
    $i = 0;
    foreach($where as $key=>$val){
        if($i == 1) {
            $part2 .= ' AND '.$key."='".$val."'";
            break;
        }else{
            $part2 .= $key."='".$val."'";
        }
        $i++;
    }
    $buildQuery = $part1.$part2;
    mysqli_query($con,$buildQuery); 
        
    return mysqli_error($con);
}

// function updateToDbPrepared($con, $tableName, $data, $conditions, $customTypeDef = false, $typeDefStr = '')
// {
    
//     // Check for empty arrays to avoid invalid queries.
//     if (empty($data)) {
//         return 'No data provided for update.';
//     }
//     if (empty($conditions)) {
//         return 'No WHERE conditions provided; refusing to update all rows.';
//     }

//     // Ensure table and column names are valid (implement your own validation as needed)
//     // For example, you might use a whitelist of allowed table/column names here.

//     $params = [];
//     $typeDef = '';
//     $setClause = '';
//     $whereClause = '';
    
//     // Build SET clause
//     $i = 0;
//     $dataCount = count($data);
//     foreach ($data as $column => $value) {
//         // Append comma if not the first column
//         $setClause .= ($i > 0 ? ', ' : '') . $column . " = ?";
//         $params[] = &$data[$column];
//         $typeDef .= 's'; // Default to string type
//         $i++;
//     }
    
//     // Build WHERE clause
//     $first = true;
//     foreach ($conditions as $column => $value) {
//         // Append 'AND' for subsequent conditions
//         $whereClause .= ($first ? '' : ' AND ') . $column . " = ?";
//         $params[] = &$conditions[$column];
//         $typeDef .= 's'; // Default to string type
//         $first = false;
//     }
    
//     // Construct the final query
//     $query = "UPDATE $tableName SET $setClause WHERE $whereClause";
     
//     $stmt = mysqli_prepare($con, $query);
//     if ($stmt === false) {
//         return mysqli_error($con);
//     }
    
//     // Optionally override type definitions if required
//     if ($customTypeDef) {
//         $typeDef = $typeDefStr;
//     }
    
//     // Bind parameters. Note: The mysqli_stmt_bind_param requires variables to be passed by reference.
//     $bindNames = array_merge([$stmt, $typeDef], $params);
//     call_user_func_array('mysqli_stmt_bind_param', $bindNames);
    
//     mysqli_stmt_execute($stmt);
//     $error = mysqli_stmt_error($stmt);
//     mysqli_stmt_close($stmt);
    
//     return $error; // Returns empty string if no error occurred.
// }
/**
 * Updates a database table using a prepared statement.
 *
 * @param mysqli  $con            The MySQLi connection object.
 * @param string  $tableName      The table name to update.
 * @param array   $updateData     Associative array of columns and values to update. 
 *                                Example: ['col1' => 'value1', 'col2' => 'value2']
 * @param array   $where          Associative array for the WHERE clause.
 *                                Example: ['id' => 123]
 * @param bool    $customTypeDef  If true, use the custom type definition string provided.
 * @param string  $typeDefStr     The custom type definition string (e.g. 'iss' for int, string, string).
 * @param bool    $debug          If true, debugging information will be output.
 *
 * @return string  An error message if one occurs, or an empty string on success.
 */
function updateToDbPrepared($con, $tableName, $updateData, $where, $customTypeDef = false, $typeDefStr = '', $debug = false) {
    // Array to store references to the parameter values
    $params = array();
  
    // Initialize variables for building the SQL query and type definition string.
    $typeDef = '';
    $setClause = '';
    $whereClause = '';
    
    // Build the SET clause from the $updateData array.
    // Each column in $updateData is appended as "column = ?" to the SET clause.
    $i = 0; // Counter for the number of parameters in SET clause.
    foreach ($updateData as $column => $value) {
        // Append a comma before subsequent columns.
        if ($i > 0) {
            $setClause .= ', ';
        }
        $setClause .= $column . ' = ?';
        // Store a reference to the value for binding.
        $params[] = &$updateData[$column];
        // For simplicity, we assume each parameter is a string ('s').
        $typeDef .= 's';
        $i++;
    }
    
    // Build the WHERE clause from the $where array.
    // Each condition is appended as "column = ?" and joined with "AND".
    $j = 0; // Counter for WHERE clause parameters.
    foreach ($where as $column => $value) {
        // Append "AND" if this is not the first condition.
        if ($j > 0) {
            $whereClause .= ' AND ';
        }
        $whereClause .= $column . ' = ?';
        // Add a reference to the value for binding.
        $params[] = &$where[$column];
        // Again, assume each value is a string ('s').
        $typeDef .= 's';
        $j++;
        $i++; // Increment the overall parameter count.
    }
    
    // Construct the full SQL update query.
    $sql = "UPDATE {$tableName} SET {$setClause} WHERE {$whereClause}";
   
    // Debug output: show the built query, type definition, and parameters.
    if ($debug) {
        echo "DEBUG: SQL Query: {$sql}\n";
        echo "DEBUG: Type Definition String: {$typeDef}\n";
        echo "DEBUG: Parameters: " . print_r($params, true) . "\n";
    }
   
    // Prepare the SQL statement.
    $stmt = mysqli_prepare($con, $sql);
    if ($stmt === false) {
        // Debug output for preparation error.
        if ($debug) {
            echo "DEBUG: Statement preparation failed: " . mysqli_error($con) . "\n";
        }
        return mysqli_error($con);
    }
    
    // If a custom type definition is provided, override the auto-generated one.
    if ($customTypeDef) {
        $typeDef = $typeDefStr;
    }
    
    // Prepare an array for binding parameters.
    // Note: mysqli_stmt_bind_param requires arguments passed by reference.
    $bindParams = array();
    $bindParams[] = $stmt;
    $bindParams[] = $typeDef;
    // Loop through $params (already by reference) and add them.
    foreach ($params as $key => $param) {
        $bindParams[] = &$params[$key];
    }
    
    // Bind parameters to the prepared statement.
    $bindResult = call_user_func_array("mysqli_stmt_bind_param", $bindParams);
    if ($bindResult === false) {
        if ($debug) {
            echo "DEBUG: Parameter binding failed: " . mysqli_stmt_error($stmt) . "\n";
        }
        return mysqli_stmt_error($stmt);
    }
   
    // Execute the prepared statement.
    mysqli_stmt_execute($stmt);
 
    // Retrieve any error message from execution.
    $error = mysqli_stmt_error($stmt);
    
    // Debug output: show any execution error.
    if ($debug && $error) {
        echo "DEBUG: Statement execution error: {$error}\n";
    }
    
    // Close the statement to free resources.
    mysqli_stmt_close($stmt);
    
    // Return the error message if any, or an empty string on success.
    return $error;
}

function mysqliPreparedQuery($con,$query,$typeDef = false,$params = false, $noSingle = true){
 
    
  $result = $bindParams = array();
  $countRes = 0;$multiQuery = false;
  if($stmt = mysqli_prepare($con,$query)){
    if(count($params) == count($params,1)){
      $params = array($params);
      $multiQuery = false;
    } else {
      $multiQuery = true;
    } 

    if($typeDef){   
      $bindParamsReferences = array();
      $bindParams = array_pad($bindParams,(count($params,1)-count($params))/count($params),"");        
      foreach($bindParams as $key => $value){
        $bindParamsReferences[$key] = &$bindParams[$key]; 
      }
      array_unshift($bindParamsReferences,$typeDef);
      $bindParamsMethod = new ReflectionMethod('mysqli_stmt', 'bind_param');
      $bindParamsMethod->invokeArgs($stmt,$bindParamsReferences);
    }

    foreach($params as $queryKey => $query){
      foreach($bindParams as $paramKey => $value){
        $bindParams[$paramKey] = $query[$paramKey];
      }
      $queryResult = array();
      if(mysqli_stmt_execute($stmt)){
        $resultMetaData = mysqli_stmt_result_metadata($stmt);
        if($resultMetaData){                                                                              
          $stmtRow = array();  
          $rowReferences = array();
          while ($field = mysqli_fetch_field($resultMetaData)) {
            $rowReferences[] = &$stmtRow[$field->name];
          }                               
          mysqli_free_result($resultMetaData);
          $bindResultMethod = new ReflectionMethod('mysqli_stmt', 'bind_result');
          $bindResultMethod->invokeArgs($stmt, $rowReferences);
          while(mysqli_stmt_fetch($stmt)){
            $countRes++;
            $row = array();
            foreach($stmtRow as $key => $value){
              $row[$key] = $value;          
            }
            $queryResult[] = $row;
          }
          mysqli_stmt_free_result($stmt);
        } else {
          $queryResult[] = mysqli_stmt_affected_rows($stmt);
        }
      } else {
        $queryResult[] = false;
      }
      $result[$queryKey] = $queryResult;
    }
    mysqli_stmt_close($stmt);  
  } else {
    $result = false;
  }

  if($multiQuery){
    return $result;
  } else {
    if($noSingle){
        if($countRes == 0)
            return false;
        elseif($countRes == 1)
            return $result[0][0];
        else
            return $result[0];
    }else{
        if($countRes == 0)
            return array();
        else
            return $result[0];
    }
  }
}
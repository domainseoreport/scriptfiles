<?php

defined('APP_NAME') or die(header('HTTP/1.0 403 Forbidden'));

/*
 * @author Balaji
 * @name: Turbo Website Reviewer
 * @copyright © 2017 ProThemes.Biz
 *
 */

$fullLayout = 1;
$pageTitle = 'Reviewer Settings';
$subTitle = 'Website Reviewer';
$footerAdd = true; $footerAddArr = array();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    $headerFilePath = $footerFilePath = $pdfData = '';
    $reviewer_list = array();
    $free_limit = escapeTrim($con, $_POST['limit']);
    $pdfCopyright = escapeTrim($con, $_POST['pdfCopyright']);
    $insightsApi = escapeTrim($con, $_POST['insights_api']);
    
    $reviewerSettings = reviewerSettings($con);        
    $arrPDF = decSerBase($reviewerSettings['pdf_data']);
    $headerFilePath = $arrPDF[1];
    $footerFilePath = $arrPDF[2];  
        
    $snapArr = array_map_recursive(
        function($item) use ($con) { return escapeTrim($con,$item); },
        $_POST['snap']
    );

    $domainDataArr = array_map_recursive(
        function($item) use ($con) { return escapeTrim($con,$item); },
        $_POST['domain_data']
    ); 

    $compareDataArr = array_map_recursive(
        function($item) use ($con) { return escapeTrim($con,$item); },
        $_POST['compare_data']
    ); 
    
    foreach($_POST['reviewerList'] as $reviewer)
       $reviewer_list[] = escapeTrim($con,$reviewer); 
    $reviewer_listStr = serialize($reviewer_list);
    
    if(isset($_FILES['headerUpload']) && $_FILES['headerUpload']['name'] != ''){
        $isUploaded = secureImageUpload($_FILES['headerUpload']);
        if($isUploaded[0])
            $headerFilePath = $isUploaded[1];
        else
            $msg = errorMsgAdmin($isUploaded[1]);
    }
    if(isset($_FILES['footerUpload']) && $_FILES['footerUpload']['name'] != ''){
        $isUploaded = secureImageUpload($_FILES['footerUpload']);
        if($isUploaded[0])
            $footerFilePath = $isUploaded[1];
        else
            $msg = errorMsgAdmin($isUploaded[1]);
    }
    
    if(!isset($msg)){
        $pdfData = serBase(array($pdfCopyright,$headerFilePath,$footerFilePath));   
        $domainDataStr = arrToDbStr($con,$domainDataArr);
        $compareDataStr = arrToDbStr($con,$compareDataArr);
        $snapStr = arrToDbStr($con,$snapArr);
        
        $query = "UPDATE reviewer_settings SET free_limit='$free_limit', domain_data='$domainDataStr', reviewer_list='$reviewer_listStr', pdf_data='$pdfData', compare_data='$compareDataStr', snap_service='$snapStr', insights_api='$insightsApi' WHERE id='1'";
        mysqli_query($con, $query);
    
        if (mysqli_errno($con))
            $msg = errorMsgAdmin(mysqli_error($con));
        else
            $msg = successMsgAdmin('Reviewer settings saved successfully'); 
    }
}

$reviewerList = getReviewerList();
$db = reviewerSettings($con);
$db['reviewer_list'] = unserialize($db['reviewer_list']);
$db['domain_data'] = dbStrToArr($db['domain_data']);
$db['compare_data'] = dbStrToArr($db['compare_data']);
$db['snap'] = dbStrToArr($db['snap_service']);

$arrPDF = decSerBase($db['pdf_data']);
$pdfCopyright = $arrPDF[0];
$headerLogo = createLink($arrPDF[1],true);
$footerLogo = createLink($arrPDF[2],true);
?>
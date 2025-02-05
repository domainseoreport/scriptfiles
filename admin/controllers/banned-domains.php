<?php

defined('APP_NAME') or die(header('HTTP/1.0 403 Forbidden'));

/*
 * @author Balaji
 * @name: Turbo Website Reviewer
 * @copyright © 2017 ProThemes.Biz
 *
 */

$pageTitle = 'Banned Domains';
$subTitle = 'Banned Domain List';
$fullLayout = 1; $footerAdd = false; $footerAddArr = array();

//Banned Success
if($pointOut == 'success')
    $msg = successMsgAdmin('Domain banned successfully.'); 
      
//Get banned domain list
$domainRestriction =  mysqli_query($con, "SELECT * FROM domain_restriction WHERE id='1'");
$domainRestrictionRow = mysqli_fetch_array($domainRestriction);
$bannedList = dbStrToArr($domainRestrictionRow['domains']);

//Delete a banned domain name
if($pointOut == 'delete'){
    $code = $args[0];
    if(isset($bannedList[$code])){
        unset($bannedList[$code]);
        
        $bannedStr = arrToDbStr($con,$bannedList);
        
        if(updateToDb($con,'domain_restriction',array(
            'domains' => $bannedStr
        ),array(
            'id' => '1'
        ))){
            $msg = errorMsgAdmin(mysqli_error($con));
        }else{
            header('Location:'.adminLink($controller,true));
            die();
        }
    }
}

//Ban a Domain
if($pointOut == 'add'){
    
    $subTitle = 'Add Domain Name to Ban';
    
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        
        $domain = getDomainName(escapeTrim($con, $_POST['domain']));
        $banReason = escapeTrim($con, $_POST['reason']);
        
        $bannedList[$domain] = array(
        'reason' => $banReason, 
        'added_at' => $date
        );
        
        $bannedStr = arrToDbStr($con,$bannedList);
        
        if(updateToDb($con,'domain_restriction',array(
            'domains' => $bannedStr
        ),array(
            'id' => '1'
        ))){
            $msg = errorMsgAdmin(mysqli_error($con));
        }else{
            header('Location:'.adminLink('banned-domains/success',true));
            die(); 
        }
    }
}
?>
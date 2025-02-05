<?php

defined('APP_NAME') or die(header('HTTP/1.0 403 Forbidden'));

/*
 * @author Balaji
 * @name: Turbo Website Reviewer
 * @copyright © 2017 ProThemes.Biz
 *
 */

$pageTitle = 'All Domains';
$subTitle = 'Domain List';
$fullLayout = 1; $footerAdd = $status = true; $footerAddArr = array();

//Ban a Domain
if($pointOut == 'ban'){ 
    $banDomainName = $args[0]; 
    $bannedList = getBannedDomainList($con);

    if(isset($bannedList[$banDomainName])){
        header('Location:'.adminLink($controller.'/banned',true));
        die();
    }else{
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            
            $domain = escapeTrim($con, $_POST['domain']);
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
}

//Already Banned Domain
if($pointOut == 'banned')
    $msg = successMsgAdmin('Domain already banned!');


//Delete a Domain
if($pointOut == 'delete'){
    $deleteId = $args[0];
    $query = "DELETE FROM domains_data WHERE id='$deleteId'";
    $result = mysqli_query($con, $query);

    if (mysqli_errno($con))
        $msg = errorMsgAdmin(mysqli_error($con));
    else 
        $msg = successMsgAdmin('Domain deleted from database successfully.');
}

?>
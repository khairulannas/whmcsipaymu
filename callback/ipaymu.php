<?php
/*
 * This is WHMCS module using IPAYMU gateway
 * Author : Harry Sudana
 * URL : http://github.com/harrysudana/whmcsipaymu
 * Release Date: 2012.12.20
 * License : http://www.gnu.org/licenses/gpl.html
 */

/*
 * WHMCS - The Complete Client Management, Billing & Support Solution
 * Copyright (c) WHMCS Ltd. All Rights Reserved,
 * Email: info@whmcs.com
 * Website: http://www.whmcs.com
 */

/*
 * IPAYMU - Indonesian Payment Gateway
 * Website: https://ipaymu.com
 */

# Required File Includes
include("../../../init.php"); #dbconnect.php diganti ke init.php utk whmcs versi 6 keatas karena sudah jadi 1 dengan file init.php, sumber : http://docs.whmcs.com/Version_6.0_Release_Notes#Removal_of_Dbconnect.php
include("../../../includes/functions.php");
include("../../../includes/gatewayfunctions.php");
include("../../../includes/invoicefunctions.php");

$gatewaymodule = "ipaymu";

$GATEWAY = getGatewayVariables($gatewaymodule);
if (!$GATEWAY["type"]) die("Module Not Activated"); # Checks gateway module is active before accepting callback
$systemURL = ($CONFIG['SystemSSLURL'] ? $CONFIG['SystemSSLURL'] : $CONFIG['SystemURL']);
$log = $_GET["log"]=='on' ? TRUE : FALSE;

if($_GET['method']=="cancel"){
	$invoiceid = $_GET["id"];
	$invoiceid = checkCbInvoiceID($invoiceid,$GATEWAY["name"]); # Checks invoice ID is a valid invoice number or ends processing
	header('HTTP/1.1 200 OK');
	if($log)
	logTransaction($GATEWAY["name"],$_GET,__LINE__.":Transaksi dibatalkan"); # Save to Gateway Log: name, data array, status
	header("Location: {$systemURL}/viewinvoice.php?id={$invoiceid}");
	exit(__LINE__.': Transaksi dibatalkan');
}elseif( $_GET['method']=="notify" ){
	if($_POST["status"]<>"berhasil"){
		if($log)
		logTransaction($GATEWAY["name"],$_POST,__LINE__.":Tidak Berhasil");
	}else{
		if($log)
		logTransaction($GATEWAY["name"],$_POST,__LINE__.":Catch from IPAYMU");
		$invoiceid = $_GET["id"];
		$parameters = array('ipaymu_apikey'=>$_GET['apikey']);
		if(isset($_POST['paypal_trx_id'])){
			$transid = $_POST["paypal_trx_id"];
			if($_POST["total"] == $_POST["paypal_trx_total"]){
				$amount = $_GET["total"];
			}else{
				$amount = $_POST["total"];
			}

			$fee = 0;
			addInvoicePayment($invoiceid,$transid,$amount,$fee,$gatewaymodule); # Apply Payment to Invoice: invoiceid, transactionid, amount paid, fees, modulename
			if($log)
			logTransaction($GATEWAY["name"], $_POST, __LINE__.":Successful using Paypal trough IPAYMU"); # Save to Gateway Log: name, data array, status
			header('HTTP/1.1 200 OK');
			exit(__LINE__.': Successful using Paypal trough IPAYMU');
		}else{
			$transid = $_POST["trx_id"];
			$amount = $_POST["total"];
			$fee = 0;
		}
		$invoiceid = checkCbInvoiceID($invoiceid,$GATEWAY["name"]); # Checks invoice ID is a valid invoice number or ends processing
		checkCbTransID($transid); # Checks transaction number isn't already in the database and ends processing if it does
		if ($transid<>"") {
			$ipaymutrx = ipaymu_cektransaksi($parameters, $transid);
			if($log)
			logTransaction($GATEWAY["name"],$ipaymutrx,__LINE__.":Cek for IPAYMU Transaction");
			if(!$ipaymutrx){
				header('HTTP/1.1 200 OK');
				exit(__LINE__.': Curl Error!');
			}elseif($ipaymutrx['Status']==1){
				# Successful
				addInvoicePayment($invoiceid,$transid,$amount,$fee,$gatewaymodule); # Apply Payment to Invoice: invoiceid, transactionid, amount paid, fees, modulename
				if($log)
				logTransaction($GATEWAY["name"],array('return'=>json_encode($_POST), 'ipaymu'=>json_encode($ipaymutrx)), __LINE__.":Successful"); # Save to Gateway Log: name, data array, status
				header('HTTP/1.1 200 OK');
				exit(__LINE__.': Successful');
			}else{
				if($log)
				logTransaction($GATEWAY["name"],array('return'=>json_encode($_POST), 'ipaymu'=>json_encode($ipaymutrx)), __LINE__.":Successful tapi masih".$ipaymutrx['Status']); # Save to Gateway Log: name, data array, status
				header('HTTP/1.1 200 OK');
				exit(__LINE__.":Successful with payment pending");
			}
		} else {
			header('HTTP/1.1 400 Bad Request');
			# Unsuccessful
			if($log)
			logTransaction($GATEWAY["name"],$_POST,__LINE__.":Tidak menemukan transaksi"); # Save to Gateway Log: name, data array, status
			exit(__LINE__.':Tidak menemukan transaksi');
		}
	}
}else{
	$invoiceid = $_GET["id"];
	header('HTTP/1.1 200 OK');
	if($log)
	logTransaction($GATEWAY["name"],$_GET,__LINE__.":Returned");
	header("Location: {$systemURL}/viewinvoice.php?id={$invoiceid}");
	exit(__LINE__.': Transaksi dikembalikan');
}

?>

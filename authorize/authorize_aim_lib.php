<?php
/*
This is a library of functions used to manipulate Advanced Integration Method (AIM) functions at Authorize.net to perform manual payment processing
They are called using the following general syntax: functionName(parameter, parameter, parameter);
Which returns a Result string along with various applicable parsed values,  For example:

	$aim = authorizeValidateCard(
		$apiLogin,
		$transKey,
		$testMode,
		$amount,
		$cardNumber,
		$expirationDate
	);

	$approval = $aim->approved;  //The Approval decision

This library requires the inclusion of the AIM class library 'authorizenet.aim.class.php' as well as 4 supporting libraries provided by Authorize

*/

/*
Live API Login:			6e9h5DC8DD4
Live Transaction Key:	5hD292gYJ5329aLT
Sandbox API Login:		2cw2pN6NAe
Sandbox Transaction Key:4w4w7xB3MvL35VCC
*/

// Include the class file and supporting libraries (need to do this in the calling code to insure proper pathing)
//require('../../secure/authorize/AuthorizeNetRequest.php');
//require('../../secure/authorize/AuthorizeNetTypes.php');
//require('../../secure/authorize/AuthorizeNetXMLResponse.php');
//require('../../secure/authorize/AuthorizeNetResponse.php');
//require('../../secure/authorize/authorizenet.aim.class.php');


/*********************************************
 VALIDATE A CARD
*********************************************/

function authorizeValidateCard(
	$apiLogin,
	$transKey,
	$testMode = false,
	$amount,
	$cardNumber,
	$expirationDate
){

	// Authenticate
	define("AUTHORIZENET_API_LOGIN_ID", $apiLogin);
	define("AUTHORIZENET_TRANSACTION_KEY", $transKey);
	define("AUTHORIZENET_SANDBOX", $testMode);

	// Create transaction object
	$validate = new AuthorizeNetAIM;

	// Set parameters
	$validate->amount = $amount;
	$validate->card_num = $cardNumber;
	$validate->exp_date = $expirationDate;

	//started returning "Error connecting to AuthorizeNet" in April (2015) so disabled peer verification on Authorize's recommendation
	$validate->VERIFY_PEER = false;

	// Do it!
	$aim = $validate->authorizeOnly();

	return $aim->approved;

}

// This API has many more functions but this is the only one we are currently using as the rest is handled by CIM

?>
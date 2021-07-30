<?php

// Sample code to show how to create various records

	// include the class file
	require('authorizenet.cim.class.php');
	require('authorize_cim_lib.php');

	// Test mode
	$apiLogin = "2cw2pN6NAe";
	$transKey = "4w4w7xB3MvL35VCC";
	$testMode = true;

	// Live
//	$apiLogin = "6e9h5DC8DD4";
//	$transKey = "5hD292gYJ5329aLT";
//	$testMode = false;


/*********************************************
 AUTHORIZE CARD ONLY (AIM)
*********************************************/

/*
  // API credentials only need to be defined once
  define("AUTHORIZENET_API_LOGIN_ID", $apiLogin);
  define("AUTHORIZENET_TRANSACTION_KEY", $transKey);
  define("AUTHORIZENET_SANDBOX", $testMode);

  $sale = new AuthorizeNetAIM;
  $sale->amount = "499.00";
  $sale->card_num = '4111111111111111';
  $sale->exp_date = '0418';
  $sale->VERIFY_PEER = false; //started returning "Error connecting to AuthorizeNet" in April (2015) so disabled peer verification on Authorize's recommendation
  $response = $sale->authorizeOnly();

  if ($response->approved) {
    echo "Success! Transaction ID: " . $response->transaction_id;
  } else {
    echo "ERROR: " . $response->error_message;
  }
//print_r($response);

*/


/*********************************************
 MEMBER ACCOUNT
*********************************************/

/*
// Create new member, set up account and default payment method
	$memberID = "3";
	$firstName = "Jeff";
	$lastName = "Saunders";
	$address = "217 Creekside Drive";
	$city = "Murphy";
	$state = "TX";
	$zip = "75094";
	$country = "US";
	$phone = "972-461-0540";
	$email = "jeff@nr.net";
	$cardNumber = "4111111111111111";
	$expirationDate = "2015-12";

	$cim = authorizeCreateAccount(
		$apiLogin,
		$transKey,
		$testMode,
		$memberID,
		$firstName,
		$lastName,
		$address,
		$city,
		$state,
		$zip,
		$country,
		$phone,
		$email,
		$cardNumber,
		$expirationDate
	);

	$resultCode = $cim->resultCode;  //"Ok" if all is well
	$code = $cim->code;  //The Authorize response code
	$text = $cim->text;  //"Successful." if all goes well
	$customerProfileId = $cim->customerProfileId;  //The customer ID for this new member
	$customerPaymentProfileId = $cim->customerPaymentProfileId;  // The payment profile ID for the credit card entered
	$customerAddressId = $cim->customerAddressId;  //The shipping address profile ID (required but unused by us (yet))
*/

/*
// Delete member
	$customerProfileId = "31821370";

	$cim = authorizeDeleteAccount(
		$apiLogin,
		$transKey,
		$testMode,
		$customerProfileId
	);

	$resultCode = $cim->resultCode;  //"Ok" if all is well
	$code = $cim->code;  //The Authorize response code
	$text = $cim->text;  //"Successful." if all goes well
*/

/*
// Update member
	$memberID = "2";
	$customerProfileId = "31821312";
	$email = "jeffsaunders@gmail.com";

	$cim = authorizeUpdateAccount(
		$apiLogin,
		$transKey,
		$testMode,
		$customerProfileId,
		$memberID,
		$email
	);

	$resultCode = $cim->resultCode;  //"Ok" if all is well
	$code = $cim->code;  //The Authorize response code
	$text = $cim->text;  //"Successful." if all goes well
*/

/*
// Get member record
	$customerProfileId = "31817903";

	$cim = authorizeGetAccount(
		$apiLogin,
		$transKey,
		$testMode,
		$customerProfileId
	);

	$resultCode = $cim->resultCode;  //"Ok" if all is well
	$code = $cim->code;  //The Authorize response code
	$text = $cim->text;  //"Successful." if all goes well
	$merchantCustomerId = $cim->merchantCustomerId;
	$email = $cim->email;
	$firstName = $cim->firstName;
	$lastName = $cim->lastName;
	$address = $cim->address;
	$city = $cim->city;
	$state = $cim->state;
	$zip = $cim->zip;
	$country = $cim->country;
*/


/*********************************************
 PAYMENT PROFILE
*********************************************/

/*
// New payment profile (add a card)
	$customerProfileId = "31821312";
	$firstName = "Jeff";
	$lastName = "Saunders";
	$address = "217 Creekside Drive";
	$city = "Murphy";
	$state = "TX";
	$zip = "75094";
	$country = "US";
	$email = "jeff@nr.net";
	$cardNumber = "4222222222222";
	$expirationDate = "2015-12";

	$cim = authorizeCreatePaymentProfile(
		$apiLogin,
		$transKey,
		$testMode,
		$customerProfileId,
		$firstName,
		$lastName,
		$address,
		$city,
		$state,
		$zip,
		$country,
		$cardNumber,
		$expirationDate
	);

	$resultCode = $cim->resultCode;  //"Ok" if all is well
	$code = $cim->code;  //The Authorize response code
	$text = $cim->text;  //"Successful." if all goes well
	$customerPaymentProfileId = $cim->customerPaymentProfileId;  // The payment profile ID for the credit card just entered
*/

/*
// Delete payment profile (card)
	$customerProfileId = "31821312";
	$customerPaymentProfileId = "28759479";

	$cim = authorizeDeletePaymentProfile(
		$apiLogin,
		$transKey,
		$testMode,
		$customerProfileId,
		$customerPaymentProfileId
	);

	$resultCode = $cim->resultCode;  //"Ok" if all is well
	$code = $cim->code;  //The Authorize response code
	$text = $cim->text;  //"Successful." if all goes well
*/

/*
// Update payment profile (card)
	$customerProfileId = "31821312";
	$customerPaymentProfileId = "28758479";
	$firstName = "Jeff";
	$lastName = "Saunders";
	$address = "217 Creekside Drive";
	$city = "Murphy";
	$state = "TX";
	$zip = "75094";
	$country = "US";
	$email = "jeff@nr.net";
	$cardNumber = "4222222222222";
	$expirationDate = "2016-12";

	$cim = authorizeUpdatePaymentProfile(
		$apiLogin,
		$transKey,
		$testMode,
		$customerProfileId,
  		$customerPaymentProfileId,
		$firstName,
		$lastName,
		$address,
		$city,
		$state,
		$zip,
		$country,
		$cardNumber,
		$expirationDate
	);

	$resultCode = $cim->resultCode;  //"Ok" if all is well
	$code = $cim->code;  //The Authorize response code
	$text = $cim->text;  //"Successful." if all goes well
*/

/*
// Get payment profile (card) information
	$customerProfileId = "31817903";
	$customerPaymentProfileId = "28758686";

	$cim = authorizeGetPaymentProfile(
		$apiLogin,
		$transKey,
		$testMode,
		$customerProfileId,
		$customerPaymentProfileId
	);

	$resultCode = $cim->resultCode;  //"Ok" if all is well
	$code = $cim->code;  //The Authorize response code
	$text = $cim->text;  //"Successful." if all goes well
	$firstName = $cim->firstName;
	$lastName = $cim->lastName;
	$address = $cim->address;
	$city = $cim->city;
	$state = $cim->state;
	$zip = $cim->zip;
	$country = $cim->country;
	$cardNumber = $cim->cardNumber;
*/


/*********************************************
 SHIPPING ADDRESS
*********************************************/

/*
// Create new shipping address
	$customerProfileId = "31821312";
	$firstName = "Jeff";
	$lastName = "Saunders";
	$address = "5791 Quicksilver Circle";
	$city = "Las Vegas";
	$state = "NV";
	$zip = "89110";
	$country = "US";
	$phone = "702-555-1212";

	$cim = authorizeCreateShippingAddress(
		$apiLogin,
		$transKey,
		$testMode,
		$customerProfileId,
		$firstName,
		$lastName,
		$address,
		$city,
		$state,
		$zip,
		$country,
		$phone
	);

	$resultCode = $cim->resultCode;  //"Ok" if all is well
	$code = $cim->code;  //The Authorize response code
	$text = $cim->text;  //"Successful." if all goes well
	$customerAddressId = $cim->customerAddressId;  //The shipping address profile ID
*/

/*
// Delete shipping address
	$customerProfileId = "31821312";
	$customerAddressId = "29807890";

	$cim = authorizeDeleteShippingAddress(
		$apiLogin,
		$transKey,
		$testMode,
		$customerProfileId,
		$customerAddressId
	);

	$resultCode = $cim->resultCode;  //"Ok" if all is well
	$code = $cim->code;  //The Authorize response code
	$text = $cim->text;  //"Successful." if all goes well
*/

/*
// Update shipping address
	$customerProfileId = "31821312";
	$customerAddressId = "29806147";
	$firstName = "Jeff";
	$lastName = "Saunders";
	$address = "5791 Quicksilver Circle";
	$city = "Las Vegas";
	$state = "NV";
	$zip = "89110";
	$country = "US";
	$phone = "702-555-1212";

	$cim = authorizeUpdateShippingAddress(
		$apiLogin,
		$transKey,
		$testMode,
		$customerProfileId,
		$customerAddressId,
		$firstName,
		$lastName,
		$address,
		$city,
		$state,
		$zip,
		$country,
		$phone
	);

	$resultCode = $cim->resultCode;  //"Ok" if all is well
	$code = $cim->code;  //The Authorize response code
	$text = $cim->text;  //"Successful." if all goes well
*/

/*
// Get payment profile (card) information
	$customerProfileId = "31817903";
	$customerAddressId = "29802753";

	$cim = authorizeGetShippingAddress(
		$apiLogin,
		$transKey,
		$testMode,
		$customerProfileId,
		$customerAddressId
	);

	$resultCode = $cim->resultCode;  //"Ok" if all is well
	$code = $cim->code;  //The Authorize response code
	$text = $cim->text;  //"Successful." if all goes well
	$firstName = $cim->firstName;
	$lastName = $cim->lastName;
	$address = $cim->address;
	$city = $cim->city;
	$state = $cim->state;
	$zip = $cim->zip;
	$country = $cim->country;
	$phoneNumber = $cim->phoneNumber;
*/


/*********************************************
 PROCESS CHARGE
*********************************************/

/*
// Validate payment profile (card)
	$customerProfileId = "31817903";
	$customerPaymentProfileId = "28758686";

	$cim = authorizeValidatePaymentProfile(
		$apiLogin,
		$transKey,
		$testMode,
		$customerProfileId,
		$customerPaymentProfileId
	);

	$resultCode = $cim->resultCode;  //"Ok" if all is well
	$code = $cim->code;  //The Authorize response code
	$text = $cim->text;  //"Successful." if all goes well
	$approval = $cim->approval;
*/

/*
// Transaction request (charge a card)
	$customerProfileId = "31817903";
	$customerPaymentProfileId = "28758686";
	$order_invoiceNumber = "1234567";
	$order_description = "Marketocracy Premium Membership";
	$itemID = "PREMIUM";
	$itemName = "Annual Premium Membership Fee";
	$transactionCardCode = "999";
	$transactionType = "profileTransAuthCapture";
	$unitPrice = "399.00";
	$tax_amount = "32.92";
	$tax_name = "Texas Sales Tax";
	$transaction_amount = "431.92";

	$cim = authorizeCreateTransactionRequest(
		$apiLogin,
		$transKey,
		$testMode,
		$customerProfileId,
		$customerPaymentProfileId,
		$order_invoiceNumber,
		$order_description,
		$itemID,
		$itemName,
		$transactionCardCode,
		$transactionType,
		$unitPrice,
		$tax_amount,
		$tax_name,
		$transaction_amount
	);

	$resultCode = $cim->resultCode;  //"Ok" if all is well
	$code = $cim->code;  //The Authorize response code
	$text = $cim->text;  //"Successful." if all goes well
	$approval = $cim->approval;
	$transID = $cim->transID;
	$authCode = $cim->authCode;
*/



	/////////////////////////////////////////////////////////////////////
	// This will echo the responses for each method (Testing)

	print_r($cim);

	echo "<br><br><br>";

	if ($cim->isSuccessful())
	{
		echo "<br><strong>response:</strong> ".$cim->response;
		echo "<br><strong>directResponse:</strong> ".$cim->directResponse;
		echo "<br><strong>validationDirectResponse:</strong> ".$cim->validationDirectResponse;
		echo "<br><strong>resultCode:</strong> ".$cim->resultCode;
		echo "<br><strong>code:</strong> ".$cim->code;
		echo "<br><strong>text:</strong> ".$cim->text;
		echo "<br><strong>refId:</strong> ".$cim->refId;
		echo "<br><strong>customerProfileId:</strong> ".$cim->customerProfileId;
		echo "<br><strong>customerPaymentProfileId:</strong> ".$cim->customerPaymentProfileId;
		echo "<br><strong>customerAddressId:</strong> ".$cim->customerAddressId;
		echo "<br>---";
		echo "<br><strong>merchantCustomerId:</strong> ".$cim->merchantCustomerId;
		echo "<br><strong>email:</strong> ".$cim->email;
		echo "<br><strong>firstName:</strong> ".$cim->firstName;
		echo "<br><strong>lastName:</strong> ".$cim->lastName;
		echo "<br><strong>address:</strong> ".$cim->address;
		echo "<br><strong>city:</strong> ".$cim->city;
		echo "<br><strong>state:</strong> ".$cim->state;
		echo "<br><strong>zip:</strong> ".$cim->zip;
		echo "<br><strong>country:</strong> ".$cim->country;
		echo "<br><strong>phoneNumber:</strong> ".$cim->phoneNumber;
		echo "<br>---";
		echo "<br><strong>cardNumber:</strong> ".$cim->cardNumber;
		echo "<br><strong>approval:</strong> ".$cim->approval;
		echo "<br><strong>transID:</strong> ".$cim->transID;
		echo "<br><strong>authCode:</strong> ".$cim->authCode;
	}
	else
	{
		echo "<br><strong>directResponse:</strong> ".$cim->directResponse;
		echo "<br><strong>validationDirectResponse:</strong> ".$cim->validationDirectResponse;
		echo "<br><strong>resultCode:</strong> ".$cim->resultCode;
		echo "<br><strong>code:</strong> ".$cim->code;
		echo "<br><strong>text:</strong> ".$cim->text;
		echo "<br><strong>error_messages:</strong> <pre>";
		print_r($cim->error_messages);
		echo "</pre>";

	}


?>
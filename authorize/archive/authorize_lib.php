<?php
/*
This is a library of functions used to manipulate Customer Information Management (CIM) records at Authroize.net and to perform payment processing
They are called using the following general syntax: functionName(parameter, parameter, parameter);
Which returns a Result string along with various applicable parsed values,  For example:

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
	$approval = $cim->approval;  //The Approval decision

This library requires the inclusion of the CIM class library 'authorizenet.cim.class.php'

*/

/*
Live API Login:			6e9h5DC8DD4
Live Transaction Key:	5hD292gYJ5329aLT
Sandbox API Login:		2cw2pN6NAe
Sandbox Transaction Key:4w4w7xB3MvL35VCC
*/

// include the class file (need to do this in the calling code to insure proper pathing)
//require('authorizenet.cim.class.php');


/*********************************************
 MEMBER ACCOUNT
*********************************************/

// New member, set up account and default payment method
function authorizeCreateAccount(
	$apiLogin,
	$transKey,
	$testMode = false,
	$merchantCustomerId,
	$billTo_firstName,
	$billTo_lastName,
	$billTo_address,
	$billTo_city,
	$billTo_state,
	$billTo_zip,
	$billTo_country,
	$billTo_phoneNumber,
	$email,
	$cardNumber,
	$expirationDate
){

	// Athenticate
 	$cim = new AuthNetCim($apiLogin, $transKey, $testMode);

	// Set all to "individual"
	$cim->setParameter('customerType', 'individual'); // individual or business (optional)

	// merchantCustomerId must be unique across all profiles
	$cim->setParameter('merchantCustomerId', $merchantCustomerId); // Up to 20 characters (optional)

	// Some Billing address information is required and some is optional
	// depending on the Address Verification Service (AVS) settings
	$cim->setParameter('billTo_firstName', $billTo_firstName); // Up to 50 characters (no symbols)
	$cim->setParameter('billTo_lastName', $billTo_lastName); // Up to 50 characters (no symbols)
	//$cim->setParameter('billTo_company', 'Acme, Inc.'); // Up to 50 characters (no symbols) (optional)
	$cim->setParameter('billTo_address', $billTo_address); // Up to 60 characters (no symbols)
	$cim->setParameter('billTo_city', $billTo_city); // Up to 40 characters (no symbols)
	$cim->setParameter('billTo_state', $billTo_state); // A valid two-character state code (US only) (optional)
	$cim->setParameter('billTo_zip', $billTo_zip); // Up to 20 characters (no symbols)
	$cim->setParameter('billTo_country', $billTo_country); // Up to 60 characters (no symbols) (optional)
	$cim->setParameter('billTo_phoneNumber', $billTo_phoneNumber); // Up to 25 digits (no letters) (optional)
	//$cim->setParameter('billTo_faxNumber', '444-444-4444'); // Up to 25 digits (no letters) (optional)

	// In this method, shipping information is required because it reduces an extra
	// step from having to create a shipping address in the future, therefore you can simply update it when needed.
	// Just populate it with the billing info if you don't have an order form with shipping details.
	$cim->setParameter('shipTo_firstName', $billTo_firstName); // Up to 50 characters (no symbols)
	$cim->setParameter('shipTo_lastName', $billTo_lastName); // Up to 50 characters (no symbols)
	//$cim->setParameter('shipTo_company', 'Acme, Inc.'); // Up to 50 characters (no symbols) (optional)
	$cim->setParameter('shipTo_address', $billTo_address); // Up to 60 characters (no symbols)
	$cim->setParameter('shipTo_city', $billTo_city); // Up to 40 characters (no symbols)
	$cim->setParameter('shipTo_state', $billTo_state); // A valid two-character state code (US only) (optional)
	$cim->setParameter('shipTo_zip', $billTo_zip); // Up to 20 characters (no symbols)
	$cim->setParameter('shipTo_country', $billTo_country); // Up to 60 characters (no symbols) (optional)
	$cim->setParameter('shipTo_phoneNumber', $billTo_phoneNumber); // Up to 25 digits (no letters) (optional)
	//$cim->setParameter('shipTo_faxNumber', '444-444-4444'); // Up to 25 digits (no letters) (optional)

	// A receipt from authorize.net will be sent to the email address defined here
	$cim->setParameter('email', $email); // Up to 255 characters (optional)

	// Merchant-assigned reference ID for the request
	//$cim->setParameter('refId', 'my unique ref id'); // Up to 20 characters (optional)

	// Description must be unique across all profiles, if defined
	//$cim->setParameter('description', 'My description'); // Up to 255 characters (optional)

	// Choose a payment type - (creditCard or bankAccount) REQUIRED
	// creditCard payment method
	$cim->setParameter('paymentType', 'creditCard');
	$cim->setParameter('cardNumber', $cardNumber);
	$cim->setParameter('expirationDate', $expirationDate); // (YYYY-MM)

	// eCheck payment method (not used right now)
	//$cim->setParameter('paymentType', 'bankAccount');
	//$cim->setParameter('accountType', 'checking'); // (checking, savings or businessChecking)
	//$cim->setParameter('nameOnAccount', 'John Smith');
	//$cim->setParameter('echeckType', 'WEB'); // (CCD, PPD, TEL or WEB)
	//$cim->setParameter('bankName', 'Bank of America');
	//$cim->setParameter('routingNumber', '000000000');
	//$cim->setParameter('accountNumber', '0000000000000');

	// Do it!
	$cim->createCustomerProfileRequest();

	return $cim;

}


// Delete member completely
function authorizeDeleteAccount(
	$apiLogin,
	$transKey,
	$testMode = false,
	$customerProfileId
){

	// Athenticate
 	$cim = new AuthNetCim($apiLogin, $transKey, $testMode);

	// Merchant-assigned reference ID for the request
	//$cim->setParameter('refId', 'my unique ref id'); // Up to 20 characters (optional)

	// Payment gateway assigned ID associated with the customer profile
	$cim->setParameter('customerProfileId', $customerProfileId); // Numeric (required)

	// Do it!
	$cim->deleteCustomerProfileRequest();

	return $cim;

}


// Update member (primarily email address)
function authorizeUpdateAccount(
	$apiLogin,
	$transKey,
	$testMode = false,
	$customerProfileId,
	$merchantCustomerId,
	$email
){

	// Athenticate
 	$cim = new AuthNetCim($apiLogin, $transKey, $testMode);

	// Merchant-assigned reference ID for the request
	//$cim->setParameter('refId', 'my unique ref id'); // Up to 20 characters (optional)

	// Description of the customer or customer profile (unused)
	//$cim->setParameter('description', 'This is my new description'); // (optional)

	// Merchant assigned ID for the customer (This rewrites the record so it has to be passed each time)
	$cim->setParameter('merchantCustomerId', $merchantCustomerId); // (optional)

	// Email address associated with the customer profile
	$cim->setParameter('email', $email); // (optional)

	// Payment gateway assigned ID associated with the customer profile
	$cim->setParameter('customerProfileId', $customerProfileId); // Numeric (required)

	// Do it!
	$cim->updateCustomerProfileRequest();

	return $cim;

}


// Get member information
function authorizeGetAccount(
	$apiLogin,
	$transKey,
	$testMode = false,
	$customerProfileId
){

	// Athenticate
 	$cim = new AuthNetCim($apiLogin, $transKey, $testMode);

	// Payment gateway assigned ID associated with the customer profile
	$cim->setParameter('customerProfileId', $customerProfileId); // Numeric (required)

	// Do it!
	$cim->getCustomerProfileRequest();

	return $cim;

}


/*********************************************
 PAYMENT PROFILE
*********************************************/

// New payment profile (add a card)
function authorizeCreatePaymentProfile(
	$apiLogin,
	$transKey,
	$testMode = false,
	$customerProfileId,
	$billTo_firstName,
	$billTo_lastName,
	$billTo_address,
	$billTo_city,
	$billTo_state,
	$billTo_zip,
	$billTo_country,
	$cardNumber,
	$expirationDate
){

	// Athenticate
 	$cim = new AuthNetCim($apiLogin, $transKey, $testMode);

	// Set all to "individual"
	$cim->setParameter('customerType', 'individual'); // individual or business (optional)

	// Payment gateway assigned ID associated with the customer profile
	$cim->setParameter('customerProfileId', $customerProfileId); // Numeric (required)

	// Some Billing address information is required and some is optional
	// depending on the Address Verification Service (AVS) settings
	$cim->setParameter('billTo_firstName', $billTo_firstName); // Up to 50 characters (no symbols)
	$cim->setParameter('billTo_lastName', $billTo_lastName); // Up to 50 characters (no symbols)
	//$cim->setParameter('billTo_company', 'Acme, Inc.'); // Up to 50 characters (no symbols) (optional)
	$cim->setParameter('billTo_address', $billTo_address); // Up to 60 characters (no symbols)
	$cim->setParameter('billTo_city', $billTo_city); // Up to 40 characters (no symbols)
	$cim->setParameter('billTo_state', $billTo_state); // A valid two-character state code (US only) (optional)
	$cim->setParameter('billTo_zip', $billTo_zip); // Up to 20 characters (no symbols)
	$cim->setParameter('billTo_country', $billTo_country); // Up to 60 characters (no symbols) (optional)
	//$cim->setParameter('billTo_phoneNumber', '555-555-5555'); // Up to 25 digits (no letters) (optional)
	//$cim->setParameter('billTo_faxNumber', '444-444-4444'); // Up to 25 digits (no letters) (optional)

	// Choose a payment type - (creditCard or bankAccount) REQUIRED
	// creditCard payment method
	$cim->setParameter('paymentType', 'creditCard');
	$cim->setParameter('cardNumber', $cardNumber);
	$cim->setParameter('expirationDate', $expirationDate); // (YYYY-MM)

	// eCheck payment method (not used right now)
	//$cim->setParameter('paymentType', 'bankAccount');
	//$cim->setParameter('accountType', 'checking'); // (checking, savings or businessChecking)
	//$cim->setParameter('nameOnAccount', 'John Smith');
	//$cim->setParameter('echeckType', 'WEB'); // (CCD, PPD, TEL or WEB)
	//$cim->setParameter('bankName', 'Bank of America');
	//$cim->setParameter('routingNumber', '000000000');
	//$cim->setParameter('accountNumber', '0000000000000');


	// Merchant-assigned reference ID for the request
	//$cim->setParameter('refId', 'my unique ref id'); // Up to 20 characters (optional)

	//  if liveMode, the billing address gets verified according to AVS settings on your Authorize.net account
	$validationMode = ($testMode == false ? "liveMode" : "testMode");
	$cim->setParameter('validationMode', $validationMode); // required (none, testMode or liveMode)

	$cim->createCustomerPaymentProfileRequest();

	return $cim;

}


// Delete payment profile (card) completely
function authorizeDeletePaymentProfile(
	$apiLogin,
	$transKey,
	$testMode = false,
	$customerProfileId,
	$customerPaymentProfileId
){

	// Athenticate
 	$cim = new AuthNetCim($apiLogin, $transKey, $testMode);

	// Merchant-assigned reference ID for the request
	//$cim->setParameter('refId', 'my unique ref id'); // Up to 20 characters (optional)

	// Payment gateway assigned ID associated with the customer profile
	$cim->setParameter('customerProfileId', $customerProfileId); // Numeric (required)

	// Payment gateway assigned ID associated with the customer payment profile
	$cim->setParameter('customerPaymentProfileId', $customerPaymentProfileId); // Numeric (required)

	// Do it!
	$cim->deleteCustomerPaymentProfileRequest();

	return $cim;

}


// Update payment profile (card)
function authorizeUpdatePaymentProfile(
	$apiLogin,
	$transKey,
	$testMode = false,
	$customerProfileId,
	$customerPaymentProfileId,
	$billTo_firstName,
	$billTo_lastName,
	$billTo_address,
	$billTo_city,
	$billTo_state,
	$billTo_zip,
	$billTo_country,
	$cardNumber,
	$expirationDate
){

	// Athenticate
 	$cim = new AuthNetCim($apiLogin, $transKey, $testMode);

	// Set all to "individual"
	//$cim->setParameter('customerType', 'individual'); // individual or business (optional)

	// Payment gateway assigned ID associated with the customer profile
	$cim->setParameter('customerProfileId', $customerProfileId); // Numeric (required)

	// Payment gateway assigned ID associated with the customer payment profile
	$cim->setParameter('customerPaymentProfileId', $customerPaymentProfileId); // Numeric (required)

	// Some Billing address information is required and some is optional
	// depending on the Address Verification Service (AVS) settings
	$cim->setParameter('billTo_firstName', $billTo_firstName); // Up to 50 characters (no symbols)
	$cim->setParameter('billTo_lastName', $billTo_lastName); // Up to 50 characters (no symbols)
	//$cim->setParameter('billTo_company', 'Acme, Inc.'); // Up to 50 characters (no symbols) (optional)
	$cim->setParameter('billTo_address', $billTo_address); // Up to 60 characters (no symbols)
	$cim->setParameter('billTo_city', $billTo_city); // Up to 40 characters (no symbols)
	$cim->setParameter('billTo_state', $billTo_state); // A valid two-character state code (US only) (optional)
	$cim->setParameter('billTo_zip', $billTo_zip); // Up to 20 characters (no symbols)
	$cim->setParameter('billTo_country', $billTo_country); // Up to 60 characters (no symbols) (optional)
	//$cim->setParameter('billTo_phoneNumber', '555-555-5555'); // Up to 25 digits (no letters) (optional)
	//$cim->setParameter('billTo_faxNumber', '444-444-4444'); // Up to 25 digits (no letters) (optional)

	// Choose a payment type - (creditCard or bankAccount) REQUIRED
	// creditCard payment method
	$cim->setParameter('paymentType', 'creditCard');
	$cim->setParameter('cardNumber', $cardNumber);
	$cim->setParameter('expirationDate', $expirationDate); // (YYYY-MM)

	// eCheck payment method (not used right now)
	//$cim->setParameter('paymentType', 'bankAccount');
	//$cim->setParameter('accountType', 'checking'); // (checking, savings or businessChecking)
	//$cim->setParameter('nameOnAccount', 'John Smith');
	//$cim->setParameter('echeckType', 'WEB'); // (CCD, PPD, TEL or WEB)
	//$cim->setParameter('bankName', 'Bank of America');
	//$cim->setParameter('routingNumber', '000000000');
	//$cim->setParameter('accountNumber', '0000000000000');


	// Merchant-assigned reference ID for the request
	//$cim->setParameter('refId', 'my unique ref id'); // Up to 20 characters (optional)

	//  if liveMode, the billing address gets verified according to AVS settings on your Authorize.net account
	//$validationMode = ($testMode == false ? "liveMode" : "testMode");
	//$cim->setParameter('validationMode', $validationMode); // required (none, testMode or liveMode)

	$cim->updateCustomerPaymentProfileRequest();

	return $cim;

}


// Get payment profile (card) information
function authorizeGetPaymentProfile(
	$apiLogin,
	$transKey,
	$testMode = false,
	$customerProfileId,
	$customerPaymentProfileId
){

	// Athenticate
 	$cim = new AuthNetCim($apiLogin, $transKey, $testMode);

	// Merchant-assigned reference ID for the request
	//$cim->setParameter('refId', 'my unique ref id'); // Up to 20 characters (optional)

	// Payment gateway assigned ID associated with the customer profile
	$cim->setParameter('customerProfileId', $customerProfileId); // Numeric (required)

	// Payment gateway assigned ID associated with the customer payment profile
	$cim->setParameter('customerPaymentProfileId', $customerPaymentProfileId); // Numeric (required)

	// Do it!
	$cim->getCustomerPaymentProfileRequest();

	return $cim;

}


/*********************************************
 SHIPPING ADDRESS
*********************************************/

// New shipping address
function authorizeCreateShippingAddress(
	$apiLogin,
	$transKey,
	$testMode = false,
	$customerProfileId,
	$shipTo_firstName,
	$shipTo_lastName,
	$shipTo_address,
	$shipTo_city,
	$shipTo_state,
	$shipTo_zip,
	$shipTo_country,
	$shipTo_phoneNumber
){

	// Athenticate
 	$cim = new AuthNetCim($apiLogin, $transKey, $testMode);

	// Payment gateway assigned ID associated with the customer profile
	$cim->setParameter('customerProfileId', $customerProfileId); // Numeric (required)

	// Shipping information
	$cim->setParameter('shipTo_firstName', $shipTo_firstName); // Up to 50 characters (no symbols)
	$cim->setParameter('shipTo_lastName', $shipTo_lastName); // Up to 50 characters (no symbols)
	//$cim->setParameter('shipTo_company', 'Acme, Inc.'); // Up to 50 characters (no symbols) (optional)
	$cim->setParameter('shipTo_address', $shipTo_address); // Up to 60 characters (no symbols)
	$cim->setParameter('shipTo_city', $shipTo_city); // Up to 40 characters (no symbols)
	$cim->setParameter('shipTo_state', $shipTo_state); // A valid two-character state code (US only) (optional)
	$cim->setParameter('shipTo_zip', $shipTo_zip); // Up to 20 characters (no symbols)
	$cim->setParameter('shipTo_country', $shipTo_country); // Up to 60 characters (no symbols) (optional)
	$cim->setParameter('shipTo_phoneNumber', $shipTo_phoneNumber); // Up to 25 digits (no letters) (optional)
	//$cim->setParameter('shipTo_faxNumber', '444-444-4444'); // Up to 25 digits (no letters) (optional)

	// Do it!
	$cim->createCustomerShippingAddressRequest();

	return $cim;

}


// Delete shipping address completely
function authorizeDeleteShippingAddress(
	$apiLogin,
	$transKey,
	$testMode = false,
	$customerProfileId,
	$customerAddressId
){

	// Athenticate
 	$cim = new AuthNetCim($apiLogin, $transKey, $testMode);

	// Merchant-assigned reference ID for the request
	//$cim->setParameter('refId', 'my unique ref id'); // Up to 20 characters (optional)

	// Payment gateway assigned ID associated with the customer profile
	$cim->setParameter('customerProfileId', $customerProfileId); // Numeric (required)

	// Payment gateway assigned ID associated with the customer shipping address
	$cim->setParameter('customerAddressId', $customerAddressId); // Numeric (required)

	// Do it!
	$cim->deleteCustomerShippingAddressRequest();

	return $cim;

}


// Update shipping address
function authorizeUpdateShippingAddress(
	$apiLogin,
	$transKey,
	$testMode = false,
	$customerProfileId,
	$customerAddressId,
	$shipTo_firstName,
	$shipTo_lastName,
	$shipTo_address,
	$shipTo_city,
	$shipTo_state,
	$shipTo_zip,
	$shipTo_country,
	$shipTo_phoneNumber
){

	// Athenticate
 	$cim = new AuthNetCim($apiLogin, $transKey, $testMode);

	// Payment gateway assigned ID associated with the customer profile
	$cim->setParameter('customerProfileId', $customerProfileId); // Numeric (required)

	// Payment gateway assigned ID associated with the customer shipping address
	$cim->setParameter('customerAddressId', $customerAddressId); // Numeric (required)

	// Shipping information
	$cim->setParameter('shipTo_firstName', $shipTo_firstName); // Up to 50 characters (no symbols)
	$cim->setParameter('shipTo_lastName', $shipTo_lastName); // Up to 50 characters (no symbols)
	//$cim->setParameter('shipTo_company', 'Acme, Inc.'); // Up to 50 characters (no symbols) (optional)
	$cim->setParameter('shipTo_address', $shipTo_address); // Up to 60 characters (no symbols)
	$cim->setParameter('shipTo_city', $shipTo_city); // Up to 40 characters (no symbols)
	$cim->setParameter('shipTo_state', $shipTo_state); // A valid two-character state code (US only) (optional)
	$cim->setParameter('shipTo_zip', $shipTo_zip); // Up to 20 characters (no symbols)
	$cim->setParameter('shipTo_country', $shipTo_country); // Up to 60 characters (no symbols) (optional)
	$cim->setParameter('shipTo_phoneNumber', $shipTo_phoneNumber); // Up to 25 digits (no letters) (optional)
	//$cim->setParameter('shipTo_faxNumber', '444-444-4444'); // Up to 25 digits (no letters) (optional)

	// Do it!
	$cim->updateCustomerShippingAddressRequest();

	return $cim;

}


// Get shipping address information
function authorizeGetShippingAddress(
	$apiLogin,
	$transKey,
	$testMode = false,
	$customerProfileId,
	$customerAddressId
){

	// Athenticate
 	$cim = new AuthNetCim($apiLogin, $transKey, $testMode);

	// Merchant-assigned reference ID for the request
	//$cim->setParameter('refId', 'my unique ref id'); // Up to 20 characters (optional)

	// Payment gateway assigned ID associated with the customer profile
	$cim->setParameter('customerProfileId', $customerProfileId); // Numeric (required)

	// Payment gateway assigned ID associated with the customer shipping address
	$cim->setParameter('customerAddressId', $customerAddressId); // Numeric (required)

	// Do it!
	$cim->getCustomerShippingAddressRequest();

	return $cim;

}



/*********************************************
 PROCESS CHARGE
*********************************************/


// Validate payment profile (card)
function authorizeValidatePaymentProfile(
	$apiLogin,
	$transKey,
	$testMode = false,
	$customerProfileId,
	$customerPaymentProfileId
){

	// Athenticate
 	$cim = new AuthNetCim($apiLogin, $transKey, $testMode);

	// Payment gateway assigned ID associated with the customer profile
	$cim->setParameter('customerProfileId', $customerProfileId); // Numeric (required)

	// Payment gateway assigned ID associated with the customer shipping address
	$cim->setParameter('customerPaymentProfileId', $customerPaymentProfileId); // Numeric (required)

	// Indicates the processing mode for the request (if live, the billing/shipping address gets verified)
	$validationMode = ($testMode == false ? "liveMode" : "testMode");
	$cim->setParameter('validationMode', $validationMode); // required (none, testMode or liveMode)

	// Do it!
	$cim->validateCustomerPaymentProfileRequest();

	return $cim;

}


// Transaction request (charge a card)
function authorizeCreateTransactionRequest(
	$apiLogin,
	$transKey,
	$testMode = false,
	$customerProfileId,
	$customerPaymentProfileId,
	$order_invoiceNumber,
	$order_description,
	$itemID,
	$itemName,
	$transactionCardCode,
	$transactionType = 'profileTransAuthCapture',
	$unitPrice,
	$tax_amount = '0.00',
	$tax_name = 'Sales Tax',
	$transaction_amount
){

	// Athenticate
 	$cim = new AuthNetCim($apiLogin, $transKey, $testMode);

	// Payment gateway assigned ID associated with the customer profile
	$cim->setParameter('customerProfileId', $customerProfileId); // Numeric (required)

	// Payment gateway assigned ID associated with the customer shipping address
	$cim->setParameter('customerPaymentProfileId', $customerPaymentProfileId); // Numeric (required)

	// Merchant-assigned reference ID for the request
	//$cim->setParameter('refId', 'my unique ref id'); // Up to 20 characters (optional)

	// Up to 20 characters (no symbols) (optional)
	$cim->setParameter('order_invoiceNumber', $order_invoiceNumber);

	// Up to 255 characters (no symbols) (optional)
	$cim->setParameter('order_description', $order_description);

	// Up to 25 characters (no symbols) (optional)
	//$cim->setParameter('order_purchaseOrderNumber', '1234');

	// The tax exempt status (Set all to false)
	$cim->setParameter('transactionTaxExempt', 'false');

	// The recurring billing status (Set all to false - we will be handling recurring billing ourselves)
	$cim->setParameter('transactionRecurringBilling', 'false');

	// The customer's card code (the three- or four-digit number on the back or front of a credit card)
	// Required only when the merchant would like to use the Card Code Verification (CCV) filter
	$cim->setParameter('transactionCardCode', $transactionCardCode); // (conditional)

	// Transaction Type - profileTransAuthOnly, profileTransCaptureOnly, or profileTransAuthCapture (actually charge the card)
	$cim->setParameter('transactionType', 'profileTransAuthCapture');

	// Total Amount: This amount should include all other amounts such as tax amount, shipping amount, etc.
	$cim->setParameter('transaction_amount', $transaction_amount); // Up to 4 digits with a decimal (required)

	// This amount must be included in the total amount for the transaction. (optional)
	$cim->setParameter('tax_amount', $tax_amount); // Up to 4 digits with a decimal point (no dollar symbol) (optional)
	$cim->setParameter('tax_name', $tax_name); // Up to 31 characters (optional)
	//$cim->setParameter('tax_description', 'my custom description'); // Up to 255 characters (optional)

	// This amount must be included in the total amount for the transaction. (optional)
	//$cim->setParameter('shipping_amount', '0.00'); // Up to 4 digits with a decimal point (no dollar symbol) (optional)
	//$cim->setParameter('shipping_name', 'my custom name'); // Up to 31 characters (optional)
	//$cim->setParameter('shipping_description', 'my custom description'); // Up to 255 characters (optional)

	// This amount must be included in the total amount for the transaction. (optional)
	//$cim->setParameter('duty_amount', '0.00'); // Up to 4 digits with a decimal point (no dollar symbol) (optional)
	//$cim->setParameter('duty_name', 'my custom name'); // Up to 31 characters (optional)
	//$cim->setParameter('duty_description', 'my custom description'); // Up to 255 characters (optional)

	// LineItems: (Contains line item details about the order.) (optional)
	// Up to 30 distinct instances of this element may be included per transaction to describe items included in the order.
	$LineItem = array();
	for ($i = 1; $i <= 1; $i++){
		// The ID assigned to the item
		$LineItem[$i]['itemId'] = $itemID; // Up to 31 characters
		// A short description of an item
		$LineItem[$i]['name'] = $itemName; // Up to 31 characters
		// A detailed description of an item
		//$LineItem[$i]['description'] = 'my custom description'; // Up to 255 characters
		// The quantity of an item
		$LineItem[$i]['quantity'] = '1'; // Up to 4 digits (up to two decimal places)
		// Cost of an item per unit excluding tax, freight, and duty
		$LineItem[$i]['unitPrice'] = $unitPrice; // Up to 4 digits with a decimal point (no dollar symbol)
		// Indicates whether the item is subject to tax
		$taxable = ($tax_amount > 0 ? "1" : "0");
		$LineItem[$i]['taxable'] = $taxable; // Standard Boolean logic, 0=FALSE and 1=TRUE
	}
	$cim->LineItems = $LineItem;

	// Do it!
	$cim->createCustomerProfileTransactionRequest();

	return $cim;

}

?>
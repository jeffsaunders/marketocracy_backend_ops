<?php
/*
The purpose of this script is to perform member billing daily.
*Note - this will not run within a web browser.
*/

// Tell me when things go sideways
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Run long enough
set_time_limit(900); // 15 minutes

// Load debug functions
require("/var/www/html/includes/systemDebugFunctions.php");

// Load encryption functions
require("/var/www/html/includes/crypto.php");

// Connect to MySQL
require("/var/www/html/includes/dbConnectPDO.php");

// Get newest system config values
require("/var/www/html/includes/getConfigPDO.php");

// Load Authorize Functions
require_once('/var/www/html/authorize/AuthorizeNetRequest.php');
require_once('/var/www/html/authorize/AuthorizeNetTypes.php');
require_once('/var/www/html/authorize/AuthorizeNetXMLResponse.php');
require_once('/var/www/html/authorize/AuthorizeNetResponse.php');

//require_once('/var/www/html/authorize/authorizenet.aim.class.php');
//require_once('/var/www/html/authorize/authorize_aim_lib.php');

require_once("/var/www/html/authorize/authorizenet.cim.class.php");
require_once("/var/www/html/authorize/authorize_cim_lib.php");

// Are we in test mode?
if ($_SESSION['authorize_test_mode'] == 1){ // In test mode
	$apiLogin = trim(decrypt($sandbox_api_login_id));
	$transKey = trim(decrypt($sandbox_transaction_key));
	$testMode = true;
}else{  // Live mode
	$apiLogin = trim(decrypt($authorize_api_login_id));
	$transKey = trim(decrypt($authorize_transaction_key));
	$testMode = false;
}

// Set today's date in YYYYMMDD format
$today = date('Ymd');
//$today = 20170202;

// Exclude the following product IDs (free/not billed)
$excludeList = "0,1,5,10,11,99";

// Get all the subscriptions that are due to be billed today
$query = "
	SELECT *
	FROM ".$subscriptions_table."
	WHERE active = 1
	AND product_id NOT IN (:excludeList)
	AND FROM_UNIXTIME(next_bill_timestamp, '%Y%m%d') + 0 <= :today
	ORDER BY member_id
";
try {
	$rsSubs = $mLink->prepare($query);
	$aValues = array(
		':excludeList'	=> $excludeList,
		':today'		=> $today
	);
	$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
	//echo $preparedQuery;//die();
	$rsSubs->execute($aValues);
}
catch(PDOException $error){
	// Log any error
	file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
}
//dump_rs($rsSubs); // Display result set - debug

// Step through them
while($subs = $rsSubs->fetch(PDO::FETCH_ASSOC)){

	// Skip subsequent add-on records for this member (already processed below)
	if ($subs['member_id'] == $member_id){
		continue;
	}

	// Static values
	$tax_name			= "Non Taxable";  // Cannot be blank (lame)
	$tax_amount			= "0.00"; // Must contain a decimal point (lamer)
	$taxable			= false;
	$orderDescription	= "Marketocracy Subscription";
	$transactionType	= "profileTransAuthCapture";
	$itemID				= "1";

	// Assigned values
	$member_id			 = $subs['member_id'];
	$order_invoiceNumber = $member_id."-".time();
	$unitPrice			 = 0;
	$transaction_amount	 = 0;

	// Get the customerProfileID from merchant_customer_profiles
	$query = "
		SELECT customerProfileID
		FROM ".$customer_profiles_table."
		WHERE member_id = :member_id
	";
	try{
		$rsGetCustomerInfo = $mLink->prepare($query);
		$aValues = array(
			':member_id'	=> $member_id
		);
		$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
		//echo $preparedQuery;//die();
		$rsGetCustomerInfo->execute($aValues);
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}
	$customerInfo		= $rsGetCustomerInfo->fetch(PDO::FETCH_ASSOC);
	$customerProfileID	= $customerInfo['customerProfileID'];

	// Get the customerPaymentProfileID & CCV from merchant_payment_profiles
	$query = "
		SELECT uid, customerPaymentProfileID, CCV, billTo_state
		FROM ".$payment_profiles_table."
		WHERE member_id = :member_id
		AND default_method = 1
	";
	try{
		$rsGetPaymentInfo = $mLink->prepare($query);
		$aValues = array(
			':member_id'	=> $member_id
		);
		$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
		//echo $preparedQuery;//die();
		$rsGetPaymentInfo->execute($aValues);
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}
	$paymentInfo				= $rsGetPaymentInfo->fetch(PDO::FETCH_ASSOC);
	$payMethodID				= $paymentInfo['uid'];
	$customerPaymentProfileID	= $paymentInfo['customerPaymentProfileID'];
	$transactionCardCode		= double_decrypt($paymentInfo['CCV']) + 0; // Force numeric
	$taxState					= $paymentInfo['billTo_state'];

	// Re-get all the subscriptions that are due to be billed today for this member
	$query = "
		SELECT *
		FROM ".$subscriptions_table."
		WHERE active = 1
		AND product_id NOT IN (:excludeList)
		AND FROM_UNIXTIME(next_bill_timestamp, '%Y%m%d') + 0 <= :today
		AND member_id = :member_id
		ORDER BY next_bill_timestamp ASC
	";
	try {
		$rsItems = $mLink->prepare($query);
		$aValues = array(
			':excludeList'	=> $excludeList,
			':today'		=> $today,
			':member_id'	=> $member_id
		);
		$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
		//echo $preparedQuery;//die();
		$rsItems->execute($aValues);
	}
	catch(PDOException $error){
		// Log any error
		file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}

	// Step through them
	$subIDs = "";
	while($item = $rsItems->fetch(PDO::FETCH_ASSOC)){

		$subIDs .= $item['uid'].",";

		// Get the product information
		$query = "
			SELECT *
			FROM site_products
			WHERE product_id = :productID
		";
		try {
			$rsProd = $mLink->prepare($query);
			$aValues = array(
				':productID'	=> $item['product_id']
			);
			$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
			//echo $preparedQuery;//die();
			$rsProd->execute($aValues);
		}
		catch(PDOException $error){
			// Log any error
			file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
		}
		$prod		= $rsProd->fetch(PDO::FETCH_ASSOC);
		$itemName	= $prod['product_name'];
		$items		= $prod['product_id']."|";

		// If this is a special, Legacy Pro membership, use one of the alternate product names - the primary one defined in the database is too long for Authorize
		if ($prod['product_id'] == 4){
			$itemName = $prod['alt_product_name_2'];
		}

		// Tack on the word "Membership" if that's the item type
		if ($prod['product_id'] < 100){
			$itemName .= " Membership";
		}

		// Hard code to "Basic Membership Add-Ons" for any/all add-ons
		if ($prod['product_id'] > 100){
			$itemName = "Basic Membership Add-Ons";
		}

		// Determine the price based on the billing frequency
		if ($subs['bill_frequency'] == "Monthly"){
			$price = $prod['monthly_price'];
		}else{
			$price = $prod['annual_price'];
		}
		$billAmount = $price;
		$term		= $item['bill_frequency'];

		// Check for discounts
		$discounts = $item['discounts'];
		if ($discounts != "" && !is_null($discounts)){

			$aDiscounts = explode("|", $item['discounts']);
			foreach($aDiscounts as $key=>$discount){
//print_r($aDiscounts);die();

				$aDiscount = explode("~", $discount);
//print_r($aDiscount);die();

				$discountID = $aDiscount[0];
				$duration = $aDiscount[1];

				// Make sure the discount isn't expired
				$expiration = strtotime('+'.$duration.' days', $item['start_timestamp']);
				if (date("Ymd", $expiration) >= date("Ymd")){

					// Get the discount value
					$query = "
						SELECT *
						FROM site_products_discounts
						WHERE discount_id = :discountID
					";
					try {
						$rsDisc = $mLink->prepare($query);
						$aValues = array(
							':discountID'	=> $discountID
						);
						$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
						//echo $preparedQuery;//die();
						$rsDisc->execute($aValues);
					}
					catch(PDOException $error){
						// Log any error
						file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
					}
//dump_rs($rsDisc);die(); // Display result set - debug
					$disc = $rsDisc->fetch(PDO::FETCH_ASSOC);

					// Apply the discount
					if ($disc['discount_type'] == "percent"){
						$discFactor = (100 - $disc['amount']) / 100;
						$billAmount = $billAmount * $discFactor;
					}else{
						$discFactor = $disc['amount'];
						$billAmount = $billAmount - $discFactor;
					}

				}

			}

		}
		$unitPrice += $billAmount;

	}

	// Format the values to be sent - Authorize requires all dollar amounts to include the decimal and the cents...this code turns 5 into 5.00, for example
	$unitPrice = number_format((float)round($unitPrice, 2), 2, '.', '');

	// Apply sales tax
	if (strtoupper($taxState) == "TX" || strtoupper($taxState) == "TEXAS"){
		$taxable	= true;
		$tax_amount	= number_format((float)round(($unitPrice * .0825), 2), 2, '.', '');
		$tax_name	= "Texas Sales Tax";
	}

	// Total
	$transaction_amount = number_format((float)round($unitPrice + $tax_amount, 2), 2, '.', '');

/* Debug
if ($_SESSION["member_id"] == 1){
	echo $apiLogin."\n";
	echo $transKey."\n";
	echo $testMode."\n";
	echo $customerProfileID."\n";
	echo $customerPaymentProfileID."\n";
	echo $order_invoiceNumber."\n";
	echo $orderDescription."\n";
	echo $itemID."\n";
	echo $itemName."\n";
	echo $transactionCardCode."\n";
	echo $transactionType."\n";
	echo $unitPrice."\n";
	echo $tax_amount."\n";
	echo $tax_name."\n";
	echo $transaction_amount."\n";
	echo "\n---\n";
	die();
}
*/
	// Charge the card!
	$cim = authorizeCreateTransactionRequest(
		$apiLogin,
		$transKey,
		$testMode,
		$customerProfileID,
		$customerPaymentProfileID,
		$order_invoiceNumber,
		$orderDescription,
		$itemID,
		$itemName,
		$transactionCardCode,
		$transactionType,
		$unitPrice,
		$tax_amount,
		$tax_name,
		$transaction_amount
	);

	$CIMresultCode	= $cim->resultCode;  //"Ok" if all is well
	$CIMcode		= $cim->code;  //The Authorize response code
	$CIMtext		= $cim->text;  //"Successful." if all goes well
	$CIMapproval	= $cim->approval;
	$CIMtransID		= $cim->transID;
	$CIMauthCode	= $cim->authCode;
	$CIMresponse	= $cim->response;
//*//////////////////////////////////////////////////////////////////////////////////////////////////
//$CIMresultCode = "Error";
//$CIMcode = "$CIMcode text";
//$CIMtext = "$CIMtext text";
//echo $CIMresponse;die();
	// Test for resultCode = "Error"
	if ($CIMresultCode == "Error"){
//	if (is_null($CIMresultCode) || $CIMresultCode == "Error"){

		// Log the error
		$query = "
			INSERT INTO ".$transaction_errors_table."(
				timestamp,
				member_id,
				task,
				result_code,
				response_code,
				response_text
			) VALUES (
				UNIX_TIMESTAMP(),
				:member_id,
				:task,
				:result_code,
				:response_code,
				:response_text
			)
		";
		try{
			$rsLogError = $mLink->prepare($query);
			$aValues = array(
				':member_id'	=> $member_id,
				':task'			=> $transactionType,
				':result_code'	=> $CIMresultCode,
				':response_code'=> $CIMcode,
				':response_text'=> $CIMtext
			);
			$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
			$rsLogError->execute($aValues);
		}
		catch(PDOException $error){
			// Log any error
			file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
		}

		// Post a notificatiopn to the member that there card was declined...

	}else{ // Card approved!

		// Now
		$billTimestamp	= time();

		// Pop the trailing pipe off the items string
		$items = substr($items, 0, -1);

		// Insert transaction into merchant_transaction_history
		$query = "
			INSERT INTO ".$transaction_history_table."(
				member_id,
				customerProfileID,
				customerPaymentProfileID,
				order_invoiceNumber,
				bill_date,
				bill_timestamp,
				order_description,
				itemIDs,
				bill_frequency,
				unitPrice,
				taxable,
				tax_name,
				transaction_amount,
				discounts,
				transactionCardCode,
				payment_profile_uid,
				CIMResultCode,
				CIMCode,
				CIMText,
				CIMApproval,
				CIMTransID,
				CIMAuthCode,
				CIMResponse
			) VALUES (
				:member_id,
				:customerProfileID,
				:customerPaymentProfileID,
				:order_invoiceNumber,
				:bill_date,
				:bill_timestamp,
				:order_description,
				:itemIDs,
				:bill_frequency,
				:unitPrice,
				:taxable,
				:tax_name,
				:transaction_amount,
				:discounts,
				:transactionCardCode,
				:payment_profile_uid,
				:CIMResultCode,
				:CIMCode,
				:CIMText,
				:CIMApproval,
				:CIMTransID,
				:CIMAuthCode,
				:CIMResponse
			)
		";
		try{
			$rsInsert = $mLink->prepare($query);
			$aValues = array(
				':member_id'				=> $member_id,
				':customerProfileID'		=> $customerProfileID,
				':customerPaymentProfileID'	=> $customerPaymentProfileID,
				':order_invoiceNumber'		=> $order_invoiceNumber,
				':bill_date'				=> date("Ymd"),
				':bill_timestamp'			=> $billTimestamp,
				':order_description'		=> $orderDescription,
				':itemIDs'					=> $items,
				':bill_frequency'			=> $term,
				':unitPrice'				=> $unitPrice,
				':taxable'					=> $taxable,
				':tax_name'					=> $tax_name,
				':transaction_amount'		=> $transaction_amount,
				':discounts'				=> $discounts,
				':transactionCardCode'		=> $paymentInfo['CCV'],
				':payment_profile_uid'		=> $payMethodID,
				':CIMResultCode'			=> $CIMresultCode,
				':CIMCode'					=> $CIMcode,
				':CIMText'					=> $CIMtext,
				':CIMApproval'				=> $CIMapproval,
				':CIMTransID'				=> $CIMtransID,
				':CIMAuthCode'				=> $CIMauthCode,
				':CIMResponse'				=> addslashes($CIMresponse)
			);
			$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
			//$aErrors[] = $preparedQuery;
			//echo $preparedQuery;
			$rsInsert->execute($aValues);
		}
		catch(PDOException $error){
			// Log any error
			file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
			$aErrors[] = $error;
		}

		// Pop the trailing comma off the subIDs string
		$subIDs = substr($subIDs, 0, -1);

		// Set the next billing date
		if($term == 'Annually'){
			$nextBillDate = strtotime('+1 year', $subs['next_bill_timestamp']);
		}elseif($term == 'Monthly'){
			$nextBillDate = strtotime('+1 month', $subs['next_bill_timestamp']);
		}

		// Update the subscription records
		$query = "
			UPDATE ".$subscriptions_table."
			SET next_bill_timestamp	= :next_bill_timestamp,
				last_invoice_id		= :last_invoice_id
			WHERE uid IN (:sub_ids)
		";
		try{
			$rsUpdate = $mLink->prepare($query);
			$aValues = array(
				':next_bill_timestamp'	=> $nextBillDate,
				':last_invoice_id'		=> $order_invoiceNumber,
				':sub_ids'				=> $subIDs
			);
			$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
			//echo $preparedQuery;die();
			$rsUpdate->execute($aValues);
		}
		catch(PDOException $error){
			// Log any error
			file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
			$aErrors[] = $error;
		}

		// Post a billing notification to the member
	}

}

// Add code to generate a report of all the trans_errors!!!

/*
SELECT * FROM members_subscriptions WHERE active = 1 AND product_id NOT IN (0,1,5,10,99) AND FROM_UNIXTIME(next_bill_timestamp, '%Y%m%d') + 0 = 20170202 ORDER BY member_id
*/

?>
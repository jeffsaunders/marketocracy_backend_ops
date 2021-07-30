<?php
/*
The purpose of this script is to find all the basic members who have an upgrade and change their membership product id from 1 to 5 for easier processing.
Grossly hacked copy of the scan-new-memberships.php script from Portfolio (similar logic).
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
//require("/var/www/html/includes/crypto.php");

// Connect to MySQL
require("/var/www/html/includes/dbConnectPDO.php");

// Get newest system config values
require("/var/www/html/includes/getConfigPDO.php");

// Parse passed arguments string to $_REQUEST array (i.e. "first=1&second=2&third=3" -> $_REQUEST['first'] = 1, etc.)
//parse_str($argv[1], $_REQUEST);

/*
// Test passed run date is all numeric
if (isset($_REQUEST['date']) && !is_numeric($_REQUEST['date'])){
	echo "You must specify a run date as \"date=YYYYMMDD\" or leave blank for \"Today\". (e.g. php scan-trial-membership.php \"date=20161015\")\n";
	die();
}

// Test passed run date is 8 characters long
if (isset($_REQUEST['date']) && strlen($_REQUEST['date']) != 8){
	echo "You must specify a run date as \"date=YYYYMMDD\" or leave blank for \"Today\". (e.g. php scan-trial-membership.php \"date=20161015\")\n";
	die();
}

// Set run date
if (isset($_REQUEST['date'])){
	$run_date = $_REQUEST['date']; // Just have to trust that the passed date is properly formatted...
}else{
	$run_date = date('Ymd');
}

// Get the product details of each membership level
/* Don't need this anymore (leaving it here in case I do in the future)
$query = "
	SELECT *
	FROM ".$_SESSION['products_table']."
	WHERE product_id < 100
	AND active = 1
";
try{
	$rsProds = $mLink->prepare($query);
	$rsProds->execute();
}
catch(PDOException $error){
	// Log any error
	file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
}

// Assign values to an array (key = product_id)
$aProds = array();
while($product = $rsProds->fetch(PDO::FETCH_ASSOC)){
	$aProds[$product['product_id']] = $product;
}

//print_r($aProds);die();
*/
//echo "Processing New Memberships for ".$run_date."\n";

// Get all the active paid members (product_id <= 10 and not 0)
$query = "
	SELECT *
	FROM members_subscriptions
	WHERE active = 1
	AND start_timestamp IS NOT NULL
	AND product_id = 1
";
try{
	$rsSubs = $mLink->prepare($query);
	$rsSubs->execute();
}
catch(PDOException $error){
	// Log any error
	file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
}
/*
// Set up some counters
$emails7	= 0;
$emails15	= 0;
$emails23	= 0;
$emails27	= 0;
$emails30	= 0;
*/
while($subscription = $rsSubs->fetch(PDO::FETCH_ASSOC)){

	// Is this a Manager Pro?
//	$GIPS = false;
//	if ($subscription['product_id'] == 10){
//		$GIPS = true;
//	}

	// If they are Basic, see if they have an add-on
//	$free = false;
//	if ($subscription['product_id'] == 1){

		$query = "
			SELECT COUNT(*) AS count
			FROM members_subscriptions
			WHERE member_id = ".$subscription['member_id']."
			AND product_id > 99
			AND active = 1
		";
		try{
			$rsAddOns = $mLink->prepare($query);
			$rsAddOns->execute();
		}
		catch(PDOException $error){
			// Log any error
			file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
		}

		$addOns = $rsAddOns->fetch(PDO::FETCH_ASSOC);

		// If no add-ons found, flag them as a FREE member
		if ($addOns['count'] == 0){
continue;
//			$free = true;
		}



			$query = "
				UPDATE members_subscriptions
				SET	product_id = 5
				WHERE member_id = :member_id
				AND product_id = 1
			";
			try {
				$rsUpdate = $mLink->prepare($query);
				$aValues = array(
					':member_id' => $subscription['member_id']
				);
				$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
				//echo $preparedQuery;//die();
				$rsUpdate->execute($aValues);
			}
			catch(PDOException $error){
				// Log any error
				file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
			}
echo $subscription['member_id']."\n";



/*
	}

	// Calculate what day it is in their membership
	$day = $run_date - date('Ymd', $subscription['start_timestamp']);

	// Evaluate the day and act accordingly
	switch (true){ // Use this method so we can evaluate whether the value is == and >
// We'll need to tweak these day increments - leaving them the same as the trial emails, for now.
		case $day == 7: // End of first week

			if ($GIPS){
				// Build and send corresponding email here
			}elseif ($free){
				// Build and send corresponding email here
			}else{
				// Build and send corresponding email here
			}
			$emails7++;
			break;

		case $day == 15:  // After second week (early discount no longer offered)

			if ($GIPS){
				// Build and send corresponding email here
			}elseif ($free){
				// Build and send corresponding email here
			}else{
				// Build and send corresponding email here
			}
			$emails15++;
			break;

		case $day == 23:  // One week out

			if ($GIPS){
				// Build and send corresponding email here
			}elseif ($free){
				// Build and send corresponding email here
			}else{
				// Build and send corresponding email here
			}
			$emails23++;
			break;

		case $day == 27:  // 3 days out

			if ($GIPS){
				// Build and send corresponding email here
			}elseif ($free){
				// Build and send corresponding email here
			}else{
				// Build and send corresponding email here
			}
			$emails27++;
			break;

		case $day == 30:  // Last day

			if ($GIPS){
				// Build and send corresponding email here
			}elseif ($free){
				// Build and send corresponding email here
			}else{
				// Build and send corresponding email here
			}
			$emails30++;
			break;

// Add as many milestones as needed......

    	default:
			// Do Nothing

	}  // End switch

	echo ".";
*/
}

//echo "\n7 Day Emails Sent:  ".$emails7."\n";
//echo "15 Day Emails Sent: ".$emails15."\n";
//echo "23 Day Emails Sent: ".$emails23."\n";
//echo "27 Day Emails Sent: ".$emails27."\n";
//echo "30 Day Emails Sent: ".$emails30."\n";

echo "Done!\n";

?>
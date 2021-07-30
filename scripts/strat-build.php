<?php
// Don't show me all those thousands of warnings and notices (no time to clean up his code so just hide them!)
error_reporting(E_ALL ^ E_WARNING);

date_default_timezone_set('America/New_York');
ini_set('memory_limit', '1024M');

/*
+----------------------------------------------------------------------------------------+
|						STRATIFICATION CALCULATION ROUTINE
+----------------------------------------------------------------------------------------+
*/
// Parse passed arguments string to $_REQUEST array (i.e. "first=1&second=2&third=3" -> $_REQUEST['first'] = 1, etc.)
parse_str($argv[1], $_REQUEST);

//get dependencies
//require('/var/www/html/portfolio.marketocracy.com/secure/dbconnect.php');
//require('/var/www/html/portfolio.marketocracy.com/web/includes/system-functions.php');
//require('/var/www/html/portfolio.marketocracy.com/web/includes/system-debug-functions.php');
require("/var/www/html/includes/dbConnectPDO.php");


//echo 'start\n';

//tables
$stockSectorsTable	= 'stocks_sectors';
$stockSymbolsTable	= 'stocks_symbols';

$fundsTable			= 'members_fund';
$tradesTable 		= 'members_fund_trades';
$posDetailsTable	= 'members_fund_positions_details';
$stratBasicTable	= 'members_fund_stratification_basic';
$stratSectorTable	= 'members_fund_stratification_sector';
$stratStyleTable	= 'members_fund_stratification_style';
$stratSecPosTable	= 'members_fund_stratification_sector_positions';
$stratStylePosTable	= 'members_fund_stratification_style_positions';

#get sectors
$query = "
	SELECT * FROM ".$stockSectorsTable." WHERE active='1'
";
try{
	$rsSectors = $mLink->prepare($query);
	$aValues = array();
	$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
	$rsSectors->execute($aValues);
}
catch(PDOException $error){
	// Log any error
}
$aListSectors = array();
while($sector = $rsSectors->fetch(PDO::FETCH_ASSOC)){
	$aListSectors[$sector['sector_id']]	= $sector['sector_name'];	
}	
//echo $preparedQuery.'/n/n';
print_r($aListSectors);

//First lets see if we need to run this for a single fund or for all funds for a member
$fundID = $_REQUEST['fundID'];
$aFunds	= array();

//check to see if fundID is empty
if($fundID == ''){
	//If empty check for memberID
	$memberID = $_REQUEST['memberID'];
	
	if($memberID == ''){
		#if there is no memberID or fund ID exit script
		exit();	
	}else{
		#if there is a memberID, get all active fund ids
		$query = "
			SELECT fund_symbol, fund_id
			FROM ".$fundsTable."
			WHERE member_id=:member_id AND active='1'
		";	
		try{
			$rsFund = $mLink->prepare($query);
			$aValues = array(
				':member_id'	=> $memberID
			);
			$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
			$rsFund->execute($aValues);
		}
		catch(PDOException $error){
			// Log any error
		}
		
		while($fund = $rsFund->fetch(PDO::FETCH_ASSOC)){
			$aFunds[]	= $fund['fund_id'];
		}
	}
}else{
	$aFunds[] = $fundID;	
}



//print_r($aFunds);

//loop through funds
foreach($aFunds as $key=>$fundID){

	//getLivePrice
	/*$port = rand(52000, 52099);
	$query = 'livePrice|bmccarthy|'.$fundID.'|';
	exec('/var/www/html/portfolio.marketocracy.com/web/process/scripts/process-legacy-query.sh "'.$port.'" "'.$query.'" > /dev/null &');*/
	
	
	//mark fund record as processing
	$query = "
		UPDATE ".$fundsTable."
		SET processing='1'
		WHERE fund_id=:fund_id
	";
	try{
		$rsUpdate = $mLink->prepare($query);
		$aValues = array(
			':fund_id'	=> $fundID
		);
		$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
		$rsUpdate->execute($aValues);
	}
	catch(PDOException $error){
	}

	$query = "
		SELECT effectiveInceptionDate
		FROM members_fund_aggregate
		WHERE fund_id=:fund_id
		ORDER BY timestamp
		LIMIT 1
	";
	
	try{
		$rsAgg = $mLink->prepare($query);
		$aValues = array(
			':fund_id'		=> $fundID
			
		);
		$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
		$rsAgg->execute($aValues);
	}
	catch(PDOException $error){
		// Log any error
		//file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}
	
	$fundAgg = $rsAgg->fetch(PDO::FETCH_ASSOC);
	
	/*$aAggAll	= array(
		'returnLastWeek'	=> $fundAgg['returnLastWeek'],
		'currentReturn'		=> $fundAgg['returnSinceInception']
	);*/
	
	//$effectiveDate2 = $fundAgg['effectiveInceptionDate'];
	
	//$inceptDate2 = mktime(0,0,0,substr($effectiveDate2, 4,2), substr($effectiveDate2, 6,2), substr($effectiveDate2, 4,4) );
	
	$inceptDate = $fundAgg['effectiveInceptionDate'];

	$effectiveDate = mktime(5, 0, 0, substr($inceptDate, 4, 2), substr($inceptDate, 6, 2), substr($inceptDate, 0, 4));
		
	$processStart = time();
	
	//Get all tickets for a fund
	$query = '
			SELECT * 
			FROM '.$tradesTable.' 
			WHERE fund_id=:fund_id 
			ORDER BY unix_closed ASC
			';
	try{
			$rsPos = $mLink->prepare($query);
			$aValues = array(
					':fund_id'	=> $fundID
			);
			$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
			$rsPos->execute($aValues);
	}
	catch(PDOException $error){
			// Log any error
			//file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}
	
	//Initialize Arrays
	$aTrades 	= array();
	$aHoldings 	= array();
	$aShares	= array();
	$aCAs		= array();
	
	//loop through result
	while($trade = $rsPos->fetch(PDO::FETCH_ASSOC)){
		
		$aTrades[$trade['stockSymbol']][strtolower($trade['buyOrSell'])][] = array(
				'fund_id'		=> $trade['fund_id'],
				'company_id'	=> $trade['company_id'],
				'unix_opened'	=> $trade['unix_opened'],
				'unix_closed'	=> $trade['unix_closed'],
				'closed'		=> date('m d, Y', $trade['unix_closed']),
				'shares'		=> $trade['shares'],
				'sharesOrdered'	=> $trade['sharesOrdered'],
				'sharesFilled'	=> $trade['sharesFilled'],
				'price'			=> $trade['price'],
				'ticketStatus'	=> $trade['ticketStatus'],
				'createdByCA'	=> $trade['createdByCA'],
				'buyOrSell'		=> $trade['buyOrSell'],
				'net'			=> $trade['net']
	
		);
	
		//Set a var name to create dynamic variable to store ongoing share count for each trade date
		$shareTotal = $trade['stockSymbol'];
	
	
		if($trade['buyOrSell'] == 'buy'){
				//if the type is buy add shares to share total
				$$shareTotal = $$shareTotal + $trade['sharesFilled'];	
		}else{
				//if the type is sell, subtract shares from share total
				$$shareTotal = $$shareTotal - $trade['sharesFilled'];	
		}
		
		//stuff values into array for comparing later.
		$aShares[$shareTotal][$trade['unix_closed']] = $$shareTotal;
				
	}
	
	//Get array of current holdings price and today values
	$aStockList = array();
	foreach($aTrades as $stockSymbol=>$aTradeType){
			
			//if(strpos($stockSymbol, '.')){
				$stockSymbol = str_replace('.', '/',$stockSymbol);
			//}
			
			$aStockList[] = "'".$stockSymbol."'";	
	}
	
	//implode symbols into a string to use in a query
	$stockList = implode(',',$aStockList);
	
	
	$query = "
			SELECT Name AS companyName, Symbol AS symbol, Last AS CurrentPrice, PercentChangeFromPreviousClose AS chang
			FROM `stock_feed`
			WHERE `Symbol` IN (".$stockList.")
	";
	try{
			$rsSymbols = $fLink->prepare($query);
			$aValues = array();
			$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
			$rsSymbols->execute($aValues);
	}
	catch(PDOException $error){
			// Log any error
			//file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}
	$aStockInfo = array();
	while($foo = $rsSymbols->fetch(PDO::FETCH_ASSOC)){
			
			$getStockSymbol	= str_replace('/', '.',$foo['symbol']);
			
			$aStockInfo[$getStockSymbol] = array(
					'stockPrice'	=> $foo['CurrentPrice'],
					'today'			=> $foo['chang']
			);
	}
	
	//Loop through and figure out current holdings
	foreach($aTrades as $stockSymbol=>$aTradeType){
	
		$aBuys 	= $aTradeType['buy'];
		$aSells	= $aTradeType['sell'];
	
		//Set some vars
		$totalBuys 		= 0;
		$totalSells		= 0;
		$priceTotalBuys		= 0;
		$priceTotalSells	= 0;
	
		//Loop through buy tickets
		foreach($aBuys as $uid=>$aTicket){
	
				$totalBuys 		= $totalBuys + $aTicket['sharesFilled'];
				//$priceTotalBuys	= $priceTotalBuys + ($aTicket['price']*$aTicket['sharesFilled']);	
				
				$priceTotalBuys	= $priceTotalBuys + $aTicket['net'];
		}
	
		//Loop through sell tickets
		foreach($aSells as $uid=>$aTicket){
	
				$totalSells 		= $totalSells + $aTicket['sharesFilled'];
				//$priceTotalSells	= $priceTotalSells + ($aTicket['price']*$aTicket['sharesFilled']);
				
				$priceTotalSells	= $priceTotalSells + $aTicket['net'];	
		}
	
		#GET Position Information
		/*$query = "
				SELECT *
				FROM ".$posDetailsTable."
				WHERE fund_id=:fund_id AND stockSymbol=:stockSymbol
				ORDER BY timestamp DESC
				LIMIT 1
		";*/
		
		
		
		/*$query = "
			SELECT pd.*, ss.sector_code
			FROM members_fund_positions_details AS pd
			INNER JOIN stocks_symbols AS ss ON ss.symbol=pd.stockSymbol
			WHERE pd.fund_id=:fund_id AND pd.stockSymbol=:stockSymbol
			ORDER BY pd.timestamp DESC
			LIMIT 1
		";*/
		$query = "
			SELECT pd.*
			FROM members_fund_positions_details AS pd
			WHERE pd.fund_id=:fund_id AND pd.stockSymbol=:stockSymbol
			ORDER BY pd.timestamp DESC
			LIMIT 1
		";
		try{
				$rsPosInfo = $mLink->prepare($query);
				$aValues = array(
						':fund_id'	=> $fundID,
						':stockSymbol'	=> $stockSymbol
				);
				$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
				$rsPosInfo->execute($aValues);
		}
		catch(PDOException $error){
				// Log any error
				//file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
		}
		$posInfo = $rsPosInfo->fetch(PDO::FETCH_ASSOC);
		
		
		if(strpos($stockSymbol,'.') !== false){
			$stockSymbol2 = str_replace('.','/',$stockSymbol);
		}else{
			$stockSymbol2 = $stockSymbol;
		}
		#get the sector code
		$query = "
			SELECT sector_code
			FROM stocks_symbols
			WHERE symbol=:stockSymbol
		";
		try{
			$rsPosInfo2 = $mLink->prepare($query);
			$aValues = array(
					':stockSymbol'	=> $stockSymbol2
			);
			$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
			$rsPosInfo2->execute($aValues);
		}
		catch(PDOException $error){
				// Log any error
				//file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
		}
		$posInfo2 = $rsPosInfo2->fetch(PDO::FETCH_ASSOC);
		
		#CA info
		$caYield 	= $posInfo['caYield'];
		$caCost		= $posInfo['caCost'];
		$recentCAYield	= $posInfo['recentCAYield'];
		$recentCACost	= $posInfo['recentCACost'];
	
		#return Info
		$recentReturn	= $posInfo['recentReturn']; //current
		$totalReturn	= $posInfo['totalReturn']; //incept
	
		#sector style info
		$sectorID		= $posInfo2['sector_code'];
		$sector         = $posInfo['sectorBaseName'];
		$style          = $posInfo['style'];
		$subIndustry    = $posInfo['subIndustryBaseName'];
		
		#if sectorID is NULL assign it as unallocated
		if($sectorID == ''){$sectorID = '00';}
		
		
		//END GET POSITION INFORMATION
		
		
		
		switch($sector){
			case 'Energy'							: $sectorCode = '1';break;
			case 'Materials'						: $sectorCode = '2';break;
			case 'Industrials'						: $sectorCode = '3';break;
			case 'Consumer Discretionary'			: $sectorCode = '4';break;
			case 'Consumer Staples'					: $sectorCode = '5';break;
			case 'Health Care'						: $sectorCode = '6';break;
			case 'Financials'						: $sectorCode = '7';break;
			case 'Information Technology'			: $sectorCode = '8';break;
			case 'Telecommunication Services'		: $sectorCode = '9';break;
			case 'Utilities'						: $sectorCode = '10';break;
			case 'Unclassified'						: $sectorCode = '11';break;
			default: $sectorCode = '11';break;
		}
		
		switch($style){
			case 'Large Cap : Value'				: $styleCode = '1';break;
			case 'Large Cap : Blend'				: $styleCode = '1';break;
			case 'Large Cap : Growth'				: $styleCode = '1';break;
			case 'Large Cap : Unclassified Style'	: $styleCode = '5';break;
			case 'Mid Cap : Value'					: $styleCode = '2';break;
			case 'Mid Cap : Blend'					: $styleCode = '2';break;
			case 'Mid Cap : Growth'					: $styleCode = '2';break;
			case 'Mid Cap : Unclassified Style'		: $styleCode = '5';break;
			case 'Small Cap : Value'				: $styleCode = '3';break;
			case 'Small Cap : Blend'				: $styleCode = '3';break;
			case 'Small Cap : Growth'				: $styleCode = '3';break;
			case 'Small Cap : Unclassified Style'	: $styleCode = '5';break;
			case 'Micro Cap : Value'				: $styleCode = '4';break;
			case 'Micro Cap : Blend'				: $styleCode = '4';break;
			case 'Micro Cap : Growth'				: $styleCode = '4';break;
			case 'Unclassified Market Cap : Unclassified Style' : $styleCode = '5';break;
			default: $styleCode = '5';break;
		}
	
		//Run Calculations
		$currentShares 	= $totalBuys - $totalSells;
		$currentValue	= $currentShares * $aStockInfo[$stockSymbol]['stockPrice'];
	
		$query = "
			SELECT *
			FROM members_fund_liveprice
			WHERE fund_id=:fund_id
		";	
		
		try{
			$rsGetLivePrice = $mLink->prepare($query);
			$aValues = array(
				':fund_id' 	=> $fundID
			);
			$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
			$rsGetLivePrice->execute($aValues);
		}
		catch(PDOException $error){
			// Log any error
			//file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
		}
		
		$lp= $rsGetLivePrice->fetch(PDO::FETCH_ASSOC);
		
		$compliant1 = $lp['violatesCash35'];
		$compliant2 = $lp['violatesDiversification25'];
		$compliant3	= $lp['violatesDiversification10'];
		$compliant4	= $lp['isInMargin'];

		//Get open tickets for this fund and calculate the BUY POWER(adjusted cash value) for the fund.
		$query = "
			SELECT shares, quote_price
			FROM members_fund_tickets
			WHERE fund_id=:fund_id AND status='pending'
		";
		try{
			$rsGetTickets = $mLink->prepare($query);
			$aValues = array(
				':fund_id' 	=> $fundID
			);
			$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
			$rsGetTickets->execute($aValues);
		}
		catch(PDOException $error){
			// Log any error
			//file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
		}

		$pendingCash = 0;

		while($tickets= $rsGetTickets->fetch(PDO::FETCH_ASSOC)){

			$shares     = $tickets['shares'];
			$quotePrice = $tickets['quote_price'];
			if($quotePrice == NULL){
				$quotePrice = 0;
			}

			$pendingCash = $pendingCash + ($shares * $quotePrice);

		}

		$adjustedCash = $lp['cashValue'] - $pendingCash;

		$aLivePrice = array(
			'nav'			=> $lp['nav'],
			'stockValue'	=> $lp['stockValue'],
			'cashValue'		=> $lp['cashValue'],
			'totalValue'	=> $lp['totalValue'],
			'compCash'		=> $compliant1,
			'compDiv25'		=> $compliant2,
			'compDiv10'		=> $compliant3,
			'compMargin'	=> $compliant4,
			'fundName'		=> $fundInfo['fund_name'],
			'adjustedCash'  => $adjustedCash
		);
		$totalValue		= $aLivePrice['totalValue'];
		$stockValue     = $aLivePrice['stockValue'];
	
		$weight			= ($currentValue / $totalValue);
		$gains			= $currentValue + $priceTotalSells + $caYield - $priceTotalBuys - $caCost;
		
		
		#calculate Returns for postions that have 0
		/*if($totalReturn == 0){
			
			$startPrice = $currentValue - $gains;
			
			$totalReturn = number_format(((($currentValue - $startPrice)/$startPrice)*100),12,".","");
				
		}*/
		//echo $stockSymbol.'~'.$sectorID.'|';
		$aSectorID = explode('-',$sectorID);
		//print_r($aSectorID);
		
		//print_r($aListSectors);
		
		$rootSectorID =  $aSectorID[0];
		
		echo $rootSectorID.'~';

		$sectorName = $aListSectors[$rootSectorID];
		echo $sectorName.'|';
		
		
		
		#create an array of current holdings
		$aHoldings[$stockSymbol] = array(
				'price'         => $aStockInfo[$stockSymbol]['stockPrice'],
				'shares'        => $currentShares,
				'value'         => $currentValue,
				'weight'        => $weight,
				'gains'         => $gains,								
				'today'         => $aStockInfo[$stockSymbol]['today'],
				'recentReturn'  => $recentReturn,
				'totalReturn'   => $totalReturn,
				'sector_id'		=> $sectorID,
				'sector_name'	=> $sectorName,
				'sector'        => $sector,
				'subIndustry'   => $subIndustry,
				'style'         => $style
		);
		
		
		//delete exisiting record
		$query = "
			DELETE
			FROM ".$stratBasicTable."
			WHERE fund_id=:fund_id AND stockSymbol=:stockSymbol
		";
		try{
			$rsDelete = $mLink->prepare($query);
			$aValues = array(
					':fund_id'		=> $fundID,
					':stockSymbol'	=> $stockSymbol
			);
			$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
			$rsDelete->execute($aValues);
		}
		catch(PDOException $error){
				// Log any error
				//file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
		}
		
		//insert new strat record
		$query = "
			INSERT INTO ".$stratBasicTable."  (
				fund_id,
				timestamp,
				stockSymbol,
				currentPrice,
				totalShares,
				currentValue,
				fundRatio,
				gains,
				todayReturn,
				totalReturn,
				recentReturn,
				sector_id,
				sector,
				style,
				sector_code,
				style_code
			)VALUES(
				:fund_id,
				UNIX_TIMESTAMP(),
				:stockSymbol,
				:price,
				:shares,
				:value,
				:weight,
				:gains,
				:today,
				:totalReturn,
				:recentReturn,
				:sector_id,
				:sector,
				:style,
				:sector_code,
				:style_code
			)
		";
		try{
			$rsInsertStrat = $mLink->prepare($query);
			$aValues = array(
					':fund_id'		=> $fundID,
					':stockSymbol'	=> $stockSymbol,
					':price'		=> $aStockInfo[$stockSymbol]['stockPrice'],
					':shares'		=> $currentShares,
					':value'		=> $currentValue,
					':weight'		=> $weight,
					':gains'		=> $gains,
					':today'		=> $aStockInfo[$stockSymbol]['today'],
					':totalReturn'	=> $totalReturn,
					':recentReturn'	=> $recentReturn,
					':sector_id'	=> $sectorID,
					':sector'		=> $sectorName,
					':style'		=> $style,
					':sector_code'	=> $sectorCode,
					':style_code'	=> $styleCode
			);
			$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
			$rsInsertStrat->execute($aValues);
		}
		catch(PDOException $error){
				// Log any error
				//file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
		}
		
		/*$query = "
			SELECT fund_id, stockSymbol
			FROM ".$stratBasicTable."
			WHERE fund_id=:fund_id AND stockSymbol=:stockSymbol
		";
		try{
			$rsGetStrat = $mLink->prepare($query);
			$aValues = array(
					':fund_id'		=> $fundID,
					':stockSymbol'	=> $stockSymbol
			);
			$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
			$rsGetStrat->execute($aValues);
		}
		catch(PDOException $error){
				// Log any error
				//file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
		}
		$stratCnt = $rsGetStrat->rowCount();*/
		
		/*if($stratCnt > 0){
			//update
			$query = "
				UPDATE ".$stratBasicTable."
				SET timestamp=UNIX_TIMESTAMP(), currentPrice=:price, totalShares=:shares, currentValue=:value, fundRatio=:weight, gains=:gains, todayReturn=:today, totalReturn=:totalReturn, sector=:sector, style=:style, recentReturn=:recentReturn, sector_code=:sector_code, style_code=:style_code
				WHERE fund_id=:fund_id AND stockSymbol=:stockSymbol
			";
			try{
				$rsUpdateStrat = $mLink->prepare($query);
				$aValues = array(
						':fund_id'		=> $fundID,
						':stockSymbol'	=> $stockSymbol,
						':price'		=> $aStockInfo[$stockSymbol]['stockPrice'],
						':shares'		=> $currentShares,
						':value'		=> $currentValue,
						':weight'		=> $weight,
						':gains'		=> $gains,
						':today'		=> $aStockInfo[$stockSymbol]['today'],
						':totalReturn'	=> $totalReturn,
						':recentReturn'	=> $recentReturn,
						':sector'		=> $sector,
						':style'		=> $style,
						':sector_code'	=> $sectorCode,
						':style_code'	=> $styleCode
				);
				$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
				$rsUpdateStrat->execute($aValues);
			}
			catch(PDOException $error){
					// Log any error
					//file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
			}	
		}else{
			//insert
			$query = "
				INSERT INTO ".$stratBasicTable."  (
					fund_id,
					timestamp,
					stockSymbol,
					currentPrice,
					totalShares,
					currentValue,
					fundRatio,
					gains,
					todayReturn,
					totalReturn,
					recentReturn,
					sector,
					style,
					sector_code,
					style_code
				)VALUES(
					:fund_id,
					UNIX_TIMESTAMP(),
					:stockSymbol,
					:price,
					:shares,
					:value,
					:weight,
					:gains,
					:today,
					:totalReturn,
					:recentReturn,
					:sector,
					:style,
					:sector_code,
					:style_code
				)
			";
			try{
				$rsInsertStrat = $mLink->prepare($query);
				$aValues = array(
						':fund_id'		=> $fundID,
						':stockSymbol'	=> $stockSymbol,
						':price'		=> $aStockInfo[$stockSymbol]['stockPrice'],
						':shares'		=> $currentShares,
						':value'		=> $currentValue,
						':weight'		=> $weight,
						':gains'		=> $gains,
						':today'		=> $aStockInfo[$stockSymbol]['today'],
						':totalReturn'	=> $totalReturn,
						':recentReturn'	=> $recentReturn,
						':sector'		=> $sector,
						':style'		=> $style,
						':sector_code'	=> $sectorCode,
						':style_code'	=> $styleCode
				);
				$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
				$rsInsertStrat->execute($aValues);
			}
			catch(PDOException $error){
					// Log any error
					//file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
			}
			
		}*///End update or insert check
		
	}//end for each trade
	
	$aSectors   = array();
	$aSyles     = array();
	
	//print_r($aHoldings);
	
	//Group positions into sectors and styles
	foreach($aHoldings as $stockSymbol=>$aStockInfo2){
		
		//$aSectors[$aStockInfo2['sector']]['positions'][$stockSymbol] = $aStockInfo2;
		$aSectors[$aStockInfo2['sector_name']]['positions'][$stockSymbol] = $aStockInfo2;
		$aStyles[$aStockInfo2['style']]['positions'][$stockSymbol] = $aStockInfo2;
		
		$aStocks[] = $stockSymbol;
		
	}
	
	$sectorCnt = 0;
	//get sector and style totals
	foreach($aSectors as $sector=>$aSectorItems){
		//For each sector level
		
		$sectorCnt++;
		
		//reset vars
		$sectorPosCnt       = 0;
		$sectorValue        = 0;
		$sectorToday        = 0;
		$aSectorToday       = array();
		
		//only loop through positions
		foreach($aSectors[$sector]['positions'] as $symbol=>$aStockInfo3){
			if($aStockInfo3['shares'] > 0){
				$sectorPosCnt++;
				$sectorValue = $sectorValue + $aStockInfo3['value'];
				$aSectorToday[$symbol]    = $aStockInfo3['today'];
				$sectorID = $aStockInfo3['sector_id'];
			}
			
		}
		
		$aSectors[$sector]['allocation']        = ($sectorValue / $stockValue) *100;
		$aSectors[$sector]['value']				= $sectorValue;
		$aSectors[$sector]['today']             = (array_sum($aSectorToday) / $sectorPosCnt);
		$aSectors[$sector]['cnt']               = $sectorPosCnt;
		$aSectors[$sector]['todayArray']        = $aSectorToday;
		
		$aSectors[$sector]['sectorID']			= $sectorID;
		//$aSectors[$sector]['sectorID']		= $fundID.'-'.$sectorCnt;
	}
	
	$styleCnt = 0;
	foreach($aStyles as $style=>$aStyleItems){
		//For each sector level
		
		$styleCnt++;
		
		//reset vars
		$stylePosCnt       = 0;
		$styleValue        = 0;
		$styleToday        = 0;
		$aStyleToday       = array();
		
		//only loop through positions
		foreach($aStyles[$style]['positions'] as $symbol=>$aStockInfo4){
			if($aStockInfo4['shares'] > 0){
				$stylePosCnt++;
				$styleValue = $styleValue + $aStockInfo4['value'];
				$aStyleToday[$symbol]    = $aStockInfo4['today'];
			}
			
		}
		
		$aStyles[$style]['allocation']        	= ($styleValue / $stockValue) *100;
		$aStyles[$style]['value']				= $styleValue;
		$aStyles[$style]['today']             	= (array_sum($aStyleToday) / $stylePosCnt);
		$aStyles[$style]['cnt']              	= $stylePosCnt;
		$aStyles[$style]['todayArray']        	= $aStyleToday;
		$aStyles[$style]['styleID']				= $fundID.'-'.$styleCnt;
	}
	
	//Insert Sector Strat Records
	//remove existing records
	$query = "
		DELETE FROM ".$stratSectorTable."
		WHERE fund_id=:fund_id
	";
	try{
		$rsDeleteStrat = $mLink->prepare($query);
		$aValues = array(
				':fund_id'		=> $fundID
		);
		$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
		$rsDeleteStrat->execute($aValues);
	}
	catch(PDOException $error){
			// Log any error
			//file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}
	//remove existing records
	$query = "
		DELETE FROM ".$stratSecPosTable."
		WHERE fund_id=:fund_id
	";
	try{
		$rsDeleteStrat = $mLink->prepare($query);
		$aValues = array(
				':fund_id'		=> $fundID
		);
		$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
		$rsDeleteStrat->execute($aValues);
	}
	catch(PDOException $error){
			// Log any error
			//file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}
	
	//insert new sector strats
	foreach($aSectors as $sector=>$aSecInfo){
		
		$aPositions			= $aSecInfo['positions'];
		$secAllocation		= $aSecInfo['allocation'];
		$secToday			= $aSecInfo['today'];
		$secValue			= $aSecInfo['value'];
		$sectorID			= $aSecInfo['sectorID'];
		
		//Insert Sector Info
		$query = "
			INSERT INTO ".$stratSectorTable." (
				fund_id,
				sector_id,
				timestamp,
				sectorName,
				sectorAllocation,
				sectorTodayReturn,
				sectorValue
			)VALUES(
				:fund_id,
				:sector_id,
				UNIX_TIMESTAMP(),
				:sectorName,
				:sectorAllocation,
				:sectorTodayReturn,
				:value
			)
		";
		try{
			$rsInsertSector = $mLink->prepare($query);
			$aValues = array(
					':fund_id'				=> $fundID,
					':sector_id'			=> $sectorID,
					':sectorName'			=> $sector,
					':sectorAllocation'		=> $secAllocation,
					':sectorTodayReturn'	=> $secToday,
					':value'				=> $secValue
			);
			$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
			$rsInsertSector->execute($aValues);
		}
		catch(PDOException $error){
				// Log any error
				//file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
		}
		
		//echo $error;
		
		//loop through each position and insert them into the db
		foreach($aPositions as $stockSymbol=>$aStock){
			
			$price			= $aStock['price'];
			$shares			= $aStock['shares'];
			$value			= $aStock['value'];
			$weight			= $aStock['weight'];
			$gains			= $aStock['gains'];
			$today			= $aStock['today'];
			$recentReturn	= $aStock['recentReturn'];
			$totalReturn	= $aStock['totalReturn'];
			
			$query = "
				INSERT INTO ".$stratSecPosTable." (
					fund_id,
					sector_id,
					timestamp,
					stockSymbol,
					currentPrice,
					totalShares,
					currentValue,
					fundRatio,
					gains,
					todayReturn,
					totalReturn,
					recentReturn
				)VALUES(
					:fund_id,
					:sector_id,
					UNIX_TIMESTAMP(),
					:stockSymbol,
					:price,
					:shares,
					:value,
					:weight,
					:gains,
					:today,
					:totalReturn,
					:recentReturn
				)
			";
			try{
				$rsInsertSectorPos = $mLink->prepare($query);
				$aValues = array(
						':fund_id'			=> $fundID,
						':sector_id'		=> $sectorID,
						':stockSymbol'		=> $stockSymbol,
						':price'			=> $price,
						':shares'			=> $shares,
						':value'			=> $value,
						':weight'			=> $weight,
						':gains'			=> $gains,
						':today'			=> $today,
						':totalReturn'		=> $totalReturn,
						':recentReturn'		=> $recentReturn
				);
				$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
				$rsInsertSectorPos->execute($aValues);
			}
			catch(PDOException $error){
					// Log any error
					//file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
			}
		}//end foreach position
	}//end foreach sector
	
	
	//INSERT STYLE RECORDS
	//remove existing records
	$query = "
		DELETE FROM ".$stratStyleTable."
		WHERE fund_id=:fund_id
	";
	try{
		$rsDeleteStrat = $mLink->prepare($query);
		$aValues = array(
				':fund_id'		=> $fundID
		);
		$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
		$rsDeleteStrat->execute($aValues);
	}
	catch(PDOException $error){
			// Log any error
			//file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}
	//remove existing records
	$query = "
		DELETE FROM ".$stratStylePosTable."
		WHERE fund_id=:fund_id
	";
	try{
		$rsDeleteStrat = $mLink->prepare($query);
		$aValues = array(
				':fund_id'		=> $fundID
		);
		$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
		$rsDeleteStrat->execute($aValues);
	}
	catch(PDOException $error){
			// Log any error
			//file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
	}
	
	//insert new style strats
	foreach($aStyles as $style=>$aStyleInfo){
		
		$aPositions			= $aStyleInfo['positions'];
		$styleAllocation	= $aStyleInfo['allocation'];
		$styleToday			= $aStyleInfo['today'];
		$styleID			= $aStyleInfo['styleID'];
		$styleValue			= $aStyleInfo['value'];
		
		//Insert Style Info
		$query = "
			INSERT INTO ".$stratStyleTable." (
				fund_id,
				style_id,
				timestamp,
				styleName,
				styleAllocation,
				styleTodayReturn,
				styleValue
			)VALUES(
				:fund_id,
				:style_id,
				UNIX_TIMESTAMP(),
				:styleName,
				:styleAllocation,
				:styleTodayReturn,
				:styleValue
			)
		";
		try{
			$rsInsertStyle = $mLink->prepare($query);
			$aValues = array(
					':fund_id'				=> $fundID,
					':style_id'				=> $styleID,
					':styleName'			=> $style,
					':styleAllocation'		=> $styleAllocation,
					':styleTodayReturn'		=> $styleToday,
					':styleValue'			=> $styleValue
			);
			$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
			$rsInsertStyle->execute($aValues);
		}
		catch(PDOException $error){
				// Log any error
				//file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
		}
		
		//loop through each position and insert them into the db
		foreach($aPositions as $stockSymbol=>$aStock){
			
			$price			= $aStock['price'];
			$shares			= $aStock['shares'];
			$value			= $aStock['value'];
			$weight			= $aStock['weight'];
			$gains			= $aStock['gains'];
			$today			= $aStock['today'];
			$recentReturn	= $aStock['recentReturn'];
			$totalReturn	= $aStock['totalReturn'];
			
			$query = "
				INSERT INTO ".$stratStylePosTable." (
					fund_id,
					style_id,
					timestamp,
					stockSymbol,
					currentPrice,
					totalShares,
					currentValue,
					fundRatio,
					gains,
					todayReturn,
					totalReturn,
					recentReturn
				)VALUES(
					:fund_id,
					:style_id,
					UNIX_TIMESTAMP(),
					:stockSymbol,
					:price,
					:shares,
					:value,
					:weight,
					:gains,
					:today,
					:totalReturn,
					:recentReturn
				)
			";
			try{
				$rsInsertStylePos = $mLink->prepare($query);
				$aValues = array(
						':fund_id'			=> $fundID,
						':style_id'			=> $styleID,
						':stockSymbol'		=> $stockSymbol,
						':price'			=> $price,
						':shares'			=> $shares,
						':value'			=> $value,
						':weight'			=> $weight,
						':gains'			=> $gains,
						':today'			=> $today,
						':totalReturn'		=> $totalReturn,
						':recentReturn'		=> $recentReturn
				);
				$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
				$rsInsertStylePos->execute($aValues);
			}
			catch(PDOException $error){
					// Log any error
					//file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
			}
		}//end foreach position
	}//end foreach style
	
	//Make sure to clear out errors by deleting records that dont exist in the stocks array.
	foreach($aStocks as $key=>$stock){
		$aStocks[$key] = "'".$stock."'";	
	}
	$stocks = implode(',',$aStocks);
	$query = "
		DELETE FROM ".$stratBasicTable."
		WHERE fund_id=:fund_id AND stockSymbol NOT IN(".$stocks.")
	";
	try{
		$rsDelete = $mLink->prepare($query);
		$aValues = array(
			':fund_id'	=> $fundID
		);
		$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
		$rsDelete->execute($aValues);
	}
	catch(PDOException $error){
	}
	
	
	//mark as finished
	$query = "
		UPDATE ".$fundsTable."
		SET processing='0'
		WHERE fund_id=:fund_id
	";
	try{
		$rsUpdate = $mLink->prepare($query);
		$aValues = array(
			':fund_id'	=> $fundID
		);
		$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
		$rsUpdate->execute($aValues);
	}
	catch(PDOException $error){
	}
}//end fund loop
?>

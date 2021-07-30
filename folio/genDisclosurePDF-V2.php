<?php
// This commandline batch script generates a PDF Fund Disclosure Report for use on MTR site (GIPS Funds only).
// *Note - this will not run within a web browser.

// Tell me when things go sideways
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Load debug & error logging functions
require_once("/var/www/html/includes/systemDebugFunctions.php");

// Connect to MySQL
require("/var/www/html/includes/dbConnectPDO.php");

// Get newest system config values
//require("/var/www/html/includes/getConfigPDO.php");

// Load some useful functions
//require("/var/www/html/includes/systemFunctions.php");

// Load PDF library
require('fpdf/fpdf.php');
//require('fpdf/fpdf_merge.php');

// Load extension to add image & text centering and inline font size manipulation (superscript)
class PDF extends FPDF {
	const DPI = 96;
	const MM_IN_INCH = 25.4;
	const PAPER_HEIGHT = 279.4;
	const PAPER_WIDTH = 215.9;
	// tweak these values (in pixels)
	const MAX_WIDTH = 450;
	const MAX_HEIGHT = 300;

	function pixelsToMM($val) {
		return $val * self::MM_IN_INCH / self::DPI;
	}

	function resizeToFit($imgFilename) {
		list($width, $height) = getimagesize($imgFilename);
		$widthScale = self::MAX_WIDTH / $width;
		$heightScale = self::MAX_HEIGHT / $height;
		$scale = min($widthScale, $heightScale);
		return array(
			round($this->pixelsToMM($scale * $width)),
			round($this->pixelsToMM($scale * $height))
		);
	}

	// These functions are set to work in Landscape.  For Portrait swap the PAPER_X const values

	function centerImageOnPage($img) {
		list($width, $height) = $this->resizeToFit($img);
		// you will probably want to swap the width/height
		// around depending on the page's orientation
		$this->Image(
			$img, (self::PAPER_HEIGHT - $width) / 2,
			(self::PAPER_WIDTH - $height) / 2,
			$width,
			$height
		);
	}

	function centerImageHorizontally($img, $verticalOffset) {
		list($width, $height) = $this->resizeToFit($img);
		// you will probably want to swap the width/height
		// around depending on the page's orientation
		$this->Image(
			$img, (self::PAPER_HEIGHT - $width) / 2,
			$verticalOffset,
			$width,
			$height
		);
	}

	function centerImageVertically($img, $horizontalOffset) {
		list($width, $height) = $this->resizeToFit($img);
		// you will probably want to swap the width/height
		// around depending on the page's orientation
		$this->Image(
			$img, $offset,
			(self::PAPER_WIDTH - $height) / 2,
			$horizontalOffset,
			$width,
			$height
		);
	}

	function centerTextHorizontally($text, $verticalOffset) {
		// you will probably want to swap the width/height
		// around depending on the page's orientation
		$this->Text(
			(self::PAPER_HEIGHT - $this->GetStringWidth($text)) / 2,
			$verticalOffset,
			$text
		);
	}

	// Function to allow for inline font size manipulation (superscript, subscript, large first letter, etc.)
	// Need to use the Write() function for the normal text, won't work in Cell() or Text()
	function subWrite($h, $txt, $link='', $subFontSize=12, $subOffset=0) {
		// resize font
		$subFontSizeold = $this->FontSizePt;
		$this->SetFontSize($subFontSize);

		// reposition y
		$subOffset = ((($subFontSize - $subFontSizeold) / $this->k) * 0.3) + ($subOffset / $this->k);
		$subX        = $this->x;
		$subY        = $this->y;
		$this->SetXY($subX, $subY - $subOffset);

		//Output text
		$this->Write($h, $txt, $link);

		// restore y position
		$subX        = $this->x;
		$subY        = $this->y;
		$this->SetXY($subX,  $subY + $subOffset);

		// restore font size
		$this->SetFontSize($subFontSizeold);
	}
}

// Parse passed arguments string to $_REQUEST array (i.e. "first=1&second=2&third=3" -> $_REQUEST['first'] = 1, etc.)
if (isset($argv[1])){
	parse_str($argv[1], $_REQUEST);
}

// Get passed fund_id
if (isset($_REQUEST['fund_id'])){
	$fund_id = $_REQUEST['fund_id'];
}else{
	$fund_id = "24-1"; //Testing
}

// NEED TO CHANGE THIS SO IT GRABS ALL THE GIPS FUNDS IF NO fund_id IS PASSED!


### Cover page

// Get needed data
// Get the funds we are tracking
$query = "
	SELECT m.*, mf.*
	FROM members m, members_fund mf
	WHERE m.member_id = mf.member_id
	AND mf.fund_id = :fund_id
";
try{
	$rsFund = $mLink->prepare($query);
	$aValues = array(
		':fund_id' => $fund_id
	);
	//$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
	$rsFund->execute($aValues);
}
catch(PDOException $error){
	// Log any error
	file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
}

$fund = $rsFund->fetch(PDO::FETCH_ASSOC);

// OK, finally start writing PDFs

// Instantiate the object
$pdf = new PDF();

$pdf->AddPage('L', 'Letter'); // Landscape, Letter
$pdf->setAutoPageBreak(0,5);

// MCM Logo
$pdf->Image('images/MCM-Logo.png',10,10,60);

// Marketocracy Logo
$pdf->centerImageHorizontally('images/Marketocracy-Logo-Tag.png',30); // Second parameter is vertical offset

// Set font for main title
$pdf->SetFont('Arial','',50);

// Title
$text = "Marketocracy's";
$pdf->centerTextHorizontally($text,85); // Second parameter is vertical offset
$text = $fund['fund_name'];
$pdf->centerTextHorizontally($text,105); // Second parameter is vertical offset

// Set font for subtitle
$pdf->SetFont('Arial','',27);

// Subtitle
$text = "Composite and Supplemental Information";
$pdf->centerTextHorizontally($text,125); // Second parameter is vertical offset

// Set font for statement
$pdf->SetFont('Arial','',22.5);

// GIPS Statement
if ($fund['gips_verified']){
//	$text = "The ".$fund['username'].":".$fund['fund_symbol']." (".$fund['fund_name'].") is a GIPS".chr(174);
//	$pdf->centerTextHorizontally($text,147); // Second parameter is vertical offset

	// This (harder) way allows for the superscript REG symbol.
	$fullText = "The ".$fund['username'].":".$fund['fund_symbol']." (".$fund['fund_name'].") is a GIPS ";
	$textWidth = $pdf->GetStringWidth($fullText);
	$pageWidth = 279.4;
	$leftMargin = (($pageWidth - $textWidth) / 2);
	$pdf->SetXY($leftMargin,145);
	$pdf->Write('',trim($fullText));
	$pdf->subWrite('',chr(174),'',14,8);

	$text = "Verifed Composite";
	$pdf->centerTextHorizontally($text,157); // Second parameter is vertical offset
}

// Call to action, with faux links
// Need to make each section (font change) a cell and lay them out side-by-side, no auto centering this time
$pdf->SetXY(50,177);
$pdf->SetFont('Arial','',10.8);
$text = "For more information or to invest, please go to ";
$pdf->Cell($pdf->GetStringWidth($text),3,$text,0,'L');

$pdf->SetFont('Arial','U',10.8);
$pdf->SetTextColor('17','85','204'); //HTML link blue
$text = "www.marketocracy.com";
$pdf->cell($pdf->GetStringWidth($text),3,$text,0,'L');

$pdf->SetFont('Arial','',10.8);
$pdf->SetTextColor('0','0','0'); //Black
$text = " or email ";
$pdf->cell($pdf->GetStringWidth($text),3,$text,0,'L');

$pdf->SetFont('Arial','U',10.8);
$pdf->SetTextColor('17','85','204'); //HTML link blue
$text = "sma@marketocracy.com";
$pdf->cell($pdf->GetStringWidth($text),3,$text,0,'L');

// Reset text color
$pdf->SetTextColor('0','0','0'); //Black

// Footer/address
$pdf->SetXY(-67,192);
$pdf->SetFont('Times','B',7);
$text = "Marketocracy Capital Management, LLC";
$width = $pdf->GetStringWidth($text);
$pdf->Cell($width,4,$text,0,'L');

$pdf->SetXY(-67,196);
$pdf->SetFont('Times','',7);
$text = "1212 E. Lancaster Avenue, Third Floor\nFort Worth, TX 76102\n(877) 462-4180 | (888) 777-6181 (fax)";
$pdf->MultiCell($width,3,$text,0,'L');

// Done building page one
// Save the file
$pdf->Output('F','cover.pdf'); // Doing the pages separately so we can build them in whatever order we need


////////////// Put growth chart page here, on new page 2, making 2 into 3, etc.


### Model table page

// Get the month-by-month model data
$query = "
	SELECT *
	FROM folio_fund_month_to_month
	WHERE fund_id = :fund_id
	ORDER BY unix_date DESC
";

try{
	$rsMTM = $tLink->prepare($query);
	$aValues = array(
		':fund_id' => $fund_id
	);
	//$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
	$rsMTM->execute($aValues);
}

catch(PDOException $error){
	// Log any error
	file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
}

// Assign DB values to an array for later processing
$aMTM = array();
$first = true;

while($months = $rsMTM->fetch(PDO::FETCH_ASSOC)){

/////// this may not be needed here as there's only one record per month AND the result set is in reverse order anyway. //////////
	// If this is the first row AND it's not the last day of the previous month we are in the middle of the current month, skip it and grab the next one.

	// This only works if the last day of the month was a trading day
	// NEED TO COMPARE DAY TO LAST TRADING DAY INSTEAD, SO I NEED OT FIGURE OUT HOW TO GET THE LAST TRADING DATES OF EACH MONTH!!!
	// I THINK BRANDON ALREADY HAS A FUNCTION THAT DOES THAT - LOOK FOR IT.
	// Maybe looking forward from that date to the next trading day, then seeing if the month changed would work???
//	if ($first && date("j", $months['unix_date']) == date("t", $months['unix_date'])){
	if ($first && date("j", strtotime('+1 day', $months['unix_date'])) != "1"){
//echo "Ack!\n";
		continue;
	}
	$first = false;

	$date	= $months['unix_date'];

	$aMTM[date('Ym',$date)] = array(
		'date'          => $date,
		'rMonth'        => date('F', $date),
		'value'         => $months['value'],
		'year'          => date('Y', $date),
		'YTD'           => $months['YTD'],
		'spYTD'         => $months['spYTD'],
		'spValue'       => $months['sp_value'],
		'spPrice'       => $months['sp_price']
	);

	$aChartFund['['.($date*1000)] = number_format($months['value'], 2, '.', ',').']';
	$aChartSP['['.($date*1000)] = number_format(($months['spYTD']*100), 2, '.', ',').']';

	if(!isset($asOfDate)){
		$asOfDate = date('m/d/Y', $months['unix_date']);
	}

}

// Create an empty array for massaged values
// Not sure why this is necessary but it's how the page code works...maybe I'll clean it up later
$aProcess = array();

foreach($aMTM as $key=>$aValues){

	$date = $aValues['date'];

	$day    = date('d', $date);
	$month  = date('m', $date);
	$year   = date('Y', $date);

	if(in_array($year, $aProcess)){

	}else{
		$aProcess[$year] = array(
			'01' => NULL,
			'02' => NULL,
			'03' => NULL,
			'04' => NULL,
			'05' => NULL,
			'06' => NULL,
			'07' => NULL,
			'08' => NULL,
			'09' => NULL,
			'10' => NULL,
			'11' => NULL,
			'12' => NULL
		);
	}
}

// Now assign values to it
// Again, not sure why since the don;t seem to be altered form the DB values but I'm just making it work, for now
foreach($aMTM as $key=>$aValues){

	$date           = $aValues['date'];

	$dateStr        = date('Ym', $date);

	$day            = date('d', $date);
	$month          = date('m', $date);
	$year           = date('Y', $date);

	foreach($aProcess as $years=>$aMonths){

		if($year == $years){

			foreach($aMonths as $months=>$aMonthValues){

				if($month == $months){
					$aProcess[$year][$month]['value']       = $aValues['value'];
					$aProcess[$year][$month]['rMonth']      = $aValues['rMonth'];
					$aProcess[$year][$month]['YTD']         = $aValues['YTD'];
					$aProcess[$year][$month]['spYTD']       = $aValues['spYTD'];
					$aProcess[$year][$month]['date']        = $dateStr;
				}
			}
		}
	}
}

// Instantiate the object again
$pdf = new PDF();

$pdf->AddPage('L', 'Letter'); // Landscape, Letter
$pdf->setAutoPageBreak(0,5);

// MCM Logo
$pdf->Image('images/MCM-Logo.png',10,10,60);

// Set font for subtitle
$pdf->SetFont('Arial','',34.5);

// Title
$text = $fund['fund_symbol']." Model Supplemental Information";
$pdf->centerTextHorizontally($text,40); // Second parameter is vertical offset

// Table title
$pdf->SetXY(25,54);
$pdf->SetFont('Arial','',12);
$text = $fund['fund_symbol']." Model Month By Month ";
$pdf->cell($pdf->GetStringWidth($text),3,$text,0,'L');

$pdf->SetFont('Arial','',8);
$text = "(As Of: ".$asOfDate.")";
$pdf->cell($pdf->GetStringWidth($text),4,$text,0,'L');

// Draw a decorative line
$pdf->SetDrawColor('225','225','225'); //Light gray
$pdf->Line(25,60,255,60);

// Model table - headers
$pdf->SetXY(25,62);
$pdf->SetFont('Arial','B',8);
$pdf->SetTextColor('255','255','255'); //White

$pdf->SetFillColor('252','179','34'); //Golden yellow
$pdf->cell(10,5,"Year",1,0,'C',1);

$pdf->SetFillColor('91','192','222'); //Medium light blue
$pdf->cell(15,5,"Jan",1,0,'C',1);
$pdf->cell(15,5,"Feb",1,0,'C',1);
$pdf->cell(15,5,"Mar",1,0,'C',1);
$pdf->cell(15,5,"Apr",1,0,'C',1);
$pdf->cell(15,5,"May",1,0,'C',1);
$pdf->cell(15,5,"Jun",1,0,'C',1);
$pdf->cell(15,5,"Jul",1,0,'C',1);
$pdf->cell(15,5,"Aug",1,0,'C',1);
$pdf->cell(15,5,"Sep",1,0,'C',1);
$pdf->cell(15,5,"Oct",1,0,'C',1);
$pdf->cell(15,5,"Nov",1,0,'C',1);
$pdf->cell(15,5,"Dec",1,0,'C',1);

$pdf->SetFillColor('66','42','136'); //Dark purple
$pdf->cell(17,5,$fund['fund_symbol']." YTD",1,0,'C',1);

$pdf->SetFillColor('0','108','163'); //Dark light blue
$pdf->cell(23,5,"S&P (TR) YTD",1,1,'C',1);

// Move to the beginning of the next table row and reset the font
$pdf->SetX(25);
$pdf->SetFont('Arial','',8);
$cnt = 1;

// Traverse array (built above) and output the rows
foreach($aProcess as $year=>$aMonths){

	// Set the default colors, alternating them for each row
	if($cnt&1){  //Odd?
		$cellFill	= "252,252,252";
		$ytdFill	= "226,223,238";
		$spFill		= "219,230,242";
		$textColor	= "0,0,0";
	}else{
		$cellFill	= "198,231,247";
		$ytdFill	= "170,159,203";
		$spFill		= "151,185,215";
		$textColor	= "0,0,0";
	}

	eval('$pdf->SetFillColor('.$cellFill.');');  // I KNOW eval() is no bueno, but the alternative is three separate vars for each RGB value...and this "works".
	eval('$pdf->SetTextColor('.$textColor.');');

	// Output the year
	$pdf->SetFont('Arial','',8);
	$pdf->cell(10,5,$year,1,0,'C',1);

	// Output the values for each month
	foreach($aMonths as $month=>$aValues){

		$mReturn	= $aValues['value'];
		$cReturn	= $aValues['composite'];
		$cYTD		= $aValues['compositeYTD'];

		if($mReturn != NULL){

			// Set fill colors based on data value
			if($mReturn < -5){
				$cellFill = "240,113,84";
				$textColor = "255,255,255";
			}elseif($mReturn > 5){
				$cellFill = "99,204,115";
				$textColor = "255,255,255";
			}else{ // Need this to reset back to default after a changed fill value is used
				$textColor = "0,0,0";
				if($cnt&1){  //Odd?
					$cellFill	= "252,252,252";
				}else{
					$cellFill	= "198,231,247";
				}
			}
			$monthReturn = number_format($mReturn, 2, '.', ',').'%';

		}else{

			// No valu for the given month, so print a "-"
			$monthReturn = '-';
			$textColor = "0,0,0";
			if($cnt&1){  //Odd?
				$cellFill	= "252,252,252";
			}else{
				$cellFill	= "198,231,247";
			}
	}

		// Assign totals
		if($aValues != NULL){
			$currentSpYTD	= number_format(($aValues['spYTD']), 2, '.', ',');
			$currentYTD		= number_format(($aValues['YTD']*100), 2, '.', ',');
		}

		// Output the cell
		eval('$pdf->SetFillColor('.$cellFill.');');
		eval('$pdf->SetTextColor('.$textColor.');');
		$pdf->cell(15,5,$monthReturn,1,0,'C',1);
	}

	// Output the totals
	eval('$pdf->SetFillColor('.$ytdFill.');');
	$pdf->SetTextColor(0,0,0);
	$pdf->SetFont('Arial','B',8);
	$pdf->cell(17,5,$currentYTD.'%',1,0,'C',1);

	eval('$pdf->SetFillColor('.$spFill.');');
	$pdf->SetTextColor(0,0,0);
	$pdf->cell(23,5,$currentSpYTD.'%',1,1,'C',1); // Setting the 5th value to 1 forces a pointer move to the next row

	// Indent
	$pdf->SetX(25);

	// Increment
	$cnt++;

}

// Make a little room
$pdf->cell(1,3,'',0,1);

$pdf->SetX(25);
$pdf->SetFillColor(99,204,115);
$pdf->cell(3,3,'',1,0,'C',1);

//$pdf->SetX(30);
$pdf->SetTextColor(0,0,0);
$pdf->SetFont('Arial','',6);
$text = " Returns greater than 5%";
$pdf->Cell($pdf->GetStringWidth($text)+5,4,$text,0,'L');

//$pdf->SetX(25);
$pdf->SetFillColor(240,113,84);
$pdf->cell(3,3,'',1,0,'C',1);

$text = " Returns less than -5%";
$pdf->Cell($pdf->GetStringWidth($text),4,$text,0,'L',1); // Setting the 5th value to 1 forces a pointer move to the next row

// Draw another decorative line
$pdf->SetDrawColor('225','225','225'); //Light gray
$pdf->Line(25,$pdf->GetY()+6,255,$pdf->GetY()+6);

// Footer
$pdf->SetFont('Times','',8);
$pdf->SetTextColor(0,0,0);
$pdf->SetXY(20,205);
$text = "Supplemental information to the ".$fund['username'].":".$fund['fund_symbol']." composite in the attached Composite Disclosure";
$width = $pdf->GetStringWidth($text);
$pdf->Cell($width,4,$text,0,'L');

$pdf->SetXY(-67,205);
$text = "Marketocracy Capital Management, LLC";
$width = $pdf->GetStringWidth($text);
$pdf->Cell($width,4,$text,0,'L');


// Done building......
// Save the file
$pdf->Output('F','model.pdf'); // Change this to something descriptive


### Composite table page

// Make sure you always build this page AFTER the model page because the model page is where the "As Of" date and the S&P returns are calculated, which are all used on this page too.

// Get the month-by-month composite data
$query = "
	SELECT *
	FROM members_fund_composite
	WHERE fund_id=:fund_id AND composite IS NOT NULL
	ORDER BY unix_date ASC
";

try{
	$rsMTMcomposite = $mLink->prepare($query);
	$aValues = array(
		':fund_id' => $fund_id
	);
	//$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
	$rsMTMcomposite->execute($aValues);
}

catch(PDOException $error){
	// Log any error
	file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
}

// Assign DB values to an array for later processing
$aMTMcomposite	= array();
$aProducts		= array();
$monthCnt		= 0;
$first			= true;

while($months = $rsMTMcomposite->fetch(PDO::FETCH_ASSOC)){

	// If this is the first row AND it's not the last day of the previous month we are in the middle of the current month, skip it and grab the next one.

	// This only works if the last day of the month was a trading day
	// NEED TO COMPARE DAY TO LAST TRADING DAY INSTEAD, SO I NEED OT FIGURE OUT HOW TO GET THE LAST TRADING DATES OF EACH MONTH!!!
	// I THINK BRANDON ALREADY HAS A FUNCTION THAT DOES THAT - LOOK FOR IT.
	// Maybe looking forward from that date to the next trading day, then seeing if the month changed would work???
//	if ($first && date("j", $months['unix_date']) == date("t", $months['unix_date'])){
	if ($first && date("j", strtotime('+1 day', $months['unix_date'])) != "1"){
//echo "Ack!\n";
		continue;
	}
	$first = false;

	$date			= $months['unix_date'];
	$compositeCalc	= $months['composite_calc'];
	$year			= date('Y', $date);
	$month			= date('m', $date);

	if($currentYear != $year){
		$currentYear = $year;
		$setSpYTDstart = true;
		$monthCnt = 0;
	}else{
		$setSpYTDstart = false;
		$monthCnt++;
	}

	$aProducts[$year][$month] = ($compositeCalc+1);

	$ytdProduct		= array_product($aProducts[$year]);
	$YTD			= (($ytdProduct - 1)*100);
	$currentSpValue = $aMTMcomposite[$year.$month]['spPrice'];

	if($setSpYTDstart == true){

		$newSPvalue = $aMTMcomposite[($year - 1).'12']['spPrice'];

		$spYTDstart = $newSPvalue;
	}

	$spYTD = ((($currentSpValue - $spYTDstart)/$spYTDstart)*100);

	$aMTMcomposite[] = array(
		'date'			=> $date,
		'returnYear'	=> date('Y', $date),
		'returnMonth'	=> date('m', $date),
		'return_calc'	=> $compositeCalc,
		'product_calc'	=> ($compositeCalc+1),
		'YTD'			=> $YTD,
		'spYTD'			=> $spYTD,
		'spPrice'		=> $currentSpValue,
'annualReturn'	=> $months['annual_return']

	);

	$asOfDateComposite = date('m/t/Y', $date);
	$showComposite = true;

}

// Create an empty array for massaged values
// Not sure why this is necessary but it's how the page code works...maybe I'll clean it up later
$aProcess2 = array();

foreach($aMTMcomposite as $key=>$aValues){

	$date = $aValues['date'];

	$day    = date('d', $date);
	$month  = date('m', $date);
	$year   = date('Y', $date);

	if(in_array($year, $aProcess2)){

	}else{
		$aProcess2[$year] = array(
			'01' => NULL,
			'02' => NULL,
			'03' => NULL,
			'04' => NULL,
			'05' => NULL,
			'06' => NULL,
			'07' => NULL,
			'08' => NULL,
			'09' => NULL,
			'10' => NULL,
			'11' => NULL,
			'12' => NULL
		);
	}
}

// Now assign values to it
// Again, not sure why since the don;t seem to be altered form the DB values but I'm just making it work, for now
foreach($aMTMcomposite as $key=>$aValues){

	$date           = $aValues['date'];

	$dateStr        = date('Ym', $date);

	$day            = date('d', $date);
	$month          = date('m', $date);
	$year           = date('Y', $date);

	foreach($aProcess2 as $years=>$aMonths){

		if($year == $years){

			foreach($aMonths as $months=>$aMonthValues){

				if($month == $months){

					if($month == 01){
						$spYearStart =  $aMTM[($year - 1).'12']['spPrice'];
					}

					if($spYearStart == '' && $aProcess2[$year][$month] == NULL){
						$spYearStart = $aMTM[$year.$month]['spPrice'];
					}

					$currentSPvalue = $aMTM[$year.$month]['spPrice'];
					$spYTD = ((($currentSPvalue - $spYearStart)/$spYearStart)*100);

					$aProcess2[$year][$month]['value']			= ($aValues['return_calc'] * 100);
					$aProcess2[$year][$month]['rMonth']			= $aValues['rMonth'];
					$aProcess2[$year][$month]['YTD']			= $aValues['YTD'];
					$aProcess2[$year][$month]['spYTD']			= $spYTD;
					$aProcess2[$year][$month]['spYTDvalue']		= $aValues['spPrice'];
					$aProcess2[$year][$month]['date']			= $dateStr;
					$aProcess2[$year][$month]['spYearStart']	= $spYearStart;
					$aProcess2[$year][$month]['currentSPvalue']	= $currentSPvalue;
					$aProcess2[$year][$month]['spYTDnew']		= $spYTDnew;
$aProcess2[$year][$month]['annualReturn']	= $aValues['annualReturn'];
				}
			}
		}
	}
}

// Get things lined up properly
krsort($aProcess2);

// Instantiate the object again
$pdf = new PDF();

$pdf->AddPage('L', 'Letter'); // Landscape, Letter
$pdf->setAutoPageBreak(0,5);

// MCM Logo
$pdf->Image('images/MCM-Logo.png',10,10,60);

// Set font for subtitle
$pdf->SetFont('Arial','',34.5);

// Title
//$text = $fund['fund_symbol']." GIPS".chr(174)." Composite Track Record";
//$pdf->centerTextHorizontally($text,40); // Second parameter is vertical offset

// This (harder) way allows for the superscript REG symbol.
$fullText = $fund['fund_symbol']." GIPS  Composite Track Record";
$textWidth = $pdf->GetStringWidth($fullText);
$pageWidth = 279.4;
$leftMargin = (($pageWidth - $textWidth) / 2);
$pdf->SetXY($leftMargin,36.4);
$pdf->Write('',$fund['fund_symbol']." GIPS");
$pdf->subWrite('',chr(174),'',18,14);
$pdf->Write('',' Composite Track Record');

// Table title
$pdf->SetXY(25,54);
$pdf->SetFont('Arial','',12);
$text = $fund['fund_symbol']." Composite Month By Month ";
$pdf->cell($pdf->GetStringWidth($text),3,$text,0,'L');

$pdf->SetFont('Arial','',8);
$text = "(As Of: ".$asOfDate.")";
$pdf->cell($pdf->GetStringWidth($text),4,$text,0,'L');

// Draw a decorative line
$pdf->SetDrawColor('225','225','225'); //Light gray
$pdf->Line(25,60,255,60);

// Model table - headers
$pdf->SetXY(25,62);
$pdf->SetFont('Arial','B',8);
$pdf->SetTextColor('255','255','255'); //White

$pdf->SetFillColor('252','179','34'); //Golden yellow
$pdf->cell(10,5,"Year",1,0,'C',1);

$pdf->SetFillColor('91','192','222'); //Medium light blue
$pdf->cell(15,5,"Jan",1,0,'C',1);
$pdf->cell(15,5,"Feb",1,0,'C',1);
$pdf->cell(15,5,"Mar",1,0,'C',1);
$pdf->cell(15,5,"Apr",1,0,'C',1);
$pdf->cell(15,5,"May",1,0,'C',1);
$pdf->cell(15,5,"Jun",1,0,'C',1);
$pdf->cell(15,5,"Jul",1,0,'C',1);
$pdf->cell(15,5,"Aug",1,0,'C',1);
$pdf->cell(15,5,"Sep",1,0,'C',1);
$pdf->cell(15,5,"Oct",1,0,'C',1);
$pdf->cell(15,5,"Nov",1,0,'C',1);
$pdf->cell(15,5,"Dec",1,0,'C',1);

$pdf->SetFillColor('66','42','136'); //Dark purple
$pdf->cell(17,5,$fund['fund_symbol']." YTD",1,0,'C',1);

$pdf->SetFillColor('0','108','163'); //Dark light blue
$pdf->cell(23,5,"S&P (TR) YTD",1,1,'C',1);

// Move to the beginning of the next table row and reset the font
$pdf->SetX(25);
$pdf->SetFont('Arial','',8);
$cnt = 1;

// Traverse array (built above) and output the rows
foreach($aProcess2 as $year=>$aMonths){

	// Set the default colors, alternating them for each row
	if($cnt&1){  //Odd?
		$cellFill	= "252,252,252";
		$ytdFill	= "226,223,238";
		$spFill		= "219,230,242";
		$textColor	= "0,0,0";
	}else{
		$cellFill	= "198,231,247";
		$ytdFill	= "170,159,203";
		$spFill		= "151,185,215";
		$textColor	= "0,0,0";
	}

	eval('$pdf->SetFillColor('.$cellFill.');');  // I KNOW eval() is no bueno, but the alternative is three separate vars for each RGB value...and this "works".
	eval('$pdf->SetTextColor('.$textColor.');');

	// Output the year
	$pdf->SetFont('Arial','',8);
	$pdf->cell(10,5,$year,1,0,'C',1);

	$monthCnt = 0;
	unset($startMonth);

	// Output the values for each month
	foreach($aMonths as $month=>$aValues){

		$mReturn	= $aValues['value'];
		$cReturn	= $aValues['composite'];
		$cYTD		= $aValues['compositeYTD'];
		$annualRet	= $aValues['annualReturn'];

		if($mReturn != NULL){

			if(!isset($startMonth)){
				$startMonth = $month;
			}

			// Set fill colors based on data value
			if($mReturn < -5){
				$cellFill = "240,113,84";
				$textColor = "255,255,255";
			}elseif($mReturn > 5){
				$cellFill = "99,204,115";
				$textColor = "255,255,255";
			}else{ // Need this to reset back to default after a changed fill value is used
				$textColor = "0,0,0";
				if($cnt&1){  //Odd?
					$cellFill	= "252,252,252";
				}else{
					$cellFill	= "198,231,247";
				}
			}
			$monthReturn = number_format($mReturn, 2, '.', ',').'%';

			$monthCnt++;

		}else{

			// No value for the given month, so print a "-"
			$monthReturn = '-';
			$textColor = "0,0,0";
			if($cnt&1){  //Odd?
				$cellFill	= "252,252,252";
			}else{
				$cellFill	= "198,231,247";
			}
	}

		// Assign totals
		if($aValues != NULL){
			$currentSpYTD	= number_format(($aValues['spYTD']), 2, '.', ',');
//			$currentYTD		= number_format(($aValues['YTD']*100), 2, '.', ',');
			$currentYTD		= number_format(($aValues['YTD']), 2, '.', ',');
		}

		// Output the cell
		eval('$pdf->SetFillColor('.$cellFill.');');
		eval('$pdf->SetTextColor('.$textColor.');');
		$pdf->cell(15,5,$monthReturn,1,0,'C',1);
	}

	// Assign the static annual return value...
	$annualReturn = number_format(($annualRet), 2, '.', ',');

	//...unless it's blank, then assign the current YTD value to it instead
	if ($annualRet == ""){
		$annualReturn = $currentYTD;
	}

	// Output the totals
	eval('$pdf->SetFillColor('.$ytdFill.');');
	$pdf->SetTextColor(0,0,0);
	$pdf->SetFont('Arial','B',8);
//	$pdf->cell(17,5,$currentYTD.'%',1,0,'C',1);
	$pdf->cell(17,5,$annualReturn.'%',1,0,'C',1);

	eval('$pdf->SetFillColor('.$spFill.');');
	$pdf->SetTextColor(0,0,0);

	// Add asterisks if partial first year - refers to disclaimer
	if($startMonth != 1){
		$pdf->cell(23,5,' '.' '.' '.' '.$currentSpYTD.'% **',1,1,'C',1); // leading spaces for pseudo-centering (multiple spaces in single string truncated)
	}else{
		$pdf->cell(23,5,$currentSpYTD.'%',1,1,'C',1);
	}

	// Indent
	$pdf->SetX(25);

	// Increment
	$cnt++;

}

// Make a little room
$pdf->cell(1,3,'',0,1);

$pdf->SetX(25);
$pdf->SetFillColor(99,204,115);
$pdf->cell(3,3,'',1,0,'C',1);

//$pdf->SetX(30);
$pdf->SetTextColor(0,0,0);
$pdf->SetFont('Arial','',6);
$text = " Returns greater than 5%";
$pdf->Cell($pdf->GetStringWidth($text)+5,4,$text,0,'L');

//$pdf->SetX(25);
$pdf->SetFillColor(240,113,84);
$pdf->cell(3,3,'',1,0,'C',1);

$text = " Returns less than -5%";
$pdf->Cell($pdf->GetStringWidth($text),4,$text,0,'L',1); // Setting the 5th value to 1 forces a pointer move to the next row

// Add disclaimer if partial first year
if($startMonth != 1){
	$text = "** The S&P500TR return represents the same partial year performance period as the composite";
	$pdf->Cell(255-($pdf->GetX()),4,$text,0,0,'R');
}

// Draw another decorative line
$pdf->SetDrawColor('225','225','225'); //Light gray
$pdf->Line(25,$pdf->GetY()+6,255,$pdf->GetY()+6);

// Footer
$pdf->SetFont('Times','',8);
$pdf->SetXY(-67,205);
$pdf->SetTextColor(0,0,0);
$text = "Marketocracy Capital Management, LLC";
$width = $pdf->GetStringWidth($text);
$pdf->Cell($width,4,$text,0,'L');


// Done building the composite table page
// Save the file
$pdf->Output('F','composite.pdf'); // Doing the pages separately so we can build them in whatever order we need


### Build the complete report PDF

// Name the result/output file (firstName-lastName-fundSymbol_composite-disclosure.pdf)
$outFile = $fund['name_first']."-".$fund['name_last'].'-'.$fund['fund_symbol'].'_composite-disclosure.pdf';

//$merge = new FPDF_Merge();
//$merge->Add('temp.pdf');
//$merge->Add('Wayne-Himelsein-LFF_composite-disclosure.pdf');
//$merge->Output('F',$outFile);

// fpdf_merge only works if the PDFs to be merged were all created suing FPDF
// Spawn an external command (pdftk) instead
//exec('pdftk temp.pdf '.$fund['composite_disclosure'].' cat output '.$outFile);

// Concatonate the cover, composite, model, and disclosure page
exec('pdftk cover.pdf composite.pdf model.pdf '.$fund['composite_disclosure'].' cat output '.$outFile);

// Move the output file to the right location and set the database var accordingly.

// Need to archive the old one first, so we have a paper trail............................

?>

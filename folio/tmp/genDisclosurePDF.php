<?php
/* Tell me what this does! */

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

// Load extension to add image centering
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

	// These functions are set to work in Landscape.  For Portrait swap the PAPER_X values

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

}



// Get needed data

// Get the funds we are tracking
$query = "
	SELECT m.username, mf.*
	FROM members m, members_fund mf
	WHERE m.member_id = mf.member_id
	AND mf.fund_id = '24-1'
";
try{
	$rsFund = $mLink->prepare($query);
	$rsFund->execute();
}
catch(PDOException $error){
	// Log any error
	file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
}

$fund = $rsFund->fetch(PDO::FETCH_ASSOC);

// Get the month-by-month data
$query = "
	SELECT *
	FROM folio_fund_month_to_month
	WHERE fund_id = :fund_id
	ORDER BY unix_date DESC
";

try{
	$rsMTM = $tLink->prepare($query);
	$aValues = array(
		':fund_id' => '24-1'

	);
	//$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
	$rsMTM->execute($aValues);
}

catch(PDOException $error){
	// Log any error
	file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
}

$aMTM = array();
$first = 1;

while($months = $rsMTM->fetch(PDO::FETCH_ASSOC)){


echo date("j", strtotime('+1 day', $months['unix_date']))."\n";

	if ($first == 1 && date("j", strtotime('+1 day', $months['unix_date'])) != "1"){
echo "Not Last DOM!\n";
		continue;
	}
	$first = 0;

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

//print_r($aMTM);



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

//print_r($aProcess);

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

//					if($showComposite == true){
						$aProcess[$year][$month]['composite'] = $aMTMc[$dateStr]['value'];
						$aProcess[$year][$month]['compositeYTD'] = $aMTMc[$dateStr]['YTD'];
//					}
				}

			}

		}

	}
}

//print_r($aProcess);






















// Instantiate the object
$pdf = new PDF();

// Page 1
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

// Statement
$text = "The ".$fund['username'].":".$fund['fund_symbol']." (".$fund['fund_name'].") is a GIPS".chr(174);
$pdf->centerTextHorizontally($text,147); // Second parameter is vertical offset
$text = "Verifed Composite";
$pdf->centerTextHorizontally($text,157); // Second parameter is vertical offset

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


// Page 2
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

$pdf->SetDrawColor('225','225','225'); //Light gray
$pdf->Line(25,60,255,60);

// Model table
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


$pdf->SetX(25);
$pdf->SetFont('Arial','',8);
$cnt = 1;

foreach($aProcess as $year=>$aMonths){

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
	$pdf->cell(10,5,$year,1,0,'C',1);

	foreach($aMonths as $month=>$aValues){

		$mReturn	= $aValues['value'];
		$cReturn	= $aValues['composite'];
		$cYTD		= $aValues['compositeYTD'];

		if($mReturn != NULL){

			if($mReturn < -5){
				$cellFill = "240,113,84";
				$textColor = "255,255,255";
			}elseif($mReturn > 5){
				$cellFill = "99,204,115";
				$textColor = "255,255,255";
			}else{
				$textColor = "0,0,0";
				if($cnt&1){  //Odd?
					$cellFill	= "252,252,252";
				}else{
					$cellFill	= "198,231,247";
				}
			}

			$monthReturn = number_format($mReturn, 2, '.', ',').'%';
		}else{
			$monthReturn = '-';
			$textColor = "0,0,0";
			if($cnt&1){  //Odd?
				$cellFill	= "252,252,252";
			}else{
				$cellFill	= "198,231,247";
			}
	}

		if($aValues != NULL){
			$currentSpYTD	= number_format(($aValues['spYTD']), 2, '.', ',');
			$currentYTD		= number_format(($aValues['YTD']*100), 2, '.', ',');
		}

		eval('$pdf->SetFillColor('.$cellFill.');');
		eval('$pdf->SetTextColor('.$textColor.');');
		$pdf->cell(15,5,$monthReturn,1,0,'C',1);
	}

	eval('$pdf->SetFillColor('.$ytdFill.');');
	$pdf->SetTextColor(0,0,0);
	$pdf->SetFont('Arial','B',8);
	$pdf->cell(17,5,$currentYTD,1,0,'C',1);

	eval('$pdf->SetFillColor('.$spFill.');');
	$pdf->SetTextColor(0,0,0);
	$pdf->cell(23,5,$currentSpYTD,1,1,'C',1);

	$pdf->SetX(25);
	$cnt++;

}




















// Footer
$pdf->SetFont('Times','',8);
$pdf->SetXY(20,205);
$text = "Supplemental information to the ".$fund['username'].":".$fund['fund_symbol']." composite in the attached Composite Disclosure";
$width = $pdf->GetStringWidth($text);
$pdf->Cell($width,4,$text,0,'L');

$pdf->SetXY(-67,205);
$text = "Marketocracy Capital Management, LLC";
$width = $pdf->GetStringWidth($text);
$pdf->Cell($width,4,$text,0,'L');


// Page 3
$pdf->AddPage('L', 'Letter'); // Landscape, Letter
$pdf->setAutoPageBreak(0,5);

// MCM Logo
$pdf->Image('images/MCM-Logo.png',10,10,60);

// Set font for subtitle
$pdf->SetFont('Arial','',34.5);

// Title
$text = $fund['fund_symbol']." GIPS".chr(174)." Composite Track Record";
$pdf->centerTextHorizontally($text,40); // Second parameter is vertical offset


// Composite Table Here!


// Footer
$pdf->SetFont('Times','',8);
$pdf->SetXY(-67,205);
$text = "Marketocracy Capital Management, LLC";
$width = $pdf->GetStringWidth($text);
$pdf->Cell($width,4,$text,0,'L');

// Save the file
$pdf->Output('F','temp.pdf'); // Change this to something descriptive

// Page 4 (and 5)
// Concatonate the annual disclosure statement onto the end of this PDF

//$merge = new FPDF_Merge();
//$merge->Add('temp.pdf');
//$merge->Add('Wayne-Himelsein-LFF_composite-disclosure.pdf');
//$merge->Output('F','complete.pdf');

exec('pdftk temp.pdf 24-1_Wayne-Himelsein-LFF_composite-disclosure_1551729988.pdf cat output Wayne-Himelsein-LFF_composite-disclosure.pdf');

?>

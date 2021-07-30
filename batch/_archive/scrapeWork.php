<?php

//include('/var/www/html/includes/simple_html_dom.php');

//$html = new simple_html_dom();

// Load from a string
//$html->load('<html><body><p>Hello World!</p><p>We're here</p></body></html>');

// Load a file
//$html->load_file('https://www.google.com/finance?q=INX&ei=7cHFV5i8E8G1jAHP8p7YAw');
//$html->load_file('https://www.marketocracy.com');
//$html->load_file('http://data.cnbc.com/quotes/.SPX');

# get an element
//$element = $html->find('td');

# modify it
//$element[1]->innertext .= " and we're here to stay.";

# output it!
//for ($x = 0; $x < sizeof($element); $x++){
//	echo $element[$x]->innertext."\n";
//}
//print_r($element);
//echo $html->save();



				$curl	= curl_init("http://data.cnbc.com/quotes/.SPXTR");
				curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
				$cnbc	= curl_exec($curl);

				$round1 = explode("var quoteDataObj = ", $cnbc);
				$round2 = explode("    CNBC_Settings = window.CNBC_Settings || {};", $round1[1]);
//				$rawData = $roundTwo[0];
				$round3 = explode("[", $round2[0]);
				$round4 = explode("]", $round3[1]);
				$json = json_decode($round4[0]);

echo $json->last;

//print_r($round4);
//echo $round4[0];
var_dump(json_decode($round4[0]));
/*
			// Pull out just the current price from the string
				$subString		= str_replace('&nbsp;', ' ', $feed);
				$subString		= strstr($subString, $aIndex[$indexCounter][2]);
				$subString		= strstr($subString, ' ');
				$subString		= substr($subString, 1);

				$indexPrice		= strstr($subString, ' ', true);

				// Now grab the change value
				$subString		= strstr($subString, '</span> ', true);
				$subString		= strstr($subString, '>');

				$indexChange	= substr($subString, 1);
*/


?>
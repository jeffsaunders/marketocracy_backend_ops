<?php

$fullpath = glob('/api2/fundprice_processing/fundprice_output_*');

//explode on /
//reverse array
//grab [0] as filename




foreach ($fullpath as $path){
////    echo $path."\n";
//	$explodepath = explode("/", $path);
////	print_r($explodepath);
//	$reversepath = array_reverse($explodepath);
////	print_r($reversepath);
//    echo $reversepath[0]."\n";


	$aFile = array_reverse(explode("/", $path));
	$file = $aFile[0];
	echo $file."\n";


//	die();
//	$reversepath = array_reverse($explodepath);
}

echo sizeof($fullpath);
?>

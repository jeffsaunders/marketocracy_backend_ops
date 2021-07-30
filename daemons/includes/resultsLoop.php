<?php
/*
This include is the main processing loop for all the API results daemons.
Vars defined in parent.
Called from daemons/<calling daemon>
*/
	// Set a counter for how many files have been processed during this run.
	$files = 0;

	// Let's use GLOB and only grab the output files from the start
	$filenames = glob($directory.'/'.$process.'_output_*');

	// Step through them
	foreach ($filenames as $fullpath){

		// If we've processed 1000 files, bail and start over (pick up where we left off) to clean out any cobwebs
		if ($files == 1000){
			break;
		}

		// Blow the full path to the file apart at the seams and flip it to get just the filename off the top
		$aFile = array_reverse(explode("/", $fullpath));
		$filename = $aFile[0];

		// if it's really a directory, and not a file, skip it
		if (is_dir($directory.$filename)){
			unset($filename);
			continue;
		}

		// Rename the output file
		$aFilename = explode("_", $filename);
		$newFilename = $aFilename[0]."_processing_".$aFilename[2]."_".$aFilename[3];
		rename($directory.$filename, $directory.$newFilename);
		$filename = $newFilename;

		// Increment counter
		$files++;

		// Open it up
		$fh = fopen($directory.$filename, "r");

		// Rip it's guts out
		$contents = fread($fh, filesize($directory.$filename)+1);

		// Sew it back up
		fclose($fh);

		// Check it's contents for any <error> tags - if so, log it and loop
		if (strpos($contents, "<error>") !== false){

			$aContents	= explode("</error>", $contents);
			$aError		= explode("<error>", $aContents[0]);
			$input		= simplexml_load_string($aContents[1]);
			$transID	= $input->trans_ID;

			$query =	"INSERT INTO ".$fetch_errors_table." (
							timestamp,
							server,
							query,
							error,
							contents
						) VALUES (
							UNIX_TIMESTAMP(),
							:server,
							:query,
							:error,
							:contents
						)";
			try{
				$rsInsert = $mLink->prepare($query);
				$aValues = array(
					':server'		=> $aAPI[1],
				   	':query'		=> addslashes($aContents[1]),
				   	':error'		=> addslashes($aError[1]),
				   	':contents'		=> addslashes($contents)
				);
				$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
				//echo $preparedQuery."\n";
				$rsInsert->execute($aValues);
			}
			catch(PDOException $error){
				// Log any error
				file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
			}

			// Update log_transactions_api record with the error too, then mark it closed
			$query = "	UPDATE ".$legacy_api_trans_table."
						SET	processing			= 0,
							xml_recv			= :query,
							error				= :error,
							xml_recv_timestamp	= UNIX_TIMESTAMP(),
							completion_timestamp = UNIX_TIMESTAMP()
						WHERE trans_id			= :transID
			";
			try{
				$rsUpdate = $mLink->prepare($query);
				$aValues = array(
				   	':query'		=> addslashes($aContents[1]),
				   	':error'		=> addslashes($aError[1]),
				   	':transID'		=> $transID
				);
				$preparedQuery = str_replace(array_keys($aValues), array_values($aValues), $query); //Debug
				//echo $preparedQuery."\n";
				$rsUpdate->execute($aValues);
			}
			catch(PDOException $error){
				// Log any error
				file_put_contents($pdo_log, "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
			}

			// Delete file
			unlink($directory.$filename);

			// Skip to the top (next file)
			continue;
		}

		// OK, not an error, parse it into an XML object so we can process it
		$xml = simplexml_load_file($directory.$filename);

		// Grab the method
		$method	= $xml->method;

		// Pull in the switch/case code for results processing
		include("resultsProcessing.php");

		// Delete file
		unlink($directory.$filename);

		//if we are processing the overflow directory don't stop after 1000...just keep going
		if (isset($overflow) && $overflow == true){
			$files = 0;
		}

	} // End foreach

?>
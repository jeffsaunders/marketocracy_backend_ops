<?php
/*
This include covers the top half of the main processing loop for all the API results daemons.
There is a matching bottom half that must be included after the query method processing includes.
*/

	// Open the directory
//	if ($dh = opendir($directory)){

		// Set a counter for how many files have been processed during this run.
		$files = 0;

		// Read in each file, one at a time...skip directories
		// If we've processed 500 files, bail and start over (pick up where we left off) to clean out any cobwebs
		while (($filename = readdir($dh)) !== false && $files <= 500){

			// if it's really a directory, and not a file, skip it
			if (is_dir($directory.$filename)){
				continue;
			}

			// If it's a file with the word "_output" in it's name, process it
			if (is_file($directory.$filename) && strpos($filename, "_output")) {

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
// Instead of doing this (which was designed to not require the fopen before we added the additional logging that now needs it) just look for <error> in the $contents
//				if (exec('grep '.escapeshellarg("<error>").' '.$directory.$filename)) {
					// Open it up
//					$fh = fopen($directory.$filename, "r");

					// Rip it's guts out
//					$contents = fgets($fh, filesize($directory.$filename)+1);

					// Sew it back up
//					fclose($fh);

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
						file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
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
						file_put_contents($_SESSION['pdo_log'], "-----\rDate: ".date('Y-m-d H:i:s')."\rFile: ". __FILE__ ."\rLine Number: ". __LINE__ ."\rVars:\r".dump_vars(get_defined_vars())."\r", FILE_APPEND);
					}

					// Delete file
					unlink($directory.$filename);

					// Skip to the top (next file)
					continue;
				}

?>
<?php
/*
This include processes the returned XML for the priceManagerDate method (fundpriceProcessing API).
Called from daemons/includes/resultsProcessing.php

Example source:
<results><method>priceManager</method><trans_ID>999</trans_ID><status>sent</status></results>
*/

								// Set main level vars
								$transID	= $xml->trans_ID;
								$aContents	= explode("?>", $contents);

								// Nothing to do here...

?>
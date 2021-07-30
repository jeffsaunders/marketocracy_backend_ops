<?php
/*
This include processes the returned XML for the setLegacyOn method (manageradminProcessing API).
Called from daemons/includes/resultsProcessing.php

Example source:
<results><method>setLegacyOn</method><trans_ID>999</trans_ID><member_ID>999</member_ID><status>success</status></results>
<results><method>setLegacyOn</method><trans_ID>999</trans_ID><member_ID>999</member_ID><status>fail</status></results>
*/

								// Set main level vars
								$transID	= $xml->trans_ID;
								$aContents	= explode("?>", $contents);

								// Nothing to do here...YET

?>
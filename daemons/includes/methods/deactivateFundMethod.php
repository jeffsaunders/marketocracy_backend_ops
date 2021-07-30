<?php
/*
This include processes the returned XML for the deactivateFund method (manageradminProcessing API).
Called from daemons/includes/resultsProcessing.php

Example source:
<results><method>deactivateFund</method><trans_ID>999</trans_ID><fund_ID>999-1</fund_ID><status>success</status></results>
*/

								// Set main level vars
								$transID	= $xml->trans_ID;
								$aContents	= explode("?>", $contents);

								// Nothing to do here...

?>
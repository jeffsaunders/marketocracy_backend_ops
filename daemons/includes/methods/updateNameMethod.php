<?php
/*
This include processes the returned XML for the updateName method (manageradminProcessing API).
Called from daemons/includes/resultsProcessing.php

Example source:
<results><method>updateName</method><trans_ID>999</trans_ID><fund_ID>999-1</fund_ID><name>New Fund Name</name></results>
*/

								// Set main level vars
								$transID	= $xml->trans_ID;
								$aContents	= explode("?>", $contents);

								// Nothing to do here...

?>
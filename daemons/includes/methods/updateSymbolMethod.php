<?php
/*
This include processes the returned XML for the updateSymbol method (manageradminProcessing API).
Called from daemons/includes/resultsProcessing.php

Example source:
<results><method>updateSymbol</method><trans_ID>999</trans_ID><fund_ID>999-1</fund_ID><symbol>NEW1</symbol></results>
where <symbol> is the new symbol just changed to.
*/

								// Set main level vars
								$transID	= $xml->trans_ID;
								$aContents	= explode("?>", $contents);

								// Nothing to do here...

?>
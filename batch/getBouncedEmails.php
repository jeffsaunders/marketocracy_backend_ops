<?php
// Define the email connection parameters
$host = "192.168.111.201";
$port = "143";
$optn = "/novalidate-cert";
$box  = "INBOX";
$user = "no-reply";
$pass = "KfabyZcbE3";

// Connect to the mail server
$mbox = imap_open("{".$host.":".$port.$optn."}".$box, $user, $pass);

//echo "<h1>Mailboxes</h1>\n";
//$folders = imap_listmailbox($mbox, "{mail.marketocracy.net:143}", "*");

//if ($folders == false) {
//    echo "Call failed<br />\n";
//} else {
//    foreach ($folders as $val) {
//        echo $val . "<br />\n";
//    }
//}

//echo "<h1>Headers in INBOX</h1>\n";
$headers = imap_headers($mbox);

//if ($headers == false) {
//    echo "Call failed<br />\n";
//} else {
//    foreach ($headers as $val) {
//        echo $val . "<br />\n";
//    }
//}

// Loop through all the emails in the box
for ($msg = 1; $msg <= count($headers); $msg++){
//	print_r(imap_headerinfo($mbox, $msg));
//      echo imap_body($mbox, $msg)."\n";

	// Rip out the body
	$body = imap_body($mbox, $msg);

	//Find the first tag (change to <camp_id> to find the embedded campaign id)
	$pieces = explode("<title>", $body);

	// Find the closing tag and chop it and the rmainder off 
	$parts = explode("</title>", $pieces[1]);

	// What remains is the value between the tags
	$title = $parts[0];
	echo $title."\n";

// Now we can test for it and branch accordingly....
// Can add a fifth parameter to mail() to force bounced emails to a different mailbox as follows:
// mail(to, subject, message, headers, "-fbounce@marketocracy.net"); 
// "bounce" already exists...
}


imap_close($mbox);
?>


<?php

require_once("../src/curl.php");

$session = new curl();

$m1 = new curl_request();
$m1->set_url("http://google.com/");

$r1 = $session->run($m1); // returns a curl_response object

echo $r1->status_code;

?>
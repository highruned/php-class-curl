<?php

require_once("../src/curl.php");

$session = new curl();

$session->set_url("http://google.com/");

$r1 = $session->run(); // returns a curl_response object

echo $r1->status_code;

?>
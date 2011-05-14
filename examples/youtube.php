<?php

require_once("../src/curl.php");

$session = new curl();

$m1 = new curl_request();
$m1->set_url("http://www.youtube.com/");

$session->run($m1, function($r1) use(&$session, &$m1) {
	print "[youtube] Loaded home page.\n";

	for($i = 1; $i <= 5; ++$i) {
		$m1->set_url("http://www.youtube.com/videos?p=" . $i);

		$session->run($m1, function($r1) use($i) {
			print "[youtube] Loaded video page {$i} ({$r1->status_code}).\n";
		});
	}
});

while(true) {
	$session->update();
	echo "."; // debug
	usleep(20000);
}
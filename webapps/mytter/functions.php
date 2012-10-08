<?php
function format_time($time) {
	$dtzone = new DateTimeZone("GMT");
	$dtime = new DateTime();
	$dtime->setTimestamp($time);
	$dtime->setTimeZone($dtzone);
	return $dtime->format("D M d H:i:s O Y");
}


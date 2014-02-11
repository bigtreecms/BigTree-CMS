<?
	foreach ($callouts as $callout) {
		include "../templates/callouts/".$callout["type"].".php";
	}
?>
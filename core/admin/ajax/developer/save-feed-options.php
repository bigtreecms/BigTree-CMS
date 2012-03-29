<?
	header("Content-type: text/javascript");
?>
$("#feed_options").val("<?=str_replace(array("\n","\r",'"'),array(' ',' ','\"'),json_encode($_POST))?>");
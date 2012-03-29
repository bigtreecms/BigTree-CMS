<?
	header("Content-type: text/javascript");
?>
$("#view_options").val("<?=addslashes(str_replace(array("\n","\r"),array(' ',' '),json_encode($_POST)))?>");
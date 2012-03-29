<?
	header("Content-type: text/javascript");
?>
$("#options_<?=$_GET["key"]?>").val("<?=str_replace(array("\n","\r",'"'),array(' ',' ','\"'),json_encode($_POST))?>");
<?
	header("Content-type: text/javascript");
?>
$("#options_<?=htmlspecialchars(strip_tags($_GET["key"]))?>").val("<?=str_replace(array("\n","\r",'"'),array(' ',' ','\"'),json_encode($_POST))?>");
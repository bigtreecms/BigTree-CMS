<?
	$view_data = isset($_GET["view_data"]) ? "&view_data=".$_GET["view_data"] : "";
?>
<div class="container">
	<section>
		<div class="alert">
			<span></span>
			<h3>LOCKED</h3>
		</div>
		<p>
			<strong><?=$locked_by["name"]?></strong> currently has this entry locked for editing.  It was last accessed by <strong><?=$locked_by["name"]?></strong> on <strong><?=date("F j, Y @ g:ia",strtotime($last_accessed))?></strong>.<br />
		If you would like to edit it anyway, please click "Unlock" below.  Otherwise, click "Cancel".
		</p>
	</section>
	<footer>
		<a href="?force=true<?=$view_data?>" class="button blue">Unlock</a>
		&nbsp;
		<a href="javascript:history.go(-1);" class="button white">Cancel</a>
	</footer>
</div>
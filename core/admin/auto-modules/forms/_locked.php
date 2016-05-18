<?php
	namespace BigTree;
	
	$view_data = isset($_GET["view_data"]) ? "&view_data=".htmlspecialchars($_GET["view_data"]) : "";
?>
<div class="container">
	<section>
		<div class="alert">
			<span></span>
			<h3><?=Text::translate("Locked")?></h3>
		</div>
		<p>
			<strong><?=$locked_by["name"]?></strong> <?=Text::translate("currently has this entry locked for editing.  It was last accessed by")?> <strong><?=$locked_by["name"]?></strong> <?=Text::translate("on")?> <strong><?=date("F j, Y @ g:ia",strtotime($last_accessed))?></strong>.<br />
			<?=Text::translate("If you would like to edit it anyway, please click \"Unlock\" below.  Otherwise, click \"Cancel\".")?>
		</p>
	</section>
	<footer>
		<a href="?force=true<?=$view_data?>" class="button blue"><?=Text::translate("Unlock")?></a>
		&nbsp;
		<a href="javascript:history.go(-1);" class="button white"><?=Text::translate("Cancel")?></a>
	</footer>
</div>
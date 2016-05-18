<?php
	namespace BigTree;
?>
<div class="container">
	<section>
		<div class="alert">
			<span></span>
			<h3><?=Text::translate("Locked")?></h3>
		</div>
		<p>
			<strong><?=$locked_by["name"]?></strong> <?=Text::translate("currently has this page locked for editing.  It was last accessed by")?> <strong><?=$locked_by["name"]?></strong> <?=Text::translate("on")?> <strong><?=date("F j, Y @ g:ia",strtotime($last_accessed))?></strong>.<br />
			<?=Text::translate("If you would like to edit this page anyway, please click \"Unlock\" below.  Otherwise, click \"Cancel\".")?>
		</p>
	</section>
	<footer>
		<a href="javascript:history.go(-1);" class="button white"><?=Text::translate("Cancel")?></a>
		&nbsp;
		<a href="?force=true" class="button blue"><?=Text::translate("Unlock")?></a>
	</footer>
</div>
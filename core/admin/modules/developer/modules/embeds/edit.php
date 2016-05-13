<?php
	namespace BigTree;
	
	$form = \BigTreeAutoModule::getEmbedForm(end($bigtree["commands"]));
	\BigTree::globalizeArray($form);
	$module = $admin->getModule($module);

	if (!SQL::tableExists($table)) {
?>
<div class="container">
	<section>
		<div class="alert">
			<span></span>
			<h3><?=Text::translate("Error")?></h3>
		</div>
		<p><?=Text::translate("The table for this form (:table:) no longer exists.", false, array(":table:" => $table))?></p>
	</section>
	<footer>
		<a href="javascript:history.go(-1);" class="button"><?=Text::translate("Back")?></a>
		<a href="<?=DEVELOPER_ROOT?>modules/interfaces/delete/<?=$form["id"]?>/?module=<?=$module["id"]?>" class="button red"><?=Text::translate("Delete Form")?></a>
	</footer>
</div>
<?php
	} else {
?>
<div class="container">
	<form method="post" action="<?=DEVELOPER_ROOT?>modules/embeds/update/<?=$form["id"]?>/" class="module">
		<?php include Router::getIncludePath("admin/modules/developer/modules/embeds/_form.php") ?>
		<section class="sub">
			<label><?=Text::translate("Embed Code <small>(not editable)</small>")?></label>
			<textarea><?=htmlspecialchars('<div id="bigtree_embeddable_form_container_'.$id.'">'.$title.'</div>'."\n".'<script type="text/javascript" src="'.ADMIN_ROOT.'js/embeddable-form.js?id='.$id.'&hash='.$hash.'"></script>')?></textarea>
		</section>
		<section class="sub" id="field_area">
			<?php include Router::getIncludePath("admin/ajax/developer/load-form.php") ?>
		</section>
		<footer>
			<input type="submit" class="button blue" value="<?=Text::translate("Update", true)?>" />
		</footer>
	</form>
</div>
<?php
		include Router::getIncludePath("admin/modules/developer/modules/forms/_footer.php");
	}
?>
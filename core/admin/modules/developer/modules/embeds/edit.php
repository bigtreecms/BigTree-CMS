<?php
	$form = BigTreeAutoModule::getEmbedForm(end($bigtree["commands"]));
	BigTree::globalizeArray($form);
	$module = $admin->getModule($module);

	if (!BigTree::tableExists($table)) {
?>
<div class="container">
	<section>
		<div class="alert">
			<span></span>
			<h3>Error</h3>
		</div>
		<p>The table for this form (<?=$table?>) no longer exists.</p>
	</section>
	<footer>
		<a href="javascript:history.go(-1);" class="button">Back</a>
	</footer>
</div>
<?php
	} else {
?>
<div class="container">
	<form method="post" action="<?=DEVELOPER_ROOT?>modules/embeds/update/<?=$form["id"]?>/" class="module module_embed_form_form">
		<?php include BigTree::path("admin/modules/developer/modules/embeds/_form.php"); ?>
		<section class="sub">
			<label>Embed Code <small>(not editable)</small></label>
			<textarea><?=htmlspecialchars('<div id="bigtree_embeddable_form_container_'.$id.'">'.$title.'</div>'."\n".'<script type="text/javascript" src="'.ADMIN_ROOT.'js/embeddable-form.js?id='.$id.'&hash='.$hash.'"></script>')?></textarea>
		</section>
		<section class="sub" id="field_area">
			<?php include BigTree::path("admin/ajax/developer/load-form.php"); ?>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Update" />
		</footer>
	</form>
</div>
<?php
		include BigTree::path("admin/modules/developer/modules/forms/_footer.php");
	}
?>
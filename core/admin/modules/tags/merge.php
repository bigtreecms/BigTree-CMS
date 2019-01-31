<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */
	
	$tag = new Tag($bigtree["commands"][0]);

	$field = new Field([
		"title" => Text::translate("Tag to Merge Into"),
		"type" => "list",
		"key" => "merge_to",
		"settings" => [
			"list_type" => "db",
			"pop-table" => "bigtree_tags",
			"pop-id" => "id",
			"pop-description" => "tag",
			"pop-sort" => "tag",
			"validation" => "required"
		]
	]);
?>
<div class="container">
	<form method="post" action="<?=ADMIN_ROOT?>tags/merge-process/" id="tag_merge_form">
		<input type="hidden" name="tag_id" value="<?=$tag->ID?>">
		<section>
			<fieldset>
				<label for="tag_field_name"><?=Text::translate("Tag Name")?></label>
				<input type="text" disabled value="<?=Text::htmlEncode($tag->Name)?>" name="tag" class="tag_field_name" id="tag_field_name">
			</fieldset>

			<?php
				$field->draw();
			?>
		</section>
		<footer>
			<input type="submit" class="button blue" value="<?=Text::translate("Merge Tag")?>">
		</footer>
	</form>
</div>
<script>
	BigTreeFormValidator("#tag_merge_form", false);
</script>
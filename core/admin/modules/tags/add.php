<?php
	namespace BigTree;

	$field = new Field([
		"title" => Text::translate("Tags to Merge In"),
		"type" => "one-to-many",
		"key" => "merge_tags",
		"settings" => [
			"table" => "bigtree_tags",
			"title_column" => "tag",
			"sort_by_column" => "tag"
		]
	]);
?>
<div class="container">
	<form method="post" action="<?=ADMIN_ROOT?>tags/create/">
		<section>
			<fieldset>
				<label for="tag_field_name"><?=Text::translate("Tag Name")?></label>
				<input type="text" name="tag" class="tag_field_name" id="tag_field_name">
			</fieldset>

			<?php
				$field->draw();
			?>
		</section>
		<footer>
			<input type="submit" class="button blue" value="<?=Text::translate("Create Tag")?>">
		</footer>
	</form>
</div>
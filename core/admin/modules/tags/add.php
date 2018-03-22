<div class="container">
	<form method="post" action="<?=ADMIN_ROOT?>tags/create/">
		<section>
			<fieldset>
				<label for="tag_field_name">Tag Name</label>
				<input type="text" name="tag" class="tag_field_name" id="tag_field_name">
			</fieldset>

			<?php
				// Emulate a field to let the field type drawer handle this
				$admin->drawField(array(
					"title" => "Tags to Merge In",
					"type" => "one-to-many",
					"key" => "merge_tags",
					"settings" => array(
						"table" => "bigtree_tags",
						"title_column" => "tag",
						"sort_by_column" => "tag"
					)
				));
			?>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Create Tag">
		</footer>
	</form>
</div>
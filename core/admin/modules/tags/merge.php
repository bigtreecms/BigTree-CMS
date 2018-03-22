<?php
	$tag_data = $cms->getTag($bigtree["commands"][0]);
?>
<div class="container">
	<form method="post" action="<?=ADMIN_ROOT?>tags/merge-process/" id="tag_merge_form">
		<input type="hidden" name="tag_id" value="<?=htmlspecialchars($bigtree["commands"][0])?>">
		<section>
			<fieldset>
				<label for="tag_field_name">Tag Name</label>
				<input type="text" disabled value="<?=htmlspecialchars($tag_data["tag"])?>" name="tag" class="tag_field_name" id="tag_field_name">
			</fieldset>

			<?php
				// Emulate a field to let the field type drawer handle this
				$admin->drawField(array(
					"title" => "Tag to Merge Into",
					"type" => "list",
					"key" => "merge_to",
					"settings" => array(
						"list_type" => "db",
						"pop-table" => "bigtree_tags",
						"pop-id" => "id",
						"pop-description" => "tag",
						"pop-sort" => "tag",
						"validation" => "required"
					)
				));
			?>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Merge Tag">
		</footer>
	</form>
</div>
<script>
	BigTreeFormValidator("#tag_merge_form", false);
</script>
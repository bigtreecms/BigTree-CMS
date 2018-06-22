<div id="template_type">
	<?php include BigTree::path("admin/ajax/pages/get-template-form.php"); ?>
</div>

<?php if (!$cms->getSetting("bigtree-internal-disable-page-tagging")) { ?>
<div class="tags" id="bigtree_tag_browser">
	<?php
		if ($admin->Level > 0) {
	?>
	<a href="<?=ADMIN_ROOT?>tags/" class="bigtree_tag_browser_manager">Manage All Tags</a>
	<?php
		}
	?>
	<fieldset class="tag_browser_entry">
		<label>Tags<span></span></label>
		<div class="tag_browser_input_wrapper">
			<input type="text" name="tag_entry" id="tag_entry" placeholder="Search for or add new tags..." />
			<ul id="tag_results" style="display: none;"></ul>
		</div>
		<ul id="tag_list">
			<?php
				if (is_array($page["tags"])) {
					foreach ($page["tags"] as $tag) {
			?>
			<li><input type="hidden" name="_tags[]" value="<?=$tag["id"]?>" /><a href="#"><?=$tag["tag"]?></a></li>
			<?php
					}
				}
			?>
		</ul>
	</fieldset>
</div>
<?php } ?>
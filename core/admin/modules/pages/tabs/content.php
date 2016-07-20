<?php
	namespace BigTree;
	
	/**
	 * @global array $page
	 */
?>
<div id="template_type">
	<?php include Router::getIncludePath("admin/ajax/pages/get-template-form.php") ?>
</div>

<?php if (!Setting::value("bigtree-internal-disable-page-tagging")) { ?>
<div class="tags" id="bigtree_tag_browser">
	<fieldset>
		<label><span></span><?=Text::translate("Tags")?></label>
		<ul id="tag_list">
			<?php
				if (is_array($page["tags"])) {
					foreach ($page["tags"] as $tag) {
			?>
			<li><input type="hidden" name="_tags[]" value="<?=$tag["id"]?>" /><a href="#"><?=$tag["tag"]?><span>x</span></a></li>
			<?php
					}
				}
			?>
		</ul>
		<input type="text" name="tag_entry" id="tag_entry" />
		<ul id="tag_results" style="display: none;"></ul>
	</fieldset>
</div>
<?php } ?>
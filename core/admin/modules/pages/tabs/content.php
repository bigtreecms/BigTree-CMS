<?php
	namespace BigTree;
	
	/**
	 * @global Page $page
	 */
?>
<div id="template_type">
	<?php include Router::getIncludePath("admin/ajax/pages/get-template-form.php") ?>
</div>

<?php if (!Setting::value("bigtree-internal-disable-page-tagging")) { ?>
<div class="tags" id="bigtree_tag_browser">
	<fieldset>
		<label for="tag_entry"><span></span><?=Text::translate("Tags")?></label>
		<ul id="tag_list">
			<?php
				foreach ($page->Tags as $tag) {
			?>
			<li><input type="hidden" name="_tags[]" value="<?=$tag->ID?>" /><a href="#"><?=$tag->Name?><span>x</span></a></li>
			<?php
				}
			?>
		</ul>
		<input type="text" name="tag_entry" id="tag_entry" />
		<ul id="tag_results" style="display: none;"></ul>
	</fieldset>
</div>
<?php } ?>
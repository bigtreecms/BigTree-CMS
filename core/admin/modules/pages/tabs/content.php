<div id="template_type">
	<? include BigTree::path("admin/ajax/pages/get-template-form.php") ?>
</div>

<? if (!$cms->getSetting("bigtree-internal-disable-page-tagging")) { ?>
<div class="tags" id="bigtree_tag_browser">
	<fieldset>
		<label><span></span>Tags</label>
		<ul id="tag_list">
			<?
				if (is_array($page["tags"])) {
					foreach ($page["tags"] as $tag) {
			?>
			<li><input type="hidden" name="_tags[]" value="<?=$tag["id"]?>" /><a href="#"><?=$tag["tag"]?><span>x</span></a></li>
			<?
					}
				}
			?>
		</ul>
		<input type="text" name="tag_entry" id="tag_entry" />
		<ul id="tag_results" style="display: none;"></ul>
	</fieldset>
</div>
<? } ?>
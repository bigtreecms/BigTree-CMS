<?
	$items = $admin->getBasicTemplates();
	if (isset($page["template"])) {
		if ($page["template"] && $page["template"] != "!") {
			$template_details = $cms->getTemplate($page["template"]);
			$callouts_enabled = $template_details["callouts_enabled"];
		} else {
			$callouts_enabled = false;
		}
	} else {
		$page["template"] = $items[0]["id"];
		$callouts_enabled = $items[0]["callouts_enabled"];
	}
?>
<p class="error_message" style="display: none;">Errors found! Please fix the highlighted fields before submitting.</p>
<fieldset>
	<label>Flexible Templates</label>
	<?
		$x = 0;
		$last_row = count($items) - (count($items) % 7);
		foreach ($items as $item) {
			$x++;
			
			if (!$item["image"]) {
				$image = ADMIN_ROOT."images/templates/page.png";
			} else {
				$image = ADMIN_ROOT."images/templates/".$item["image"];
			}
	?>
	<a href="#<?=$item["id"]?>" class="box_select<? if ($item["id"] == $page["template"]) { ?> active<? } ?><? if ($x > $last_row) { ?> last_row<? } ?>">
		<img src="<?=$image?>" alt="" width="32" height="32" />
		<p><?=$item["name"]?></p>
	</a>
	<?
		}
	?>
</fieldset>

<input type="hidden" name="template" id="template" value="<?=$page["template"]?>" />
<hr />
	
<fieldset>
	<label>Special Templates</label>
	<?
		$items = $admin->getRoutedTemplates();
		$last_row = count($items) - (count($items) % 7);
		foreach ($items as $item) {
			if (!$item["image"]) {
				$image = ADMIN_ROOT."images/templates/page.png";
			} else {
				$image = ADMIN_ROOT."images/templates/".$item["image"];
			}
	?>
	<a href="#<?=$item["id"]?>" class="box_select<? if ($item["id"] == $page["template"]) { ?> active<? } ?><? if ($x > $last_row) { ?> last_row<? } ?>">
		<img src="<?=$image?>" alt="" width="32" height="32" />
		<p><?=$item["name"]?></p>
	</a>
	<?
		}
	?>
</fieldset>

<hr />

<div class="left">
	<fieldset>
		<label>External Link <small>(include http://)</small></label>
		<input type="text" name="external" value="<?=$page["external"]?>" id="external_link" tabindex="1" <? if ($page["template"] == "") { ?> class="active"<? } ?>/>
	</fieldset>
	<fieldset>
		<input type="checkbox" name="redirect_lower" <? if ($page["template"] == "!") { ?>checked="checked" <? } ?>tabindex="3" /><label class="for_checkbox">Redirect Lower</label>
	</fieldset>
</div>
<div class="right">
	<fieldset>
		<label>Open In New Window?</label>
		<select name="new_window" tabindex="2">
			<option value="">No</option>
			<option value="Yes"<? if ($page["new_window"] == "Yes") { ?> selected="selected"<? } ?>>Yes</option>
		</select>
	</fieldset>
</div>
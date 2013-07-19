<?
	$items = $admin->getBasicTemplates();
	if (isset($bigtree["current_page"]["template"])) {
		if ($bigtree["current_page"]["template"] && $bigtree["current_page"]["template"] != "!") {
			$template_details = $cms->getTemplate($bigtree["current_page"]["template"]);
			$callouts_enabled = $template_details["callouts_enabled"];
		} else {
			$callouts_enabled = false;
		}
	} else {
		$bigtree["current_page"]["template"] = $items[0]["id"];
		$callouts_enabled = $items[0]["callouts_enabled"];
	}
?>
<p class="error_message" style="display: none;">Errors found! Please fix the highlighted fields before submitting.</p>
<fieldset class="last">
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
	<a href="#<?=$item["id"]?>" class="box_select<? if ($item["id"] == $bigtree["current_page"]["template"]) { ?> active<? } ?><? if ($x > $last_row) { ?> last_row<? } ?>">
		<img src="<?=$image?>" alt="" width="32" height="32" />
		<p><?=$item["name"]?></p>
	</a>
	<?
		}
	?>
</fieldset>

<input type="hidden" name="template" id="template" value="<?=$bigtree["current_page"]["template"]?>" />
<hr />
	
<fieldset class="last">
	<label>Special Templates</label>
	<?
		$x = 0;
		$items = $admin->getRoutedTemplates();
		$last_row = count($items) - (count($items) % 7);
		foreach ($items as $item) {
			$x++;
			if (!$item["image"]) {
				$image = ADMIN_ROOT."images/templates/page.png";
			} else {
				$image = ADMIN_ROOT."images/templates/".$item["image"];
			}
	?>
	<a href="#<?=$item["id"]?>" class="box_select<? if ($item["id"] == $bigtree["current_page"]["template"]) { ?> active<? } ?><? if ($x > $last_row) { ?> last_row<? } ?>">
		<img src="<?=$image?>" alt="" width="32" height="32" />
		<p><?=$item["name"]?></p>
	</a>
	<?
		}
	?>
</fieldset>

<hr />

<div class="left last">
	<fieldset>
		<label>External Link <small>(include http://)</small></label>
		<input type="text" name="external" value="<?=$bigtree["current_page"]["external"]?>" id="external_link" tabindex="1" <? if ($bigtree["current_page"]["template"] == "") { ?> class="active"<? } ?>/>
	</fieldset>
	<fieldset>
		<input type="checkbox" name="redirect_lower" <? if ($bigtree["current_page"]["template"] == "!") { ?>checked="checked" <? } ?>tabindex="3" /><label class="for_checkbox">Redirect Lower</label>
	</fieldset>
</div>
<div class="right last">
	<fieldset>
		<label>Open In New Window?</label>
		<select name="new_window" tabindex="2">
			<option value="">No</option>
			<option value="Yes"<? if ($bigtree["current_page"]["new_window"] == "Yes") { ?> selected="selected"<? } ?>>Yes</option>
		</select>
	</fieldset>
</div>
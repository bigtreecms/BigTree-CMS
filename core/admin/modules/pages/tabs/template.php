<?
	$items = $admin->getBasicTemplates();
	if ($pdata) {
		if ($pdata["template"] && $pdata["template"] != "!") {
			$td = $cms->getTemplate($pdata["template"]);
			$default_template = $td["id"];
			$callouts_enabled = $td["callouts_enabled"];
		} else {
			$default_template = $pdata["template"];
			$callouts_enabled = false;
		}
	} else {
		$default_template = $items[0]["id"];
		$callouts_enabled = $items[0]["callouts_enabled"];
	}
?>
<fieldset>
	<label>Flexible Templates</label>
	<?
		$x = 0;
		foreach ($items as $item) {
			$x++;
			
			if (!$item["image"]) {
				$image = $admin_root."images/templates/page.png";
			} else {
				$image = $admin_root."images/templates/".$item["image"];
			}
	?>
	<a href="#<?=$item["id"]?>" class="box_select<? if ($item["id"] == $default_template) { ?> active<? } ?>">
		<img src="<?=$image?>" alt="" />
		<p><?=$item["name"]?></p>
	</a>
	<?
		}
	?>
</fieldset>

<input type="hidden" name="template" id="template" value="<?=$default_template?>" />
<hr />
	
<fieldset>
	<label>Special Templates</label>
	<?
		$items = $admin->getRoutedTemplates();
		foreach ($items as $item) {
			if (!$item["image"]) {
				$image = $admin_root."images/templates/page-module.png";
			} else {
				$image = $admin_root."images/templates/".$item["image"];
			}
	?>
	<a href="#<?=$item["id"]?>" class="box_select<? if ($item["id"] == $default_template) { ?> active<? } ?>">
		<img src="<?=$image?>" alt="" />
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
		<input type="text" name="external" value="<?=$pdata["external"]?>" id="external_link" tabindex="1" <? if ($default_template == "") { ?> class="active"<? } ?>/>
	</fieldset>
	<fieldset>
		<input type="checkbox" name="redirect_lower" <? if ($default_template == "!") { ?>checked="checked" <? } ?>tabindex="3" /><label class="for_checkbox">Redirect Lower</label>
	</fieldset>
</div>
<div class="right">
	<fieldset>
		<label>Open In New Window?</label>
		<select name="new_window" tabindex="2">
			<option value="">No</option>
			<option value="Yes"<? if ($pdata["new_window"] == "Yes") { ?> selected="selected"<? } ?>>Yes</option>
		</select>
	</fieldset>
</div>
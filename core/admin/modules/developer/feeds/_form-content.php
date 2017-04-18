<?php
	namespace BigTree;
	
	/**
	 * @global Feed $feed
	 */
	
	CSRF::drawPOSTToken();
?>
<section>
	<div class="left last">
		<fieldset>
			<label for="feed_name" class="required"><?=Text::translate("Name")?></label>
			<input id="feed_name" type="text" class="required" name="name" value="<?=$feed->Name?>" />
		</fieldset>
		<fieldset>
			<label for="feed_table" class="required"><?=Text::translate("Data Table")?></label>
			<select name="table" id="feed_table" class="required left">
				<option></option>
				<?php SQL::drawTableSelectOptions($feed->Table); ?>
			</select>
		</fieldset>
		<fieldset>
			<label for="feed_type"><?=Text::translate("Type")?></label>
			<select name="type" id="feed_type" class="left">
				<?php foreach (Feed::$AvailableTypes as $t => $v) { ?>
				<option value="<?=$t?>"<?php if ($t == $feed->Type) { ?> selected="selected"<?php } ?>><?=$v?></option>
				<?php } ?>
			</select> &nbsp; <a href="#" class="options icon_settings centered"></a>
			<input type="hidden" name="options" id="feed_options" value="<?=htmlspecialchars(json_encode($feed->Settings))?>" />
		</fieldset>
	</div>
	<div class="right last">
		<fieldset>
			<label for="feed_description"><?=Text::translate("Description")?></label>
			<textarea id="feed_description" name="description"><?=$feed->Description?></textarea>
		</fieldset>
	</div>
</section>
<section class="sub" id="field_area"<?php if ($feed->Type == "rss" || $feed->Type == "rss2") { ?> style="display: none;"<?php } ?>>
	<?php
		if (!empty($feed->Table)) {
			include Router::getIncludePath("admin/ajax/developer/load-feed-fields.php");
		} else {
			echo "<p>".Text::translate("Please choose a table to populate this area.")."</p>";
		}
	?>
</section>
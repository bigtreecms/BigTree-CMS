<?php
	namespace BigTree;

	/**
	 * @global array $options
	 * @global string $table
	 */
?>
<fieldset>
	<label for="options_field_title"><?=Text::translate("Feed Title")?></label>
	<input id="options_field_title" type="text" name="feed_title" value="<?=htmlspecialchars($options["feed_title"])?>" />
</fieldset>
<fieldset>
	<label for="options_field_link"><?=Text::translate("Original Content Link <small>(i.e. link back to news page)</small>")?></label>
	<input id="options_field_link" type="text" name="feed_link" value="<?=htmlspecialchars($options["feed_link"])?>" />
</fieldset>
<fieldset>
	<label for="options_field_limit"><?=Text::translate("Limit <small>(defaults to 15)</small>")?></label>
	<input id="options_field_limit" type="text" name="limit" value="<?=$options["limit"]?>" />
</fieldset>

<h4><?=Text::translate("Field Settings")?></h4>
<?php if (!$table) { ?>
<p><?=Text::translate("Please select a table first.")?></p>
<?php } else { ?>
<fieldset>
	<label for="options_field_title_field"><?=Text::translate("Title Field")?></label>
	<select id="options_field_title_field" name="title">
		<?php SQL::drawColumnSelectOptions($table, $options["title"]); ?>
	</select>
</fieldset>
<fieldset>
	<label for="options_field_description_field"><?=Text::translate("Description Field")?></label>
	<select id="options_field_description_field" name="description">
		<?php SQL::drawColumnSelectOptions($table, $options["description"]); ?>
	</select>
</fieldset>
<fieldset>
	<label for="options_field_content_limit"><?=Text::translate("Description Content Limit <small>(default is 500 characters)</small>")?></label>
	<input id="options_field_content_limit" type="text" name="content_limit" value="<?=htmlspecialchars($options["content_limit"])?>" />
</fieldset>
<fieldset>
	<label for="options_field_link_field"><?=Text::translate("Link Field")?></label>
	<select id="options_field_link_field" name="link">
		<?php SQL::drawColumnSelectOptions($table, $options["link"]); ?>
	</select>
</fieldset>
<fieldset>
	<label for="options_field_generator"><?=Text::translate("Link Generator <small>(use field names wrapped in {} to have dynamic links)</small>")?></label>
	<input id="options_field_generator" type="text" name="link_gen" value="<?=htmlspecialchars($options["link_gen"])?>" />
</fieldset>
<fieldset>
	<label for="options_field_date_field"><?=Text::translate("Date Field")?></label>
	<select id="options_field_date_field" name="date">
		<?php SQL::drawColumnSelectOptions($table, $options["date"]); ?>
	</select>
</fieldset>
<fieldset>
	<label for="options_field_creator_field"><?=Text::translate("Creator Field")?></label>
	<select id="options_field_creator_field" name="creator">
		<?php SQL::drawColumnSelectOptions($table, $options["creator"]); ?>
	</select>
</fieldset>
<fieldset>
	<label for="options_field_orderby"><?=Text::translate("Order By")?></label>
	<select id="options_field_orderby" name="sort">
		<?php SQL::drawColumnSelectOptions($table, $options["sort"], true); ?>
	</select>
</fieldset>
<?php } ?>
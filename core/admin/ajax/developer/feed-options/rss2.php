<?php
	namespace BigTree;
?>
<fieldset>
	<label><?=Text::translate("Feed Title")?></label>
	<input type="text" name="feed_title" value="<?=htmlspecialchars($data["feed_title"])?>" />
</fieldset>
<fieldset>
	<label><?=Text::translate("Original Content Link <small>(i.e. link back to news page)</small>")?></label>
	<input type="text" name="feed_link" value="<?=htmlspecialchars($data["feed_link"])?>" />
</fieldset>
<fieldset>
	<label><?=Text::translate("Limit <small>(defaults to 15)</small>")?></label>
	<input type="text" name="limit" value="<?=$data["limit"]?>" />
</fieldset>

<h4><?=Text::translate("Field Settings")?></h4>
<?php if (!$table) { ?>
<p><?=Text::translate("Please select a table first.")?></p>
<?php } else { ?>
<fieldset>
	<label><?=Text::translate("Title Field")?></label>
	<select name="title">
		<?php SQL::drawColumnSelectOptions($table,$data["title"]); ?>
	</select>
</fieldset>
<fieldset>
	<label><?=Text::translate("Description Field")?></label>
	<select name="description">
		<?php SQL::drawColumnSelectOptions($table,$data["description"]); ?>
	</select>
</fieldset>
<fieldset>
	<label><?=Text::translate("Description Content Limit <small>(default is 500 characters)</small>")?></label>
	<input type="text" name="content_limit" value="<?=htmlspecialchars($data["content_limit"])?>" />
</fieldset>
<fieldset>
	<label><?=Text::translate("Link Field")?></label>
	<select name="link">
		<?php SQL::drawColumnSelectOptions($table,$data["link"]); ?>
	</select>
</fieldset>
<fieldset>
	<label><?=Text::translate("Link Generator <small>(use field names wrapped in {} to have dynamic links)</small>")?></label>
	<input type="text" name="link_gen" value="<?=htmlspecialchars($data["link_gen"])?>" />
</fieldset>
<fieldset>
	<label><?=Text::translate("Date Field")?></label>
	<select name="date">
		<?php SQL::drawColumnSelectOptions($table,$data["date"]); ?>
	</select>
</fieldset>
<fieldset>
	<label><?=Text::translate("Creator Field")?></label>
	<select name="creator">
		<?php SQL::drawColumnSelectOptions($table,$data["creator"]); ?>
	</select>
</fieldset>
<fieldset>
	<label><?=Text::translate("Order By")?></label>
	<select name="sort">
		<?php SQL::drawColumnSelectOptions($table,$data["sort"],true); ?>
	</select>
</fieldset>
<?php } ?>
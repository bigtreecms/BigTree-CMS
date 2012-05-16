<fieldset>
	<label>Feed Title</label>
	<input type="text" name="feed_title" value="<?=htmlspecialchars($data["feed_title"])?>" />
</fieldset>
<fieldset>
	<label>Original Content Link <small>(i.e. link back to news page)</small></label>
	<input type="text" name="feed_link" value="<?=htmlspecialchars($data["feed_link"])?>" />
</fieldset>
<fieldset>
	<label>Limit <small>(defaults to 15)</small></label>
	<input type="text" name="limit" value="<?=$data["limit"]?>" />
</fieldset>
<h4>Field Settings</h4>
<? if (!$table) { ?>
<p>Please select a table first.</p>
<? } else { ?>
<fieldset>
	<label>Title Field</label>
	<select name="title">
		<? BigTree::getFieldSelectOptions($table,$data["title"]); ?>
	</select>
</fieldset>
<fieldset>
	<label>Description Field</label>
	<select name="description">
		<? BigTree::getFieldSelectOptions($table,$data["description"]); ?>
	</select>
</fieldset>
<fieldset>
	<label>Description Content Limit <small>(default is 500 characters)</small></label>
	<input type="text" name="content_limit" value="<?=htmlspecialchars($data["content_limit"])?>" />
</fieldset>
<fieldset>
	<label>Link Field</label>
	<select name="link">
		<? BigTree::getFieldSelectOptions($table,$data["link"]); ?>
	</select>
</fieldset>
<fieldset>
	<label>Link Generator <small>(use field names wrapped in {} to have dynamic links)</small></label>
	<input type="text" name="link_gen" value="<?=htmlspecialchars($data["link_gen"])?>" />
</fieldset>
<fieldset>
	<label>Order By</label>
	<select name="sort">
		<? BigTree::getFieldSelectOptions($table,$data["sort"],true); ?>
	</select>
</fieldset>
<? } ?>
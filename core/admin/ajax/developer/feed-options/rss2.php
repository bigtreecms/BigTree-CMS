<fieldset>
	<label>Feed Title</label>
	<input type="text" name="feed_title" value="<?=htmlspecialchars($d["feed_title"])?>" />
</fieldset>
<fieldset>
	<label>Original Content Link <small>(i.e. link back to news page)</small></label>
	<input type="text" name="feed_link" value="<?=htmlspecialchars($d["feed_link"])?>" />
</fieldset>
<fieldset>
	<label>Limit <small>(defaults to 15)</small></label>
	<input type="text" name="limit" value="<?=$d["limit"]?>" />
</fieldset>
<h4>Field Settings</h4>
<? if (!$table) { ?>
<p>Please select a table first.</p>
<? } else { ?>
<fieldset>
	<label>Title Field</label>
	<select name="title">
		<? BigTree::getFieldSelectOptions($table,$d["title"]); ?>
	</select>
</fieldset>
<fieldset>
	<label>Description Field</label>
	<select name="description">
		<? BigTree::getFieldSelectOptions($table,$d["description"]); ?>
	</select>
</fieldset>
<fieldset>
	<label>Description Content Limit <small>(default is 500 characters)</small></label>
	<input type="text" name="content_limit" value="<?=htmlspecialchars($d["content_limit"])?>" />
</fieldset>
<fieldset>
	<label>Link Field</label>
	<select name="link">
		<? BigTree::getFieldSelectOptions($table,$d["link"]); ?>
	</select>
</fieldset>
<fieldset>
	<label>Link Generator <small>(use field names wrapped in {} to have dynamic links)</small></label>
	<input type="text" name="link_gen" value="<?=htmlspecialchars($d["link_gen"])?>" />
</fieldset>
<fieldset>
	<label>Date Field</label>
	<select name="date">
		<? BigTree::getFieldSelectOptions($table,$d["date"]); ?>
	</select>
</fieldset>
<fieldset>
	<label>Creator Field</label>
	<select name="creator">
		<? BigTree::getFieldSelectOptions($table,$d["creator"]); ?>
	</select>
</fieldset>
<fieldset>
	<label>Order By</label>
	<select name="sort">
		<? BigTree::getFieldSelectOptions($table,$d["sort"],true); ?>
	</select>
</fieldset>
<? } ?>
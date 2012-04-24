<section>
	<fieldset>
		<label class="required">Name</label>
		<input type="text" name="name" class="required" value="<?=$item["name"]?>" />
	</fieldset>
	<fieldset>
		<label>Route</label>
		<input type="text" name="route" value="<?=$item["route"]?>" />
	</fieldset>
	<fieldset>
		<label>Access Level</label>
		<select name="level">
			<option value="0">Normal User</option>
			<option value="1"<? if ($item["level"] == 1) { ?> selected="selected"<? } ?>>Administrator</option>
			<option value="2"<? if ($item["level"] == 2) { ?> selected="selected"<? } ?>>Developer</option>
		</select>
	</fieldset>
	<fieldset>
		<label class="required">Image</label>
		<input type="hidden" name="class" id="selected_icon" value="<?=$item["class"]?>" />
		<ul class="developer_icon_list">
			<? foreach ($classes as $class) { ?>
			<li>
				<a href="#<?=$class?>"<? if ($class == $item["class"]) { ?> class="active"<? } ?>><span class="icon_small icon_small_<?=$class?>"></span></a>
			</li>
			<? } ?>
		</ul>
	</fieldset>
	<fieldset>
		<label>In Navigation</label>
		<input type="checkbox" name="in_nav" <? if ($item["in_nav"]) { ?>checked="checked" <? } ?>/>
	</fieldset>
</section>
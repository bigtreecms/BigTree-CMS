<?
	$types = array(
		"text" => "Text",
		"textarea" => "Text Area",
		"html" => "HTML",
		"checkbox" => "Checkbox",
		"date" => "Date Picker",
		"time" => "Time Picker",
	);
?>
<h4>Fields <a href="#" class="add_option"><img src="<?=$admin_root?>images/add.png" alt="" /></a></h4>
<fieldset>
	<div class="list_attr list_attr_triple" id="array_option_list">
		<ul>
			<li>Array Key</li>
			<li>Title</li>
			<li>Type</li>
		</ul>
		<?
			// If we have fields already, show them.
			if (!empty($d["fields"])) {
				$x = 0;
				foreach ($d["fields"] as $option) {
		?>
		<ul>
			<li>
				<input type="text" name="fields[<?=$x?>][key]" value="<?=htmlspecialchars($option["key"])?>" />
			</li>
			<li>
				<input type="text" name="fields[<?=$x?>][title]" value="<?=htmlspecialchars($option["title"])?>" />
			</li>
			<li>
				<select name="fields[<?=$x?>][type]">
					<? foreach ($types as $type => $desc) { ?>
					<option value="<?=$type?>"<? if ($type == $option["type"]) { ?> selected="selected"<? } ?>><?=$desc?></option>
					<? } ?>
				</select>
			</li>
			<li class="del"><a href="#"><img src="<?=$admin_root?>images/currently-kill.png" alt="" /></a></li>
		</ul>
		<?
					$x++;
				}
			
			// Otherwise, let's draw a single entry so they don't need to click the + already.
			} else {
		?>
		<ul>
			<li>
				<input type="text" name="fields[<?=$x?>][key]" value="" />
			</li>
			<li>
				<input type="text" name="fields[<?=$x?>][title]" value="" />
			</li>
			<li>
				<select name="fields[<?=$x?>][type]">
					<? foreach ($types as $type => $desc) { ?>
					<option value="<?=$type?>"><?=$desc?></option>
					<? } ?>
				</select>
			</li>
			<li class="del"><a href="#"><img src="<?=$admin_root?>images/currently-kill.png" alt="" /></a></li>
		</ul>
		<?
			}
		?>
	</div>
</fieldset>

<script type="text/javascript">
	var option_count = <?=$x?>;
	
	$("#array_option_list").on("click",".del a",function() {
		$(this).parents("ul").remove();
		return false;
	});
	
	$(".add_option").click(_local_addOptionClick);
	
	function _local_addOptionClick() {
		option_count++;

		ul = $('<ul>');
		
		li_description = $('<li>');
		li_description.html('<input type="text" name="fields[' + option_count + '][key]" value="" />');
		ul.append(li_description);
		
		li_value = $('<li>');
		li_value.html('<input type="text" name="fields[' + option_count + '][title]" value="" />');
		ul.append(li_value);
		
		li_type = $('<li>');
		li_type.html('<select name="fields[' + option_count + '][type]"><? foreach ($types as $type => $desc) { ?><option value="<?=$type?>"><?=$desc?></option><? } ?></select>');
		ul.append(li_type);
		
		li_del = $('<li class="del">');
		li_del.html('<a href="#"><img src="<?=$admin_root?>images/currently-kill.png" alt="" /></a>');
		li_del.find("a").click(function() {
			$(this).parents("ul").remove();
			return false;
		});		
		ul.append(li_del);
		
		$("#array_option_list").append(ul);
		
		return false;
	}
</script>
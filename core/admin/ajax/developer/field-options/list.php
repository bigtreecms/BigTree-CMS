<?
	$types = array(
		"static" => "Static",
		"db" => "Database Populated",
		"state" => "State List",
		"country" => "Country List"
	);
?>
<fieldset>
	<label>List Type</label>
	<select name="list_type" id="field_list_types">
		<? foreach ($types as $val => $desc) { ?>
		<option value="<?=$val?>"<? if ($val == $d["list_type"]) { ?> selected="selected"<? } ?>><?=$desc?></option>
		<? } ?>
	</select>
</fieldset>
<fieldset>
	<label>Allow Empty <small>(first option is blank)</small></label>
	<select name="allow-empty">
		<option value="Yes">Yes</option>
		<option value="No"<? if ($d["allow-empty"] == "No") { ?> selected="selected"<? } ?>>No</option>
	</select>
</fieldset>

<div class="list_type_options" id="static_list_options"<? if ($d["list_type"] && $d["list_type"] != "static") { ?> style="display: none;"<? } ?>>
	<h4>Static List Options <a href="#" class="add_option"><img src="<?=$admin_root?>images/add.png" alt="" /></a></h4>
	<fieldset>
		<div class="list_attr" id="pop_option_list">
			<ul>
				<li>Value:</li><li>Description:</li>
			</ul>
			<?
				$x = 0;
				if (!empty($d["list"])) {
					foreach ($d["list"] as $option) {
			?>
			<ul>
				<li>
					<input type="text" name="list[<?=$x?>][value]" value="<?=htmlspecialchars($option["value"])?>" />
				</li>
				<li>
					<input type="text" name="list[<?=$x?>][description]" value="<?=htmlspecialchars($option["description"])?>" />
				</li>
				<li class="del"><a href="#"><img src="<?=$admin_root?>images/currently-kill.png" alt="" /></a></li>
			</ul>
			<?
						$x++;
					}
				}
			?>
		</div>
	</fieldset>
</div>

<div class="list_type_options" id="db_list_options"<? if ($d["list_type"] != "db") { ?> style="display: none;"<? } ?>>
	<h4>Database Populated List Options</h4>
	<fieldset>
		<label>Table</label>
		<select name="pop-table" class="table_select">
			<option></option>
			<? BigTree::getTableSelectOptions($d["pop-table"]); ?>
		</select>
	</fieldset>
	
	<fieldset>
		<label>Description Field</label>
		<div name="pop-description" class="pop-dependant pop-table">
			<? if ($d["pop-table"]) { ?>
			<select name="pop-description"><? BigTree::getFieldSelectOptions($d["pop-table"],$d["pop-description"]) ?></select>
			<? } else { ?>
			<small>-- Please select a table --</small>
			<? } ?>
		</div>
	</fieldset>
	
	<fieldset>
		<label>Sort By</label>
		<div name="pop-sort" class="sort_by pop-dependant pop-table">
			<? if ($d["pop-table"]) { ?>
			<select name="pop-sort"><? BigTree::getFieldSelectOptions($d["pop-table"],$d["pop-sort"],true) ?></select>
			<? } else { ?>
			<small>-- Please select a table --</small>
			<? } ?>
		</div>	
	</fieldset>
</div>

<script type="text/javascript">
	var option_count = <?=$x?>;
	
	$("#field_list_types").change(function() {
		$(".list_type_options").hide();
		$("#" + $(this).val() + "_list_options").show();
	});
	
	$(".list_attr .del a").click(function() {
		$(this).parents("ul").remove();
		return false;
	});
	
	$(".add_option").click(_local_addOptionClick);
	
	$(".list_attr input").on("keydown",function(e) {
		if (e.keyCode == 13) {
			_local_addOptionClick();
			return false;
		}
	});
	
	function _local_addOptionClick() {
		option_count++;

		ul = $('<ul>');
		
		li_value = $('<li>');
		li_value.html('<input type="text" name="list[' + option_count + '][value]" value="" />');
		ul.append(li_value);
		
		li_description = $('<li>');
		li_description.html('<input type="text" name="list[' + option_count + '][description]" value="" />');
		ul.append(li_description);
		
		li_del = new $('<li class="del">');
		li_del.html('<a href="#"><img src="<?=$admin_root?>images/currently-kill.png" alt="" /></a>');
		li_del.find("a").click(function() {
			$(this).parents("ul").remove();
			return false;
		});		
		ul.append(li_del);
		
		$("#pop_option_list").append(ul);
		
		return false;
	};
</script>
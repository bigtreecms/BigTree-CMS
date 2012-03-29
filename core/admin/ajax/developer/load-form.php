<?
	$reserved = $admin->ReservedColumns;
	
	$used = array();
	$unused = array();
	$positioned = false;
	
	$table = isset($_POST["table"]) ? $_POST["table"] : $table;

	if (isset($fields)) {
		foreach ($fields as $key => $field) {
			$used[] = $key;
		}
		// Figure out the fields we're not using so we can offer them back.
		$q = sqlquery("DESCRIBE $table");
		while ($f = sqlfetch($q)) {
			if (!in_array($f["Field"],$reserved) && !in_array($f["Field"],$used)) {
				$unused[$f["Field"]] = ucwords(str_replace("_"," ",$f["Field"]));
			}
			if ($f["Field"] == "position") {
				$positioned = true;
			}
		}
	} else {
		$fields = array();
		$q = sqlquery("DESCRIBE $table");
		while ($f = sqlfetch($q)) {
			if (!in_array($f["Field"],$reserved)) {
				// Do a ton of guessing here to try to save time.
				$subtitle = "";
				$type = "text";
				$title = ucwords(str_replace(array("-","_")," ",$f["Field"]));
				$title = str_replace(array("Url","Pdf","Sql"),array("URL","PDF","SQL"),$title);
				
				if (strpos($title,"URL") !== false) {
					$subtitle = "Include http://";
				}
				
				if (strpos($title,"Date") !== false) {
					$type = "date";
				}
				
				if (strpos($title,"File") !== false || strpos($title,"PDF") !== false) {
					$type = "upload";
				}
				
				if (strpos($title,"Image") !== false) {
					$type = "image";
				}
				
				if (strpos($title,"Featured") !== false) {
					$type = "checkbox";
				}
				
				if (strpos($title,"Description") !== false) {
					$type = "html";
				}

				$fields[$f["Field"]] = array("title" => $title, "subtitle" => $subtitle, "type" => $type);
			}
			
			if ($f["Field"] == "position") {
				$positioned = true;
			}
		}
	}
	
	$cached_types = $admin->getCachedFieldTypes();
	$types = $cached_types["module"];
?>



<label>Fields</label>

<div class="form_table">
	<header>
		<a href="#" class="add add_geocoding">Geocoding</a>
		<a href="#" class="add add_many_to_many">Many-To-Many</a>
		<a href="#" class="add add_unused">Add</a>
		<select id="unused_field" class="custom_control">
			<? foreach ($unused as $key => $val) { ?>
			<option value="<?=htmlspecialchars($val)?>"><?=htmlspecialchars($key)?></option>
			<? } ?>
		</select>
	</header>
	<div class="labels">
		<span class="developer_resource_form_title">Title</span>
		<span class="developer_resource_form_subtitle">Subtitle</span>
		<span class="developer_resource_type">Type</span>
		<span class="developer_resource_action">Edit</span>
		<span class="developer_resource_action">Delete</span>
	</div>
	<ul id="resource_table">
		<?
			$mtm_count = 0;
			foreach ($fields as $key => $field) {
				$used[] = $key;
		?>
		<li id="row_<?=$key?>">
			<section class="developer_resource_form_title">
				<span class="icon_sort"></span>
				<input type="text" name="titles[<?=$key?>]" <? if ($field["type"] == "geocoding") { ?>disabled="disabled" value="Geocoding"<? } else { ?>value="<?=$field["title"]?>"<? } ?> />
			</section>
			<section class="developer_resource_form_subtitle">
				<input type="text" name="subtitles[<?=$key?>]" <? if ($field["type"] == "geocoding") { ?>disabled="disabled" value="Geocoding"<? } else { ?>value="<?=$field["subtitle"]?>"<? } ?> />
			</section>
			<section class="developer_resource_type">
				<?
					if ($field["type"] == "geocoding") {
				?>
				<input type="hidden" name="type[geocoding]" value="geocoding" id="type_geocoding" />
				<?
					} elseif ($field["type"] == "many_to_many") {
						$mtm_count++;
				?>
				<span class="resource_name">Many to Many</span>
				<input type="hidden" name="type[mtm_<?=$mtm_count?>]" value="many_to_many" id="type_mtm_<?=$mtm_count?>" />
				<?
					} else {
				?>
				<select name="type[<?=$key?>]" id="type_<?=$key?>">
					<? foreach ($types as $k => $v) { ?>
					<option value="<?=$k?>"<? if ($k == $field["type"]) { ?> selected="selected"<? } ?>><?=htmlspecialchars($v)?></option>
					<? } ?>
				</select>
				<?
					}
				?>
			</section>
			<section class="developer_resource_action">
				<a href="#" class="options icon_edit" name="<?=$key?>"></a>
				<input type="hidden" name="options[<?=$key?>]" value="<?=htmlspecialchars(json_encode($field))?>" id="options_<?=$key?>" />
			</section>
			<section class="developer_resource_action">
				<a href="#" class="icon_delete" name="<?=$key?>"></a>
			</section>
		</li>
		<?
			}
		?>
	</ul>
</div>

<? if ($positioned) { ?>
<fieldset>
	<label>Default Position <small>For New Entries</small></label>
	<select name="default_position">
		<option>Bottom</option>
		<option<? if ($form["default_position"] == "Top") { ?> selected="selected"<? } ?>>Top</option>
	</select>
</fieldset>
<? } ?>

<script type="text/javascript">
	var current_editing_key;
	var mtm_count = <?=$mtm_count?>;
	
	$(".icon_edit").live("click",function() {
		key = $(this).attr("name");
		current_editing_key = key;
		
		$.ajax("<?=$admin_root?>ajax/developer/load-field-options/", { type: "POST", data: { type: $("#type_" + key).val(), data: $("#options_" + key).val() }, complete: function(response) {
			new BigTreeDialog("Field Options",response.responseText,function(data) {
				$.ajax("<?=$admin_root?>ajax/developer/save-field-options/?key=" + current_editing_key, { type: "POST", data: data });
			});
		}});
		
		return false;
	});
		
	$(".icon_delete").live("click",function() {
		new BigTreeDialog("Delete Resource",'<p class="confirm">Are you sure you want to delete this field?</p>',$.proxy(function() {
			li = $(this).parents("li");
			title = li.find("input").val();
			if (title) {
				key = $(this).attr("name");
				if (key != "geocoding") {
					sel = $("#unused_field").get(0);
					sel.options[sel.options.length] = new Option(key,title,false,false);
				}
			}
			li.remove();
		},this),"delete",false,"OK");
		
		return false;
	});
	
	$(".add_unused").click(function() {
		un = $("#unused_field").get(0);
		key = un.options[un.selectedIndex].text;
		title = un.options[un.selectedIndex].value;
		un.remove(un.selectedIndex);
		
		li = $('<li id="row_' + key + '">');
		li.html('<section class="developer_resource_form_title"><span class="icon_sort"></span><input type="text" name="titles[' + key + ']" value="' + title + '" /></section><section class="developer_resource_form_subtitle"><input type="text" name="subtitles[' + key + ']" value="" /></section><section class="developer_resource_type"><select name="type[' + key + ']" id="type_' + key + '"><? foreach ($types as $k => $v) { ?><option value="<?=$k?>"><?=htmlspecialchars($v)?></option><? } ?></select></section><section class="developer_resource_action"><a href="#" class="options icon_edit" name="' + key + '"></a><input type="hidden" name="options[' + key + ']" value="" id="options_' + key + '" /></section><section class="developer_resource_action"><a href="#" class="icon_delete" name="' + key + '"></a></section>');
		
		$("#resource_table").append(li);
		_local_hooks();
		
		return false;
	});
	
	$(".add_geocoding").click(function() {
		li = $('<li id="row_geocoding">');
		li.html('<section class="developer_resource_form_title"><span class="icon_sort"></span><input type="text" name="titles[geocoding]" value="Geocoding" disabled="disabled" /></section><section class="developer_resource_form_subtitle"><input type="hidden" name="subtitles[geocoding]" value="" />&nbsp;</section><section class="developer_resource_type"><input name="type[geocoding]" id="type_geocoding" type="hidden" />&nbsp;</section><section class="developer_resource_action"><a href="#" class="options icon_edit" name="geocoding"></a><input type="hidden" name="options[geocoding]" value="" id="options_geocoding" /></section><section class="developer_resource_action"><a href="#" class="icon_delete" name="geocoding"></a></section>');
		
		$("#resource_table").append(li);
		_local_hooks();
		
		return false;
	});
	
	$(".add_many_to_many").click(function() {
		mtm_count++;
			
		li = $('<li id="mtm_row_' + mtm_count + '">');
		li.html('<section class="developer_resource_form_title"><span class="icon_sort"></span><input type="text" name="titles[mtm_' + mtm_count + ']" value="" /></section><section class="developer_resource_form_subtitle"><input type="text" name="subtitles[mtm_' + mtm_count + ']" value="" /></section><section class="developer_resource_type"><input name="type[mtm_' + mtm_count + ']" id="type_mtm_' + mtm_count + '" type="hidden" value="many_to_many" /><p>Many To Many</p></section><section class="developer_resource_action"><a href="#" class="options icon_edit" name="mtm_' + mtm_count + '"></a><input type="hidden" name="options[mtm_' + mtm_count + ']" value="" id="options_mtm_' + mtm_count + '" /></section><section class="developer_resource_action"><a href="#" class="icon_delete" name="mtm_' + mtm_count + '"></a></section>');
		
		$("#resource_table").append(li);
		_local_hooks();
		
		return false;
	});
	
	function _local_hooks() {
		$("#resource_table").sortable({ axis: "y", containment: "parent", handle: ".icon_sort", items: "li", placeholder: "ui-sortable-placeholder", tolerance: "pointer" });
		BigTreeCustomControls();
	}
	
	_local_hooks();
</script>
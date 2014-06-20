<?
	$reserved = $admin->ReservedColumns;
	
	$used = array();
	$unused = array();
	$positioned = false;
	
	$table = isset($_POST["table"]) ? $_POST["table"] : $table;
	$table_columns = array();

	if (isset($fields)) {
		foreach ($fields as $key => $field) {
			$used[] = $key;
		}
		// Figure out the fields we're not using so we can offer them back.
		$table_description = BigTree::describeTable($table);
		foreach ($table_description["columns"] as $column => $details) {
			if (!in_array($column,$reserved) && !in_array($column,$used)) {
				$unused[] = array("field" => $column, "title" => str_replace(array("Url","Pdf","Sql"),array("URL","PDF","SQL"),ucwords(str_replace(array("-","_")," ",$details["name"]))));
			}
			if ($column == "position") {
				$positioned = true;
			}
			$table_columns[] = $column;
		}
	} else {
		$fields = array();
		// To tolerate someone selecting the blank spot in the table dropdown again when creating a form.
		if ($table) {
			$table_info = BigTree::describeTable($table);
		} else {
			$table_info = array("foreign_keys" => array(), "columns" => array());
		}

		// Let's relate the foreign keys based on the local column so we can check easier.
		$foreign_keys = array();
		foreach ($table_info["foreign_keys"] as $key) {
			if (count($key["local_columns"]) == 1) {
				$foreign_keys[$key["local_columns"][0]] = $key;
			}
		}
		foreach ($table_info["columns"] as $column) {
			$table_columns[] = $column["name"];
			if (!in_array($column["name"],$reserved)) {
				// Do a ton of guessing here to try to save time.
				$subtitle = "";
				$type = "text";
				$title = str_replace(array("Url","Pdf","Sql"),array("URL","PDF","SQL"),ucwords(str_replace(array("-","_")," ",$column["name"])));
				$options = array();
				
				if (strpos($title,"URL") !== false) {
					$subtitle = "(include http://)";
				}

				if ($column["name"] == "route") {
					$type = "route";
				}
				
				if (strpos($title,"File") !== false || strpos($title,"PDF") !== false) {
					$type = "upload";
				}
				
				if (strpos($title,"Image") !== false) {
					$type = "upload";
					$options["image"] = "on";
				}
				
				if (strpos($title,"Description") !== false) {
					$type = "html";
				}
				
				if ($column["name"] == "featured") {
					$type = "checkbox";
				}
				
				if ($column["type"] == "date") {
					$type = "date";
				}
				
				if ($column["type"] == "time") {
					$type = "time";
				}
				
				if ($column["type"] == "datetime") {
					$type = "datetime";
				}
				
				if ($column["type"] == "enum") {
					$type = "list";
					$list = array();
					foreach ($column["options"] as $option) {
						$list[] = array("value" => $option, "description" => $option);
					}
					$options = array(
						"list_type" => "static",
						"list" => $list
					);
					if ($column["allow_null"]) {
						$options["allow-empty"] = "Yes";
					} else {
						$options["allow-empty"] = "No";
					}
				}
				
				// Database populated list for foreign keys.
				if (substr($column["type"],-3,3) == "int" && isset($foreign_keys[$column["name"]]) && implode("",$foreign_keys[$column["name"]]["other_columns"]) == "id") {
					$type = "list";
					// Describe this other table
					$other_table = BigTree::describeTable($foreign_keys[$column["name"]]["other_table"]);
					$ot_columns = $other_table["columns"];
					$desc_column = "";
					// Find the first short title-esque column and use it as the populated list descriptor
					while (!$desc_column && next($ot_columns)) {
						$col = current($ot_columns);
						if (($col["type"] == "varchar" || $col["type"] == "char") && $col["size"] > 2) {
							$desc_column = $col;
						}
					}
					$options = array("list_type" => "db", "pop-table" => $foreign_keys[$column["name"]]["other_table"]);
					if ($desc_column) {
						$options["pop-description"] = $desc_column["name"];
						$options["pop-sort"] = $desc_column["name"]." ASC";
					}
					if ($column["allow_null"]) {
						$options["allow-empty"] = "Yes";
					} else {
						$options["allow-empty"] = "No";
					}
				}

				$fields[$column["name"]] = array_merge(array("title" => $title, "subtitle" => $subtitle, "type" => $type),$options);
			}
			
			if ($f["Field"] == "position") {
				$positioned = true;
			}
		}
	}

	$cached_types = $admin->getCachedFieldTypes();
	$types = $cached_types["modules"];
	if (count($fields)) {
?>
<label>Fields</label>

<div class="form_table<? if (!$positioned) { ?> last<? } ?>">
	<header>
		<a href="#" class="add add_geocoding"><span></span>Geocoding</a>
		<a href="#" class="add add_many_to_many"><span></span>Many-To-Many</a>
	</header>
	<div class="labels">
		<span class="developer_resource_form_title">Title</span>
		<span class="developer_resource_form_subtitle">Subtitle</span>
		<span class="developer_resource_type">Type</span>
		<span class="developer_resource_action">Delete</span>
	</div>
	<ul id="resource_table">
		<?
			$mtm_count = 0;

			foreach ($fields as $key => $field) {
				if ($field["type"] == "many-to-many") {
					$mtm_count++;
					$key = "__mtm-".$mtm_count."__";
				}

				// If this column is no longer in the table, we're going to remove it.
				if (in_array($key,$table_columns) || $field["type"] == "geocoding" || $field["type"] == "many-to-many") {
					$used[] = $key;
		?>
		<li id="row_<?=$key?>">
			<section class="developer_resource_form_title">
				<span class="icon_sort"></span>
				<input type="text" name="titles[<?=$key?>]" <? if ($field["type"] == "geocoding") { ?>disabled="disabled" value="Geocoding"<? } else { ?>value="<?=$field["title"]?>"<? } ?> />
			</section>
			<section class="developer_resource_form_subtitle">
				<input type="text" name="subtitles[<?=$key?>]" <? if ($field["type"] == "geocoding") { ?>disabled="disabled" value=""<? } else { ?>value="<?=$field["subtitle"]?>"<? } ?> />
			</section>
			<section class="developer_resource_type">
				<?
					if ($field["type"] == "geocoding") {
				?>
				<input type="hidden" name="type[geocoding]" value="geocoding" id="type_geocoding" />
				<span class="resource_name">Geocoding</span>
				<?
					} elseif ($field["type"] == "many-to-many") {
				?>
				<span class="resource_name">Many to Many</span>
				<input type="hidden" name="type[<?=$key?>]" value="many-to-many" id="type_<?=$key?>" />
				<?
					} else {
				?>
				<select name="type[<?=$key?>]" id="type_<?=$key?>">
					<? foreach ($types as $k => $v) { ?>
					<option value="<?=$k?>"<? if ($k == $field["type"]) { ?> selected="selected"<? } ?>><?=$v["name"]?></option>
					<? } ?>
				</select>
				<?
					}
				?>
				<a href="#" class="options icon_settings" name="<?=$key?>"></a>
				<input type="hidden" name="options[<?=$key?>]" value="<?=htmlspecialchars(json_encode($field))?>" id="options_<?=$key?>" />
			</section>
			<section class="developer_resource_action">
				<a href="#" class="icon_delete" name="<?=$key?>"></a>
			</section>
		</li>
		<?
				}
			}
		?>
	</ul>
</div>

<? if ($positioned) { ?>
<fieldset class="last">
	<label>Default Position <small>For New Entries</small></label>
	<select name="default_position">
		<option>Bottom</option>
		<option<? if ($form["default_position"] == "Top") { ?> selected="selected"<? } ?>>Top</option>
	</select>
</fieldset>
<? } ?>

<script>
	BigTree.localMTMCount = <?=$mtm_count?>;
	
	fieldSelect = new BigTreeFieldSelect(".form_table header",<?=json_encode($unused)?>,function(el,fs) {
		title = el.title;
		key = el.field;
		
		li = $('<li id="row_' + key + '">');
		li.html('<section class="developer_resource_form_title"><span class="icon_sort"></span><input type="text" name="titles[' + key + ']" value="' + title + '" /></section><section class="developer_resource_form_subtitle"><input type="text" name="subtitles[' + key + ']" value="" /></section><section class="developer_resource_type"><select name="type[' + key + ']" id="type_' + key + '"><? foreach ($types as $k => $v) { ?><option value="<?=$k?>"><?=$v["name"]?></option><? } ?></select><a href="#" class="options icon_settings" name="' + key + '"></a><input type="hidden" name="options[' + key + ']" value="" id="options_' + key + '" /></section><section class="developer_resource_action"><a href="#" class="icon_delete" name="' + key + '"></a></section>');
		
		$("#resource_table").append(li);
		fs.removeCurrent();
		BigTree.localHooks();
	});
</script>
<?
	} else {
?>
<p>Please choose a table to populate this area.</p>
<?
	}
?>
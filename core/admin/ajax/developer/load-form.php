<?
	$reserved = BigTreeAdmin::$ReservedColumns;
	
	$used = array();
	$unused = array();
	$positioned = false;
	
	$table = isset($_POST["table"]) ? $_POST["table"] : $table;
	$table_columns = array();

	if (isset($fields)) {
		foreach ($fields as $field) {
			$used[] = $field["column"];
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
			$table_description = BigTree::describeTable($table);
		} else {
			$table_description = array("foreign_keys" => array(), "columns" => array());
		}

		// Let's relate the foreign keys based on the local column so we can check easier.
		$foreign_keys = array();
		foreach ($table_description["foreign_keys"] as $key) {
			if (count($key["local_columns"]) == 1) {
				$foreign_keys[$key["local_columns"][0]] = $key;
			}
		}
		foreach ($table_description["columns"] as $column) {
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

				$fields[] = array("column" => $column["name"],"title" => $title, "subtitle" => $subtitle, "type" => $type,"options" => $options);
			}
			
			if ($column["name"] == "position" && $column["type"] == "int") {
				$positioned = true;
			}
		}
	}

	// Make sure this table has an "id" column and is auto increment, if not, throw a warning
	if (empty($table_description["columns"]["id"])) {
?>
<p class="error_message">The chosen table does not have a column named "id" which BigTree requires as a unique identifier.<br />Please an an "id" column INT(11) with Primary Key and Auto Increment settings.</p>
<?
	} elseif (!$table_description["columns"]["id"]["auto_increment"]) {
?>
<p class="error_message">The chosen table's "id" column is not set to auto increment. If you're adding to this table via BigTree, please set the column to auto increment.</p>
<?
	}

	$cached_types = $admin->getCachedFieldTypes(true);
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

			foreach ($fields as $field) {
				$key = $field["column"];
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
				<input type="text" name="fields[<?=$key?>][title]" <? if ($field["type"] == "geocoding") { ?>disabled="disabled" value="Geocoding"<? } else { ?>value="<?=$field["title"]?>"<? } ?> />
			</section>
			<section class="developer_resource_form_subtitle">
				<input type="text" name="fields[<?=$key?>][subtitle]" <? if ($field["type"] == "geocoding") { ?>disabled="disabled" value=""<? } else { ?>value="<?=$field["subtitle"]?>"<? } ?> />
			</section>
			<section class="developer_resource_type">
				<?
					if ($field["type"] == "geocoding") {
				?>
				<input type="hidden" name="fields[<?=$key?>][type]" value="geocoding" id="type_geocoding" />
				<span class="resource_name">Geocoding</span>
				<?
					} elseif ($field["type"] == "many-to-many") {
				?>
				<span class="resource_name">Many to Many</span>
				<input type="hidden" name="fields[<?=$key?>][type]" value="many-to-many" id="type_<?=$key?>" />
				<?
					} else {
				?>
				<select name="fields[<?=$key?>][type]" id="type_<?=$key?>">
					<optgroup label="Default">
						<? foreach ($types["default"] as $k => $v) { ?>
						<option value="<?=$k?>"<? if ($k == $field["type"]) { ?> selected="selected"<? } ?>><?=$v["name"]?></option>
						<? } ?>
					</optgroup>
					<? if (count($types["custom"])) { ?>
					<optgroup label="Custom">
						<? foreach ($types["custom"] as $k => $v) { ?>
						<option value="<?=$k?>"<? if ($k == $field["type"]) { ?> selected="selected"<? } ?>><?=$v["name"]?></option>
						<? } ?>
					</optgroup>
					<? } ?>
				</select>
				<?
					}
				?>
				<a href="#" class="options icon_settings" name="<?=$key?>"></a>
				<input type="hidden" name="fields[<?=$key?>][options]" value="<?=htmlspecialchars(json_encode($field["options"]))?>" id="options_<?=$key?>" />
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
	
	BigTree.localFieldSelect = BigTreeFieldSelect({
		selector: ".form_table header",
		elements: <?=json_encode($unused)?>,
		callback: function(el,fs) {
			var title = el.title;
			var key = el.field;
			
			var li = $('<li id="row_' + key + '">');
			li.html('<section class="developer_resource_form_title"><span class="icon_sort"></span><input type="text" name="fields[' + key + '][title]" value="' + title + '" /></section><section class="developer_resource_form_subtitle"><input type="text" name="fields[' + key + '][subtitle]" value="" /></section><section class="developer_resource_type"><select name="fields[' + key + '][type]" id="type_' + key + '"><optgroup label="Default"><? foreach ($types["default"] as $k => $v) { ?><option value="<?=$k?>"><?=$v["name"]?></option><? } ?></optgroup><? if (count($types["custom"])) { ?><optgroup label="Custom"><? foreach ($types["custom"] as $k => $v) { ?><option value="<?=$k?>"><?=$v["name"]?></option><? } ?></optgroup><? } ?></select><a href="#" class="options icon_settings" name="' + key + '"></a><input type="hidden" name="fields[' + key + '][options]" value="" id="options_' + key + '" /></section><section class="developer_resource_action"><a href="#" class="icon_delete" name="' + key + '"></a></section>');
			
			$("#resource_table").append(li);
			fs.removeCurrent();
			BigTree.localHooks();
		}
	});
</script>
<?
	} elseif (array_filter((array)$table_description["columns"])) {
?>
<p>The chosen table does not have any <a href="http://www.bigtreecms.org/docs/dev-guide/sql-queries/table-structure/#ReservedColumns" target="_blank">non-reserved columns</a>.</p>
<?
	} else {
?>
<p>Please choose a table to populate this area.</p>
<?
	}
?>
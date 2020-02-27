<?php
	namespace BigTree;
	
	/**
	 * @global ModuleForm $form
	 * @global string $table
	 */

	$reserved = ModuleForm::$ReservedColumns;
	
	$used = [];
	$unused = [];
	$positioned = false;
	$field_types = FieldType::reference(true, "modules");
	$table = isset($_POST["table"]) ? $_POST["table"] : $table;
	$table_columns = [];

	if (isset($fields)) {
		foreach ($fields as $field) {
			$used[] = $field["column"];
		}
		
		// Figure out the fields we're not using so we can offer them back.
		$table_description = SQL::describeTable($table);
		
		foreach ($table_description["columns"] as $column => $details) {
			if (!in_array($column,$reserved) && !in_array($column,$used)) {
				$unused[] = [
					"field" => $column,
					"title" => str_replace(["Url", "Pdf", "Sql"], ["URL", "PDF", "SQL"], ucwords(str_replace(["-", "_"], " ", $details["name"])))
				];
			}
			
			if ($column == "position") {
				$positioned = true;
			}
			
			$table_columns[] = $column;
		}
	} else {
		$fields = [];
		
		// To tolerate someone selecting the blank spot in the table dropdown again when creating a form.
		if ($table) {
			$table_description = SQL::describeTable($table);
		} else {
			$table_description = ["foreign_keys" => [], "columns" => []];
		}

		// Let's relate the foreign keys based on the local column so we can check easier.
		$foreign_keys = [];
		
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
				$title = str_replace(["Url", "Pdf", "Sql"], ["URL", "PDF", "SQL"], ucwords(str_replace(["-", "_"], " ", $column["name"])));
				$settings = [];
				
				if (strpos($title,"URL") !== false) {
					$subtitle = Text::translate("(include http://)");
					$type = "link";
				}

				if ($column["name"] == "route") {
					$type = "route";
				}
				
				if (strpos($title,"File") !== false || strpos($title,"PDF") !== false) {
					$type = "upload";
				}
				
				if (strpos($title,"Image") !== false) {
					$type = "image";
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
					$list = [];
					
					foreach ($column["settings"] as $option) {
						$list[] = ["value" => $option, "description" => $option];
					}
					
					$settings = [
						"list_type" => "static",
						"list" => $list
					];
					
					if ($column["allow_null"]) {
						$settings["allow-empty"] = "Yes";
					} else {
						$settings["allow-empty"] = "No";
					}
				}
				
				// Database populated list for foreign keys.
				if (substr($column["type"],-3,3) == "int" && isset($foreign_keys[$column["name"]]) && implode("", $foreign_keys[$column["name"]]["other_columns"]) == "id") {
					$type = "list";
					
					// Describe this other table
					$other_table = SQL::describeTable($foreign_keys[$column["name"]]["other_table"]);
					$ot_columns = $other_table["columns"];
					$desc_column = "";
					
					// Find the first short title-esque column and use it as the populated list descriptor
					while (!$desc_column && next($ot_columns)) {
						$col = current($ot_columns);
						if (($col["type"] == "varchar" || $col["type"] == "char") && $col["size"] > 2) {
							$desc_column = $col;
						}
					}
					
					$settings = ["list_type" => "db", "pop-table" => $foreign_keys[$column["name"]]["other_table"]];
					
					if ($desc_column) {
						$settings["pop-description"] = $desc_column["name"];
						$settings["pop-sort"] = $desc_column["name"]." ASC";
					}
					
					if ($column["allow_null"]) {
						$settings["allow-empty"] = "Yes";
					} else {
						$settings["allow-empty"] = "No";
					}
				}

				$fields[] = [
					"column" => $column["name"],
					"title" => $title,
					"subtitle" => $subtitle,
					"type" => $type,
					"settings" => $settings
				];
			}
			
			if ($column["name"] == "position" && $column["type"] == "int") {
				$positioned = true;
			}
		}
	}

	// Make sure this table has an "id" column and is auto increment, if not, throw a warning
	if (empty($table_description["columns"]["id"])) {
?>
<p class="error_message"><?=Text::translate('The chosen table does not have a column named "id" which BigTree requires as a unique identifier.<br />Please an an "id" column INT(11) with Primary Key and Auto Increment settings.')?></p>
<?php
	} elseif (!$table_description["columns"]["id"]["auto_increment"]) {
?>
<p class="error_message"><?=Text::translate('The chosen table\'s "id" column is not set to auto increment. If you\'re adding to this table via BigTree, please set the column to auto increment.')?></p>
<?php
	}
	
	if (count($fields) || count($unused)) {
?>
<label><?=Text::translate("Fields")?></label>

<div class="form_table<?php if (!$positioned) { ?> last<?php } ?>">
	<header>
		<a href="#" class="add add_geocoding"><span></span>Geocoding</a>
		<a href="#" class="add add_many_to_many"><span></span>Many-To-Many</a>
	</header>
	<div class="labels">
		<span class="developer_resource_form_title"><?=Text::translate("Title")?></span>
		<span class="developer_resource_form_subtitle"><?=Text::translate("Subtitle")?></span>
		<span class="developer_resource_type"><?=Text::translate("Type")?></span>
		<span class="developer_resource_action"><?=Text::translate("Delete")?></span>
	</div>
	<ul id="resource_table">
		<?php
			$mtm_count = 0;

			$custom_title = Text::translate("Custom", true);
			$default_title = Text::translate("Default", true);

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
				<input title="Title" type="text" name="fields[<?=$key?>][title]" <?php if ($field["type"] == "geocoding") { ?>disabled="disabled" value="Geocoding" <?php } else { ?>value="<?=$field["title"]?>"<?php } ?> />
			</section>
			<section class="developer_resource_form_subtitle">
				<input title="Subtitle" type="text" name="fields[<?=$key?>][subtitle]" <?php if ($field["type"] == "geocoding") { ?>disabled="disabled" value="" <?php } else { ?>value="<?=$field["subtitle"]?>"<?php } ?> />
			</section>
			<section class="developer_resource_type">
				<?php
					if ($field["type"] == "geocoding") {
				?>
				<input type="hidden" name="fields[<?=$key?>][type]" value="geocoding" id="type_<?=$key?>" />
				<span class="resource_name">Geocoding</span>
				<?php
					} elseif ($field["type"] == "many-to-many") {
				?>
				<span class="resource_name">Many to Many</span>
				<input type="hidden" name="fields[<?=$key?>][type]" value="many-to-many" id="type_<?=$key?>" />
				<?php
					} else {
				?>
				<select title="Type" name="fields[<?=$key?>][type]" id="type_<?=$key?>">
					<optgroup label="<?=$default_title?>">
						<?php foreach ($field_types["default"] as $k => $v) { ?>
						<option value="<?=$k?>"<?php if ($k == $field["type"]) { ?> selected="selected"<?php } ?>><?=$v["name"]?></option>
						<?php } ?>
					</optgroup>
					<?php if (count($field_types["custom"])) { ?>
					<optgroup label="<?=$custom_title?>">
						<?php foreach ($field_types["custom"] as $k => $v) { ?>
						<option value="<?=$k?>"<?php if ($k == $field["type"]) { ?> selected="selected"<?php } ?>><?=$v["name"]?></option>
						<?php } ?>
					</optgroup>
					<?php } ?>
				</select>
				<?php
					}
				?>
				<a href="#" class="icon_settings" name="<?=$key?>"></a>
				<input type="hidden" name="fields[<?=$key?>][settings]" value="<?=htmlspecialchars(json_encode($field["settings"]))?>" id="settings_<?=$key?>" />
			</section>
			<section class="developer_resource_action">
				<a href="#" class="icon_delete" name="<?=$key?>"></a>
			</section>
		</li>
		<?php
				}
			}
		?>
	</ul>
</div>

<?php if ($positioned) { ?>
<fieldset class="last">
	<label for="form_field_default_position"><?=Text::translate("Default Position <small>For New Entries</small>")?></label>
	<select id="form_field_default_position" name="default_position">
		<option><?=Text::translate("Bottom")?></option>
		<option<?php if (!empty($form) && $form->DefaultPosition == "Top") { ?> selected="selected"<?php } ?>><?=Text::translate("Top")?></option>
	</select>
</fieldset>
<?php } ?>

<script>
	Form.setMTMCount(<?=$mtm_count?>);
	Form.setFieldSelect(BigTreeFieldSelect({
		selector: ".form_table header",
		elements: <?=json_encode($unused)?>,
		callback: function(el,fs) {
			var title = el.title;
			var key = el.field;
			
			var li = $('<li id="row_' + key + '">');
			li.html('<section class="developer_resource_form_title"><span class="icon_sort"></span><input type="text" name="fields[' + key + '][title]" value="' + title + '" /></section><section class="developer_resource_form_subtitle"><input type="text" name="fields[' + key + '][subtitle]" value="" /></section><section class="developer_resource_type"><select name="fields[' + key + '][type]" id="type_' + key + '"><optgroup label="Default"><?php foreach ($field_types["default"] as $k => $v) { ?><option value="<?=$k?>"><?=$v["name"]?></option><?php } ?></optgroup><?php if (count($field_types["custom"])) { ?><optgroup label="Custom"><?php foreach ($field_types["custom"] as $k => $v) { ?><option value="<?=$k?>"><?=$v["name"]?></option><?php } ?></optgroup><?php } ?></select><a href="#" class="icon_settings" name="' + key + '"></a><input type="hidden" name="fields[' + key + '][settings]" value="" id="settings_' + key + '" /></section><section class="developer_resource_action"><a href="#" class="icon_delete" name="' + key + '"></a></section>');
			
			$("#resource_table").append(li);
			fs.removeCurrent();
			BigTree.localHooks();
		}
	}));
</script>
<?php
	} elseif (array_filter((array)$table_description["columns"])) {
?>
<p><?=Text::translate('The chosen table does not have any <a href=":doc_link:" target="_blank">non-reserved columns</a>.', false, [":doc_link:" => "https://www.bigtreecms.org/docs/dev-guide/sql-queries/table-structure/#ReservedColumns"])?></p>
<?php
	} else {
?>
<p><?=Text::translate("Please choose a table to populate this area.")?></p>
<?php
	}
?>
<?php
	namespace BigTree;
	
	CSRF::verify();
	
	$reserved = ["id", "position"];
	$fields = [];
	$column_definitions = [];
	$module = $_POST["module"];
	$table = "`".str_replace("`", "", $_POST["table"])."`";
	
	if (!count($_POST["titles"]) || empty($_POST["titles"])) {
		$_SESSION["developer"]["designer_errors"]["fields"] = true;
		$_SESSION["developer"]["saved_form"] = $_POST;
		Router::redirect($_SERVER["HTTP_REFERER"]);
	}
	
	foreach ($_POST["titles"] as $key => $field_title) {
		$field_type = $_POST["type"][$key];
		$field = [
			"title" => $field_title,
			"subtitle" => $_POST["subtitles"][$key],
			"type" => $field_type,
			"settings" => json_decode(str_replace(["\r", "\n"], ['\r', '\n'], $_POST["settings"][$key]), true)
		];
		
		$x = 2;
		$field_name = str_replace(["`", "-"], ["", "_"], Link::urlify($field_title));
		$original_field_name = $field_name;
		
		while (isset($fields[$field_name]) && !in_array($field_name, $reserved)) {
			$field_name = $original_field_name.$x;
			$x++;
		}
		
		$fields[$field_name] = $field;
		
		// Figure out what to make the MySQL field.
		$column_definition = "ADD COLUMN `$field_name` ";
		
		if ($field_type == "textarea" || $field_type == "html" || $field_type == "video") {
			$column_definition .= "TEXT";
		} elseif ($field_type == "media-gallery" || $field_type == "matrix" || $field_type == "callouts") {
			$column_definition .= "LONGTEXT";
		} elseif ($field_type == "date") {
			$column_definition .= "DATE";
		} elseif ($field_type == "time") {
			$column_definition .= "TIME";
		} elseif ($field_type == "datetime") {
			$column_definition .= "DATETIME";
		} else {
			$column_definition .= "VARCHAR(255)";
		}
		
		$column_definitions[] = $column_definition;
	}
	
	// Update the table
	SQL::query("ALTER TABLE $table ".implode(", ", $column_definitions));
	
	// Add the module form
	$form = ModuleForm::create($module, $_POST["title"], $_POST["table"], $fields);
	
	// Add module actions
	ModuleAction::create($module, "Add ".$_POST["title"], "add", "on", "add", $form->ID);
	ModuleAction::create($module, "Edit ".$_POST["title"], "edit", "", "edit", $form->ID);
	
	Router::redirect(DEVELOPER_ROOT."modules/designer/view/?module=$module&table=".urlencode($_POST["table"])."&title=".urlencode($_POST["title"]));
	
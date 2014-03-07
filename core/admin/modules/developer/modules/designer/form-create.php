<?
	$reserved = array("id","position");
	$fields = array();
	$adds = array();
	$module = $_POST["module"];
	$table = "`".$_POST["table"]."`";
	
	if (!count($_POST["titles"]) || empty($_POST["titles"])) {
		$_SESSION["developer"]["designer_errors"]["fields"] = true;
		$_SESSION["developer"]["saved_module"] = $_POST;
		BigTree::redirect($_SERVER["HTTP_REFERER"]);
	}
	
	foreach ($_POST["titles"] as $key => $ft) {
		$t = $_POST["type"][$key];
		$field = array(
			"title" => $ft,
			"subtitle" => $_POST["subtitles"][$key],
			"type" => $t
		);
		$options = json_decode(str_replace(array("\r","\n"),array('\r','\n'),$_POST["options"][$key]),true);
		if (is_array($options)) {
			foreach ($options as $k => $o) {
				$field[$k] = $o;
			}
		}
		
		$x = 2;
		$field_name = str_replace("-","_",$cms->urlify($ft));
		$ofn = $field_name;
		while (isset($fields[$field_name]) && !in_array($field_name,$reserved)) {
			$field_name = $ofn.$x;
			$x++;
		}
		$fields[$field_name] = $field;
		
		// Figure out what to make the MySQL field.
		$a = "ADD COLUMN `$field_name` ";
		if ($t == "textarea" || $t == "html" || $t == "photo-gallery" || $t == "array") {
			$a .= "TEXT";
		} elseif ($t == "date") {
			$a .= "DATE";
		} elseif ($t == "time") {
			$a .= "TIME";
		} elseif ($t == "datetime") {
			$a .= "DATETIME";
		} else {
			$a .= "VARCHAR(255)";
		}
		$adds[] = $a;
	}
	
	// Update the table
	sqlquery("ALTER TABLE $table ".implode(", ",$adds));
	
	// Add the module form	
	$form_id = $admin->createModuleForm($module,$_POST["title"],$_POST["table"],$fields);
	
	// Add module actions
	$admin->createModuleAction($module,"Add ".$_POST["title"],"add","on","add",$form_id);
	$admin->createModuleAction($module,"Edit ".$_POST["title"],"edit","","edit",$form_id);
	
	BigTree::redirect(DEVELOPER_ROOT."modules/designer/view/?module=$module&table=".urlencode($_POST["table"])."&title=".urlencode($_POST["title"]));
?>
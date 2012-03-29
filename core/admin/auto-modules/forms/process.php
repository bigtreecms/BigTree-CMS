<?
	$view = BigTreeAutoModule::getRelatedViewForForm($form);
	$data_action = ($_POST["save_and_publish"] || $_POST["save_and_publish_x"] || $_POST["save_and_publish_y"]) ? "publish" : "save";
	
	// Find out what kind of permissions we're allowed on this item.  We need to check the EXISTING copy of the data AND what it's turning into and find the lowest of the two permissions.
	$permission = $admin->getAccessLevel($module,$_POST,$form["table"]);
	if ($_POST["id"] && $permission && $permission != "n") {
		$existing_item = BigTreeAutoModule::getPendingItem($form["table"],$_POST["id"]);
		$previous_permission = $admin->getAccessLevel($module,$existing_item["item"],$form["table"]);
		
		// If the current permission is e or p, drop it down to e if the old one was e.
		if ($previous_permission != "p") {
			$permission = $previous_permission;
		}
	}
	
	if (!$permission || $permission == "n") {
		include BigTree::path("admin/atuo-modules/forms/_denied.php");
	} else {
		// Initiate the Upload Service class.
		$upload_service = new BigTreeUploadService;
		// Make sure we have permission to this module before update.
		$fields = $form["fields"];
		$crops = array();
		$many_to_many = array();
		$fails = array();
		
		// Let us figure out what was posted and get the data...!
		$item = array();
		
		$data = $_POST;
		$file_data = $_FILES;
		
		foreach ($fields as $key => $options) {
			$type = $options["type"];
			$tpath = BigTree::path("admin/form-field-types/process/$type.php");
			
			$no_process = false;
			// If we have a customized handler for this data type, run it, otherwise, it's simply the post value.
			if (file_exists($tpath)) {
				include $tpath;
			} else {
				$value = htmlspecialchars($data[$key]);
			}
			
			if (!BigTreeForms::validate($value,$options["validation"])) {
				$error = $options["error_message"] ? $options["error_message"] : BigTreeForms::errorMessage($value,$options["validation"]);
				$fails[] = array(
					"field" => $options["title"],
					"error" => $error
				);
			}
			
			$value = $admin->autoIPL($value);
		
			if (!$no_process) {
				$item[$key] = $value;
			}
		}
		
		// Sanitize the form data so it fits properly in the database (convert dates to MySQL-friendly format and such)
		$formParser = new BigTreeForms($form["table"]);
		$item = $formParser->sanitizeFormDataForDB($item);
		$tags = $_POST["_tags"];
		$resources = $_POST["_resources"];
		
		$edit_id = $_POST["id"] ? $_POST["id"] : false;
		$new_id = false;
		
		$table = $form["table"];
		// Let's stick it in the database or whatever!	
		if ($permission == "e" || $data_action == "save") {
			// We're an editor
			if ($edit_id) {
				BigTreeAutoModule::submitChange($module["id"],$table,$edit_id,$item,$many_to_many,$tags);
				$admin->growl($module["name"],"Saved ".$form["title"]." Draft");
			} else {
				$edit_id = "p".BigTreeAutoModule::createPendingItem($module["id"],$table,$item,$many_to_many,$tags);
				$admin->growl($module["name"],"Created ".$form["title"]." Draft");
			}
			
			if ($edit_id && is_numeric($edit_id)) {
				$published = true;
			} else {
				$published = false;
			}
		} elseif ($permission == "p" && $data_action == "publish") {
			// We're a publisher
			if ($edit_id) {
				if (substr($edit_id,0,1) == "p") {
					$edit_id = BigTreeAutoModule::publishPendingItem($table,substr($edit_id,1),$item,$many_to_many,$tags);
					$admin->growl($module["name"],"Updated & Published ".$form["title"]);
				} else {
					BigTreeAutoModule::updateItem($table,$edit_id,$item,$many_to_many,$tags);
					$admin->growl($module["name"],"Updated ".$form["title"]);
				}
			} else {
				$edit_id = BigTreeAutoModule::createItem($table,$item,$many_to_many,$tags);
				$admin->growl($module["name"],"Created ".$form["title"]);
			}
			$published = true;
		}
		
		// Kill off any applicable locks to the entry
		if ($edit_id) {
			$admin->unlock($table,$edit_id);
		}
		
		// Get the redirect location.
		$pieces = explode("-",$action["route"]);
		if (count($pieces) == 2) {
			$redirect_url = $admin_root.$module["route"]."/view-".$pieces[1]."/";
		} else {
			$redirect_url = $admin_root.$module["route"]."/";
		}
		
		if (end($path) == "preview") {
			$admin->ungrowl();
			$redirect_url = $view["preview_url"].$edit_id."/?bigtree_preview_bar=true";
		}
		
		// Check to see if this is a positioned element, if it is and the form is selected to move to the top, update the record.
		$cols = sqlcolumns($table);
		if ($cols["position"] && $form["default_position"] == "Top" && !$_POST["id"]) {
			$max = sqlrows(sqlquery("SELECT id FROM `$table`"));
			BigTreeAutoModule::updateItem($table,$edit_id,array("position" => $max));
		}
			
		// If there's a callback function for this module, let's get'r'done.
		if ($form["callback"]) {
			$function = htmlspecialchars_decode($form["callback"]).'($edit_id,$item,$published);';
			eval($function);
		}
		
		if (count($crops) == 0) {
			if (count($fails)) {
				$suffix = $view["suffix"] ? "-".$view["suffix"] : "";
				$edit_link = $admin_root.$module["route"]."/edit$suffix/$edit_id/";
?>
<h1>Errors Occurred</h1>
<div class="table">
	<summary>
		<p>Your submission had <?=count($fails)?> error<? if (count($fails) != 1) { ?>s<? } ?>.</p>
	</summary>
	<header>
		<span class="view_column" style="width: 250px;">Field</span>
		<span class="view_column" style="width: 668px;">Error</span>
	</header>
	<ul>
		<? foreach ($fails as $fail) { ?>
		<li>
			<section class="view_column" style="width: 250px;"><?=$fail["field"]?></section>
			<section class="view_column" style="width: 668px;"><?=$fail["error"]?></section>
		</li>
		<? } ?>
	</ul>
</div>
<a href="<?=$redirect_url?>" class="button blue">Continue</a> &nbsp; <a href="<?=$edit_link?>" class="button">Edit Entry</a> &nbsp; <a href="#" class="delete button red">Delete Entry</a>
<script type="text/javascript">
	$(".delete").click(function() {
		$.ajax("<?=$admin_root?>ajax/auto-modules/views/delete/?view=<?=$view["id"]?>&id=<?=$edit_id?>", {
			complete: function() {
				document.location = '<?=$redirect_url?>';
			}
		});
		
		return false;
	});
</script>
<?
			} else {
				header("Location: $redirect_url");
				die();
			}
		} else {
			include BigTree::path("admin/auto-modules/forms/_crop.php");
		}
	}
?>
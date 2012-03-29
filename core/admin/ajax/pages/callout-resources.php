<?
	if (isset($_POST["data"])) {
		$resources = json_decode(base64_decode($_POST["data"]),true);
		foreach ($resources as &$val) {
			if (is_array(json_decode($val,true))) {
				$val = BigTree::untranslateArray(json_decode($val,true));
			} else {
				$val = $cms->replaceInternalPageLinks($val);
			}
		}
		
		$type = $resources["type"];
	}
	
	if (isset($_POST["count"])) {
		$bigtree_callout_count = $_POST["count"];
	}
	
	$type = isset($_POST["type"]) ? $_POST["type"] : $type;
	
	$callout = $admin->getCallout($type);
	
	if ($callout["description"]) {
?>
<p><?=htmlspecialchars($callout["description"])?></p>
<?
	}
	
	$tabindex = 1000;
	
	if (count($callout["resources"])) {
		foreach ($callout["resources"] as $options) {
			$key = "callouts[$count][".$options["id"]."]";
			$type = $options["type"];
			$title = $options["name"];
			$subtitle = $options["subtitle"];
			$options["directory"] = "files/pages/";
			$value = $resources[$options["id"]];
			$currently_key = "callouts[$bigtree_callout_count][currently_".$options["id"]."]";
			include BigTree::path("admin/form-field-types/draw/$type.php");
			$tabindex++;
		}
	}
?>

<script type="text/javascript">
	BigTreeCustomControls();
	
	if (!tinyMCE) {
		tiny = new Element("script");
		tiny.src = "<?=$admin_root?>js/tiny_mce/tiny_mce.js";
		$("body").append(tiny);
	}
</script>
<?
	$mce_width = 400;
	$mce_height = 150;
	
	if (count($htmls)) {
		include BigTree::path("admin/layouts/_tinymce_specific.php");
	}
	if (count($simplehtmls)) {
		include BigTree::path("admin/layouts/_tinymce_specific_simple.php");
	}
?>
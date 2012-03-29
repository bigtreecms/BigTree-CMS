<?
	$page = end($path);
	
	// Check for a page lock
	$lock = $admin->lockCheck("bigtree_pages",$page,"admin/modules/pages/front-end-locked.php",$_GET["force"],false);
	
	if ($lock) {	
		if ($page[0] == "p") {
			$cid = substr($page,1);
			$f = $admin->getPendingChange($cid);
			$pdata = $f["changes"];
			$pdata["updated_at"] = $f["date"];
			$r = $admin->getPageAccessLevel($pdata["parent"]);
		
			$tags = array();
			$temp_tags = json_decode($f["tags_changes"],true);
			if (is_array($temp_tags)) {
				foreach ($temp_tags as $tag) {
					$tags[] = $admin->getTag($tag);
				}
			}
			$presources = json_decode($f["resources_changes"],true);
			
			$pdata["id"] = $page;
		} else {
			$r = $admin->getPageAccessLevel($page);
			$pdata = $admin->getPendingPage($page);
			$tags = $pdata["tags"];
			if ($admin->getPageChanges($page)) {
				$show_revert = true;
			}
		}
		
		if ($r == "p") {
			$publisher = true;
		} elseif ($r == "e") {
			$publisher = false;
		} else {
			die("You do not have access to this page.");
		}
		
		$template = $cms->getTemplate($pdata["template"]);
		$resources = $cms->decodeResources($pdata["resources"]);
			
		$htmls = array();
		$small_htmls = array();
		$tabindex = 1;
?>
<html>
	<head>
		<link rel="stylesheet" href="<?=$admin_root?>css/main.css" type="text/css" media="screen" charset="utf-8" />
		<script type="text/javascript" src="<?=$admin_root?>js/lib.js"></script>
		<script type="text/javascript" src="<?=$admin_root?>js/main.js"></script>
		<script type="text/javascript" src="<?=$admin_root?>js/pages.js"></script>
	</head>
	<body>
		<div id="bigtree_dialog_window" class="front_end_editor">
			<h2>Edit Page Content</h2>
			<form id="bigtree_dialog_form" method="post" action="<?=$admin_root?>pages/front-end-update/<?=$page?>/" enctype="multipart/form-data">
				<div class="overflow">
					<p class="error_message" style="display: none;">Errors found! Please fix the highlighted fields before submitting.</p>
					<?
						foreach ($template["resources"] as $options) {
							$no_file_browser = true;
							$key = $options["id"];
							$type = $options["type"];
							$title = $options["title"];
							$subtitle = $options["subtitle"];
							$value = $resources[$key];
							$options["directory"] = "files/pages/";
							$currently_key = "resources[currently_$key]";
							$key = "resources[$key]";
							
							// Setup Validation Classes
							$label_validation_class = "";
							$input_validation_class = "";
							if ($options["validation"]) {
								if (strpos($options["validation"],"required") !== false) {
									$label_validation_class = ' class="required"';
								}
								$input_validation_class = ' class="'.$options["validation"].'"';
							}
							
							if ($options["wrapper"]) {
								echo $options["wrapper"];
							}
							
							include BigTree::path("admin/form-field-types/draw/$type.php");
							
							if ($options["wrapper"]) {
								$parts = explode(" ",$options["wrapper"]);
								echo "</".substr($parts[0],1).">";
							}
					
							$tabindex++;
						}
					
						$mce_width = 760;
						$mce_height = 365;
						
						if (count($htmls) || count($small_htmls) || count ($simplehtmls)) {
							include BigTree::path("admin/layouts/_tinymce.php");
							if (count($htmls)) {
								include BigTree::path("admin/layouts/_tinymce_specific.php");
							}
							if (count($small_htmls)) {
								include BigTree::path("admin/layouts/_tinymce_block_small.php");
							}
							if (count($simplehtmls)) {
								include BigTree::path("admin/layouts/_tinymce_specific_simple.php");
							}
						}
					?>
				</div>
				<footer>
					<a class="button" href="#">Cancel</a>
					<input type="submit" class="button<? if (!$publisher) { ?> blue<? } ?>" name="ptype" value="Save" />
					<? if ($publisher) { ?>
					<input type="submit" class="button blue" name="ptype" value="Save &amp; Publish" />
					<? } ?>
				</footer>
			</form>
		</div>
		
		<script type="text/javascript">
			<? if (is_array($dates)) { foreach ($dates as $id) { ?>
			$(document.getElementById("<?=$id?>")).datepicker();
			<? } } ?>
		
			BigTreeCustomControls();
			BigTreeFormValidator("#bigtree_dialog_form");
			
			$("footer a").click(function() {
				parent.bigtree_bar_cancel();
				
				return false;
			});
			
			var page = "<?=$pdata["id"]?>";
			lockTimer = setInterval("$.ajax('<?=$admin_root?>ajax/pages/refresh-lock/', { type: 'POST', data: { id: '<?=$lockid?>' } });",60000);
		</script>
	</body>
</html>
<?
	}
	
	die();
?>
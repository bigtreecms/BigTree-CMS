<?
	$parts = explode("/",$_POST["directory"]);
	$postdirectory = array();
	foreach ($parts as $part) {
		if ($part == "..") {
			unset($postdirectory[count($postdirectory)-1]);
		} elseif ($part) {
			$postdirectory[] = $part;
		}
	}
	if (count($postdirectory)) {
		$postdirectory = implode("/",$postdirectory)."/";
	} else {
		$postdirectory = "";
	}
	$directory = $server_root.$postdirectory;
	$subdirectories = array();
	$files = array();
	if ($postdirectory) {
		if (!$_POST["lockInSite"] || strlen($postdirectory) > 5)
			$subdirectories[] = "..";
	}
	$o = opendir($directory);
	while ($r = readdir($o)) {
		if ($r != "." && $r != "..") {
			if (is_dir($directory.$r)) {
				$subdirectories[] = $r;	
			} else {
				$files[] = $r;
			}
		}
	}
?>
<div class="directory">Current Directory: <em><?=str_replace($server_root,"/",$directory)?></em></div>
<div class="navigation_pane">
	<ul>
		<? foreach ($subdirectories as $d) { ?>
		<li><a href="<?=$d?>"><span class="icon_small icon_small_folder"></span><?=$d?></a></li>
		<? } ?>
	</ul>
</div>
<div class="browser_pane">
	<ul>
		<?
			foreach ($files as $file) {
				$parts = BigTree::pathInfo($file);
				$ext = strtolower($parts["extension"]);
				if (($ext == "png" || $ext == "jpg" || $ext == "gif") && substr($postdirectory,0,5) == "site/") {
					$image = $www_root.str_replace("site/","",$postdirectory).$file;
					$class = "image";
				} else {
					if (file_exists(BigTree::path("admin/images/icons/file-types/$ext.png"))) {
						$image = $aroot."images/icons/file-types/$ext.png";
					} else {
						$image = $aroot."images/icons/file-types/other.png";
					}
					$class = "file";
				}
		?>
		<li class="<?=$class?>"><?=$file?></li>
		<?
			}
		?>
	</ul>
	<input type="hidden" name="file" id="bigtree_foundry_selected_file" value="" />
	<input type="hidden" name="directory" value="<?=$postdirectory?>" id="bigtree_foundry_directory" />
	<input type="submit" value="Use Selected File" class="button blue" />
	<a href="#" class="button">Cancel</a>
</div>

<script type="text/javascript">
	$("#bigtree_foundry_browser_window .navigation_pane a").click(function(ev) {
		directory = "<?=$postdirectory?>" + $(this).attr("href") + "/";
		$("#bigtree_foundry_browser_form").load("<?=$admin_root?>ajax/foundry/file-browser/", { directory: directory });
		return false;
	});
	
	$("#bigtree_foundry_browser_window .browser_pane li").click(function() {
		$(".browser_pane li").removeClass("selected");
		$(this).addClass("selected");
		$("#bigtree_foundry_selected_file").val($(this).html());
		return false;
	});
	
	$("#bigtree_foundry_browser_window a.button").click(function() {
		$("#bigtree_dialog_overlay, #bigtree_foundry_browser_window").remove();
		return false;
	});
</script>
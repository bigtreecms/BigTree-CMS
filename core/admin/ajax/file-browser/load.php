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
		<li><a href="<?=$d?>"><?=$d?></a></li>
		<? } ?>
	</ul>
</div>
<div class="browser_pane">
	<span id="full_file_name"></span>
	<a class="images" href="#"></a>
	<a class="files selected" href="#"></a>
	<ul>
		<?
			foreach ($files as $file) {
				$parts = BigTree::pathInfo($file);
				$ext = strtolower($parts["extension"]);
				if (($ext == "png" || $ext == "jpg" || $ext == "gif") && substr($postdirectory,0,5) == "site/") {
					$image = $www_root.str_replace("site/","",$postdirectory).$file;
					$class = "image";
				} else {
					if (file_exists(BigTree::path("admin/images/file-types/$ext.png"))) {
						$image = $admin_root."images/file-types/$ext.png";
					} else {
						$image = $admin_root."images/file-types/other.png";
					}
					$class = "file";
				}
		?>
		<li class="<?=$class?>">
			<div><img src="<?=$image?>" alt="" /></div>
			<span><?=$file?></span>
		</li>
		<?
			}
		?>
	</ul>
	<input type="hidden" name="file" id="selected_file" value="" />
	<input type="hidden" name="directory" value="<?=$postdirectory?>" />
	<input type="submit" value="Choose Selected File" class="button white small" />
</div>

<script type="text/javascript">
	$(".navigation_pane a").click(function() {
		directory = "<?=$postdirectory?>" + $(this).attr("href") + "/";
		$("#bigtree_browser_form").load("<?=$admin_root?>ajax/file-browser/load/", { directory: directory, lockInSite: <? if ($_POST["lockInSite"]) { echo '"on"'; } else { echo '""'; } ?> });
		
		return false;
	});

	$(".browser_pane .images").click(function() {
		$(this).addClass("selected");
		$(".browser_pane .file").hide();
		$(".browser_pane .files").removeClass("selected");
		
		return false;
	});
	
	$(".browser_pane .files").click(function() {
		$(this).addClass("selected");
		$(".browser_pane .file").show();
		$(".browser_pane .images").removeClass("selected");
		
		return false;
	});
	
	$(".browser_pane li").each(function() {
		$(this).mouseenter(function() {
			$("#full_file_name").html($(this).find("span").html());
		});
		$(this).mouseleave(function() {
			$("#full_file_name").html("");
		});
		$(this).click(function() {
			$(".browser_pane li").removeClass("selected");
			$(this).addClass("selected");
			$("#selected_file").val($(this).find("span").html());
			
			return false;
		});
	});
</script>
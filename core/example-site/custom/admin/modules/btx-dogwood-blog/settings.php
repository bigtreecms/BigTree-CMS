<?
	$module = $admin->getModuleByRoute($path[1]);
	// Calculate related modules
	if ($module["group"]) {
		$mgroup = $admin->getModuleGroup($module["group"]);
		$other = $admin->getModulesByGroup($module["group"]);
		if (count($other) > 1) {
			$subnav = array();
			foreach ($other as $more) {
				$subnav[] = array("title" => $more["name"], "link" => $more["route"]."/");
			}
		}
	}
	
	// Calculate breadcrumb
	$breadcrumb = array(
		array("link" => "modules/","title" => "Modules")
	);
	if ($mgroup) {
		$breadcrumb[] = array("link" => "modules/", "title" => $mgroup["name"]);
	}
	$breadcrumb[] = array("link" => $module["route"], "title" => $module["name"]);
	$breadcrumb[] = array("link" => "#", "title" => $action_title);
	
	// Module Actions
	$actions = $admin->getModuleNavigation($module);
	
	$settings = $cms->getSetting("btx-dogwood-settings");
?>
<h1>
	<span class="modules"></span>Blog Settings
	<? if (count($subnav)) { ?>
	<nav class="jump_group">
		<span class="icon"></span>
		<nav class="dropdown">
			<strong><?=$mgroup["name"]?></strong>
			<? foreach ($subnav as $link) { ?>
			<a href="<?=$admin_root?><?=$link["link"]?>"><?=$link["title"]?></a>
			<? } ?>
		</nav>
	</nav>
	<? } ?>
</h1>
<nav class="sub">
	<ul>
		<?
			foreach ($actions as $a) {
				if ($a["level"] <= $admin->Level) {
		?>
		<li><a href="<?=$admin_root?><?=$module["route"]?>/<? if ($a["route"]) { echo $a["route"]."/"; } ?>"<? if (end($path) == $a["route"]) { ?> class="active"<? } ?>><span class="icon_small icon_small_<?=$a["class"]?>"></span><?=$a["name"]?></a></li>
		<?
				}
			}
		?>
	</ul>
</nav>
<div class="form_container">
	<form method="post" action="../update-settings/">
		<section>
			<fieldset>
				<label>Blog Title</label>
				<input type="text" name="title" value="<?=$settings["title"]?>" />
			</fieldset>
			<fieldset>
				<label>Blog Tagline</label>
				<input type="text" name="tagline" value="<?=$settings["tagline"]?>" />
			</fieldset>
			<fieldset>
				<label>Disqus Shortname <small>(to enable commenting)</small></label>
				<input type="text" name="disqus" value="<?=$settings["disqus"]?>" />
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Update" />
		</footer>
	</form>
</div>
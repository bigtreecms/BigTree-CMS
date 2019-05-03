<?php
	namespace BigTree;
	
	// Check for newer versions of BigTree
	$ignored_all = true;
	$updates = [];
	
	if (!$_COOKIE["bigtree_admin"]["deferred_update"]) {
		$updates = array_filter((array)@json_decode(cURL::request("https://www.bigtreecms.org/ajax/version-check/?current_version=".BIGTREE_VERSION, false, [CURLOPT_CONNECTTIMEOUT => 1, CURLOPT_TIMEOUT => 5]), true));
		// See if we've ignored these updates
		$ignorable = [];
		
		foreach ($updates as $update) {
			if (!$_COOKIE["bigtree_admin"]["ignored_update"][$update["version"]]) {
				$ignored_all = false;
			}
			
			$ignorable[] = $update["version"];
		}
	}
	
	// If we're ignoring updates through config, still ignore them
	if (!empty(Router::$Config["ignore_admin_updates"])) {
		$ignored_all = true;
	}

	// Flag for showing/hiding the developer dashboard
	$showing_updates = false;

	// Updates are available and we didn't ignore them
	if (!$ignored_all && count($updates)) {
		$showing_updates = true;
?>
<div class="container">
	<div class="container_summary"><h2><?=Text::translate("Update Available")?></h2></div>
	<section>
		<p><?=Text::translate("You are currently running BigTree :version:. The following update(s) are available:", false, [":version:" => BIGTREE_VERSION])?></p>
		<ul>
			<?php
				foreach ($updates as $type => $update) {
					if (!$_COOKIE["bigtree_admin"]["ignored_update"][$update["version"]]) {
			?>
			<li>
				<strong><?=$update["version"]?></strong> &mdash; <?=Text::translate("Released")?> <?=date("F j, Y",strtotime($update["release_date"]))?> &mdash; 
				<?php
					if ($type == "revision") {
						echo Text::translate("This is a bugfix release and is recommended for all users.");
					} elseif ($type == "minor") {
						echo Text::translate("This is a feature release. Though it should be backwards compatible it is recommended that you test the update on your development site before running it on your live site.");
					} elseif ($type == "major") {
						echo Text::translate("This is a major update and is not backwards compatible. You must install this release manually.");
					}
				?>
			</li>
			<?php
					}
				}
			?>
		</ul>
	</section>
	<footer>
		<?php
			foreach ($updates as $type => $update) {
				if ($type != "major" && !$_COOKIE["bigtree_admin"]["ignored_update"][$update["version"]]) {
		?>
		<a class="button<?php if ($type == "revision") { ?> blue<?php } ?>" href="<?=DEVELOPER_ROOT?>upgrade/init/?type=<?=$type?>"><?=Text::translate("Upgrade To :version:", false, [":version:" => $update["version"]])?></a>
		<?php
				}
			}
		?>
		<a class="button" href="<?=DEVELOPER_ROOT?>upgrade/remind/"><?=Text::translate("Remind Me In 1 Week")?></a>
		<a class="button red" href="<?=DEVELOPER_ROOT?>upgrade/ignore/?versions=<?=urlencode(json_encode($ignorable))?><?php CSRF::drawGETToken(); ?>"><?=Text::translate("Ignore These Updates")?></a>
	</footer>
</div>
<?php
	}
	
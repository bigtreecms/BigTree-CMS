<?
	// Check for newer versions of BigTree
	$ignored_all = true;
	if (!$_COOKIE["bigtree_admin"]["deferred_update"]) {
		$updates = @json_decode(BigTree::cURL("http://www.bigtreecms.org/ajax/version-check/?current_version=".BIGTREE_VERSION),true);
		// See if we've ignored these updates
		$ignorable = array();
		foreach ($updates as $update) {
			if (!$_COOKIE["bigtree_admin"]["ignored_update"][$update["version"]]) {
				$ignored_all = false;
			}
			$ignorable[] = $update["version"];
		}
	}
	// If we're ignoring updates through config, still ignore them
	if (!empty($bigtree["config"]["ignore_admin_updates"])) {
?>
<div class="container">
	<section>
		<div class="alert">
			<span></span>
			<h3>Error</h3>
		</div>
		<p>
			BigTree updates have been ignored in the configuration file ($bigtree["config"]["ignore_admin_updates"] is set to a truthy value).
		</p>
	</section>
	<footer>
		<a href="javascript:history.go(-1);" class="button white">Return</a>
	</footer>
</div>
<?
	// Non-ignored updates are available
	} else if (!$ignored_all && count($updates)) {
?>
<div class="container">
	<summary><h2>Update Available</h2></summary>
	<section>
		<p>You are currently running BigTree <?=BIGTREE_VERSION?>. The following update<? if (count($updates) > 1) { ?>s are<? } else { ?> is<? } ?> available:</p>
		<ul>
			<?
				foreach ($updates as $type => $update) {
					if (!$_COOKIE["bigtree_admin"]["ignored_update"][$update["version"]]) {
			?>
			<li>
				<strong><?=$update["version"]?></strong> &mdash; Released <?=date("F j, Y",strtotime($update["release_date"]))?> &mdash; 
				<?
					if ($type == "revision") {
						echo "This is a bugfix release and is recommended for all users.";
					} elseif ($type == "minor") {
						echo "This is a feature release. Though it should be backwards compatible it is recommended that you test the update on your development site before running it on your live site.";
					} elseif ($type == "major") {
						echo "This is a major update and is not backwards compatible. You must install this release manually.";
					}
				?>
			</li>
			<?
					}
				}
			?>
		</ul>
	</section>
	<footer>
		<?
			foreach ($updates as $type => $update) {
				if ($type != "major" && !$_COOKIE["bigtree_admin"]["ignored_update"][$update["version"]]) {
		?>
		<a class="button<? if ($type == "revision") { ?> blue<? } ?>" href="<?=DEVELOPER_ROOT?>upgrade/init/?type=<?=$type?>">Upgrade To <?=$update["version"]?></a>
		<?
				}
			}
		?>
		<a class="button" href="<?=DEVELOPER_ROOT?>upgrade/remind/">Remind Me In 1 Week</a>
		<a class="button red" href="<?=DEVELOPER_ROOT?>upgrade/ignore/?versions=<?=urlencode(json_encode($ignorable))?>">Ignore These Updates</a>
	</footer>
</div>
<?
	} else {
?>
<div class="container">
	<section>
		<p>No updates are available.</p>
	</section>
	<footer>
		<a href="javascript:history.go(-1);" class="button white">Return</a>
	</footer>
</div>
<?
	}
?>
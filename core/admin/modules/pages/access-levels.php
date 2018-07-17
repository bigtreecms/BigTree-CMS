<?php
	if ($admin->Level < 1) {
		$admin->stop("Permission denied.");
	}

	$publishers = [];
	$editors = [];
	$users = $admin->getUsers();

	foreach ($users as $user) {
		$level = $admin->getPageAccessLevelByUser($bigtree["current_page"]["id"], $user["id"]);

		if ($level == "p") {
			$publishers[] = $user;
		} elseif ($level == "e") {
			$editors[] = $user;
		}
	}
?>
<form class="container" method="get" action="">
	<summary>
		<h2>Access Levels</h2>
	</summary>
	<section>
		<div class="left">
			<h3>Editors</h3>
			<ul>
				<?php
					foreach ($editors as $user) {
				?>
				<li><a href="<?=ADMIN_ROOT?>users/edit/<?=$user["id"]?>/"><?=$user["name"]?></a></li>
				<?php
					}
				?>
			</ul>
		</div>
		<div class="right">
			<h3>Publishers</h3>
			<ul>
				<?php
					foreach ($publishers as $user) {
				?>
				<li><a href="<?=ADMIN_ROOT?>users/edit/<?=$user["id"]?>/"><?=$user["name"]?></a></li>
				<?php
					}
				?>
			</ul>
		</div>
	</section>
	<footer>
		<a href="<?=ADMIN_ROOT?>pages/edit/<?=$bigtree["current_page"]["id"]?>/" class="button blue">Return to Editing</a>
	</footer>
</form>
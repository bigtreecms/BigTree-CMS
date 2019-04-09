<?php
	namespace BigTree;
	
	/**
	 * @global Page $page
	 */
	
	if (Auth::user()->Level < 1) {
		Auth::stop(Text::translate("Permission denied."));
	}
	
	$publishers = [];
	$editors = [];
	$users = User::all("name ASC");
	
	foreach ($users as $user) {
		$level = $page->getUserAccessLevel($user);
		
		if ($level == "p") {
			$publishers[] = $user;
		} elseif ($level == "e") {
			$editors[] = $user;
		}
	}
?>
<form class="container" method="get" action="">
	<summary>
		<h2><?=Text::translate("Access Levels")?></h2>
	</summary>
	<section>
		<div class="left">
			<h3><?=Text::translate("Editors")?></h3>
			<ul>
				<?php
					foreach ($editors as $user) {
				?>
				<li><a href="<?=ADMIN_ROOT?>users/edit/<?=$user->ID?>/"><?=$user->Name?></a></li>
				<?php
					}
				?>
			</ul>
		</div>
		<div class="right">
			<h3><?=Text::translate("Publishers")?></h3>
			<ul>
				<?php
					foreach ($publishers as $user) {
				?>
				<li><a href="<?=ADMIN_ROOT?>users/edit/<?=$user->ID?>/"><?=$user->Name?></a></li>
				<?php
					}
				?>
			</ul>
		</div>
	</section>
	<footer>
		<a href="<?=ADMIN_ROOT?>pages/edit/<?=$page->ID?>/" class="button blue"><?=Text::translate("Return to Editing")?></a>
	</footer>
</form>
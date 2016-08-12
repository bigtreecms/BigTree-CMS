<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 * @global string $page_id
	 * @global string $refresh_link
	 */
?>
<h2><?=Text::translate("Errors Occurred")?></h2>
<div class="bigtree_dialog_form">
	<div class="overflow">
		<div class="table">
			<div class="table_summary">
				<p><?=Text::translate("Your submission had")?> <?=count($bigtree["errors"])?> <?=Text::translate(count($bigtree["errors"]) == 1 ? "error" : "errors")?>.</p>
			</div>
			<header>
				<span class="view_column" style="padding: 0 0 0 20px; width: 250px;"><?=Text::translate("Field")?></span>
				<span class="view_column" style="width: 506px;">Error</span>
			</header>
			<ul>
				<?php foreach ($bigtree["errors"] as $error) { ?>
				<li>
					<section class="view_column" style="padding: 0 0 0 20px; width: 250px;"><?=$error["field"]?></section>
					<section class="view_column" style="width: 506px;"><?=$error["error"]?></section>
				</li>
				<?php } ?>
			</ul>
		</div>
	</div>
	<footer>
		<a href="<?=ADMIN_ROOT?>pages/front-end-return/<?=base64_encode($refresh_link)?>/" class="button white"><?=Text::translate("Ignore")?></a>				
		<a href="<?=ADMIN_ROOT?>pages/front-end-edit/<?=$page_id?>/" class="button blue"><?=Text::translate("Go Back")?></a>
	</footer>
</div>
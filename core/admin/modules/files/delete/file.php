<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */
	
	if (!Resource::exists(Router::$Commands[0])) {
		Auth::stop("Invalid resource.");
	}
	
	$file = new Resource(Router::$Commands[0]);
	
	if ($file->UserAccessLevel != "p") {
		Auth::stop("Access denied.");
	}
?>
<form class="container" method="post" action="<?=ADMIN_ROOT?>files/process/delete-file/">
	<?php CSRF::drawPOSTToken(); ?>
	<input type="hidden" name="id" value="<?=$file->ID?>">

	<section>
		<p><?=Text::translate("Are you sure you want to delete: :file_name:?", false, [":file_name:" => $file->Name])?></p>
		<?php
			if ($file->AllocationCount) {
		?>
		<hr>
		<p><?=Text::translate("This file is presently being used in <strong>:count:</strong> place(s).", false, [":count:" => $file->AllocationCount])?></p>
		<?php
			}
		?>
	</section>

	<footer>
		<input type="submit" class="button red" value="<?=Text::translate("Delete File", true)?>">
	</footer>
</form>
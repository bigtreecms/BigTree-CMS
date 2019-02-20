<?php
	namespace BigTree;
	
	/**
	 * @global ResourceFolder $folder
	 */
	
	$bigtree["breadcrumb"][] = ["link" => "#", "title" => "Add Video"];
?>
<form action="<?=ADMIN_ROOT?>files/process/video/" method="post" class="container">
	<?php CSRF::drawPOSTToken(); ?>
	<input type="hidden" name="folder" value="<?=$folder->ID?>">

	<section>
		<?php
			if (!empty($_GET["error"])) {
		?>
		<p class="error_message"><?=Text::translate($_GET["error"], true)?></p>
		<?php
			}
		?>
		<fieldset>
			<label for="file_manager_field_video"><?=Text::translate("Video URL <small>Supported Services: YouTube, Vimeo</small>")?></label>
			<input type="url" name="video" id="file_manager_field_video">
		</fieldset>
	</section>

	<footer>
		<input type="submit" class="button blue" value="<?=Text::translate("Continue", true)?>">
	</footer>
</form>
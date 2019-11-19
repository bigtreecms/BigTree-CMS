<?php
	namespace BigTree;
	
	/**
	 * @global ResourceFolder $folder
	 */
	
	$bigtree["js"][] = "dropzone.js";
	$bigtree["breadcrumb"][] = ["link" => "#", "title" => "Add Files"];
	$storage = new Storage;
	
	// Clean out the temp directory
	FileSystem::deleteDirectory(SITE_ROOT."files/temporary/".Auth::user()->ID."/");
?>
<div class="container file_manager_wrapper">	
	<section>
		<form action="<?=ADMIN_ROOT?>ajax/files/dropzone-upload/?1<?php CSRF::drawGETToken(); ?>" class="dropzone" id="file_manager_dropzone">
			<p><?=Text::translate("Drag and drop files into this zone or click to manually upload.")?></p>
		</form>
	</section>

	<footer>
		<a href="<?=ADMIN_ROOT?>files/process/file/<?=$folder->ID?>/" class="blue button disabled js-continue-button"><?=Text::translate("Continue")?></a>
	</footer>
</div>

<script>
	(function() {
		var ContinueButton = $(".js-continue-button");

		Dropzone.options.fileManagerDropzone = {
			accept: function(file, done) {
				var parts = file.name.split(".");
				var extension = parts[parts.length - 1].toLowerCase();
				var disallowed = <?=json_encode($storage->DisabledFileExtensions)?>;

				if (disallowed.indexOf(extension) > -1) {
					done("<?=Text::translate("This file type is disabled for security reasons.", true)?>");
				} else if (file.type.indexOf("image") !== -1) {
					done("<?=Text::translate("This form does not accept images.", true)?>");
				} else {
					done();
				}
			},
			init: function() {
				this.on("success", function() {
					ContinueButton.removeClass("disabled");
				});
			}
		};
	
		ContinueButton.click(function() {
			if ($(this).hasClass("disabled")) {
				return false;
			}
	
			$(this).addClass("disabled");
			$(this).after('<span class="button_loader"></span>');
		});
	})();
</script>
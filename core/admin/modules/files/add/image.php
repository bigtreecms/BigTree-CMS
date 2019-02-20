<?php
	namespace BigTree;
	
	/**
	 * @global ResourceFolder $folder
	 */
	
	$bigtree["js"][] = "dropzone.js";
	$bigtree["breadcrumb"][] = ["link" => "#", "title" => "Add Images"];

	// Clean out the temp directory
	FileSystem::deleteDirectory(SITE_ROOT."files/temporary/".Auth::user()->ID."/");
?>
<div class="container file_manager_wrapper">	
	<section>
		<form action="<?=ADMIN_ROOT?>ajax/files/dropzone-upload-image/" class="dropzone" id="file_manager_dropzone">
			<?php CSRF::drawPOSTToken(); ?>
			<input type="hidden" name="folder" value="<?=$folder->ID?>">
			<p><?=Text::translate("Drag and drop files into this zone or click to manually upload.")?></p>
		</form>
	</section>

	<footer>
		<form method="post" action="<?=ADMIN_ROOT?>files/process/image/<?=$folder->ID?>/" id="files_form">
			<input type="hidden" name="folder" value="<?=$folder->ID?>">
			<input type="submit" class="blue button js-continue-button disabled" value="<?=Text::translate("Continue", true)?>">
		</form>
	</footer>
</div>

<script>
	(function() {
		var ContinueButton = $(".js-continue-button");
		var Form = $("#files_form");
		var Processed = 0;
		var Total = 0;

		Dropzone.options.fileManagerDropzone = {
			accept: function(file, done) {
				if (file.type !== "image/png" && file.type !== "image/gif" && file.type !== "image/jpeg") {
					done("<?=Text::translate("This form only accepts png, jpg, and gif images.", true)?>");
				} else {
					done();
				}
			},
			init: function() {
				this.on("addedfile", function(ev) {
					Total++;
					$(ev.previewElement).find(".dz-details").append('<span class="button_loader"></span>');
				});

				this.on("success", function(ev, response) {
					Processed++;
					Form.append($('<input name="files[]" type="hidden">').val(response));

					$(ev.previewElement).removeClass("dz-processing").find(".button_loader").remove();

					if (Processed === Total) {
						ContinueButton.removeClass("disabled");
					}
				});

				this.on("error", function() {
					Processed++;
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
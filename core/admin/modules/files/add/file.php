<?php
	$bigtree["js"][] = "dropzone.js";
	$bigtree["breadcrumb"][] = ["link" => "#", "title" => "Add Files"];
	$permission = $admin->getResourceFolderPermission($bigtree["commands"][0]);
	$storage = new BigTreeStorage;

	if ($permission != "p") {
		$admin->stop("You do not have permission to create content in this folder.");
	}
	
	// Clean out the temp directory
	BigTree::deleteDirectory(SITE_ROOT."files/temporary/".$admin->ID."/");
?>
<div class="container file_manager_wrapper">	
	<section>
		<form action="<?=ADMIN_ROOT?>ajax/files/dropzone-upload/" class="dropzone" id="file_manager_dropzone">
			<?php $admin->drawCSRFToken(); ?>
			<input type="hidden" name="MAX_FILE_SIZE" value="<?=BigTree::uploadMaxFileSize()?>">
			<p>Drag and drop files into this zone or click to manually upload.</p>
		</form>
	</section>

	<footer>
		<a href="<?=ADMIN_ROOT?>files/process/file/<?=intval($bigtree["commands"][0])?>/" class="blue button disabled js-continue-button">Continue</a>
	</footer>
</div>

<script>
	(function() {
		var ContinueButton = $(".js-continue-button");
		var Processed = 0;
		var Total = 0;

		Dropzone.options.fileManagerDropzone = {
			accept: function(file, done) {
				if (file.name.match(<?=$storage->DisabledExtensionRegEx?>)) {
					done("This file type is disabled for security reasons.");
				} else if (file.type.indexOf("image") !== -1) {
					done("This form does not accept images.");
				} else {
					done();
				}
			},
			init: function() {
				this.on("addedfile", function(ev) {
					Total++;
					$(ev.previewElement).find(".dz-details").append('<span class="button_loader"></span>');
				});

				this.on("success", function(ev) {
					Processed++;
					
					$(ev.previewElement).removeClass("dz-processing").find(".button_loader").remove();

					if (Processed == Total) {
						ContinueButton.removeClass("disabled");
					}
				});

				this.on("error", function(ev, response) {
					$(ev.previewElement).removeClass("dz-processing").find(".button_loader").remove();
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
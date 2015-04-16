// Default Photo Gallery w/ Attribution and Link fields added
var CustomPhotoGallery = function(settings) {
	// BigTree < 4.2 style
	if (!is_object(settings)) {
		settings = { container: arguments[0], key: arguments[1], counter: arguments[2], disableCaptions: arguments[3] };
	}

	return (function($,settings) {

		var ActiveAttribution = false;
		var ActiveCaption = false;
		var ActiveLink = false;
		var Container = false;
		var Counter = 0;
		var DisableCaptions = false;
		var FileInput = false;
		var Key = false;

		function addPhoto() {
			if (!FileInput.val() || FileInput.hasClass("error")) {
				return false;
			}
			if (!DisableCaptions) {
				BigTreeDialog({
					title: "Image Caption",
					content: '<fieldset><label>Caption</label><input type="text" name="caption" /></fieldset>' +
							 '<fieldset><label>Attribution</label><input type="text" name="attribution" /></fieldset>' +
							 '<fieldset><label>Link</label><input type="text" name="link" /></fieldset>',
					callback: saveNewFile,
					icon: "caption"
				});
			} else {
				saveNewFile({ caption: "", attribution: "", link: "" });
			}
			return false;
		};
		
		function deletePhoto() {
			BigTreeDialog({
				title: "Remove Photo",
				content: '<p class="confirm">Are you sure you want to remove this photo?</p>',
				icon: "delete",
				alternateSaveText: "OK",
				callback: $.proxy(function() { $(this).parents("li").remove(); },this)
			});
			
			return false;
		};
		
		function editPhoto(ev) {
			var link = $(ev.target);
			ActiveAttribution = link.siblings(".attribution");
			ActiveCaption = link.siblings(".caption");
			ActiveLink = link.siblings(".link_field");
	
			BigTreeDialog({
				title: "Image Caption",
				content: '<fieldset><label>Caption</label><input type="text" name="caption" value="' + htmlspecialchars(ActiveCaption.val()) + '"/></fieldset>' +
						 '<fieldset><label>Attribution</label><input type="text" name="attribution" value="' + htmlspecialchars(ActiveAttribution.val()) + '"/></fieldset>' +
						 '<fieldset><label>Link</label><input type="text" name="link" value="' + htmlspecialchars(ActiveLink.val()) + '"/></fieldset>',
				callback: saveCaption,
				icon: "caption"
			});
	
			return false;
		};

		function openFileManager(ev) {
			var target = $(ev.target);
			// In case they click the span instead of the button.
			if (!target.attr("href")) {
				var field = target.parent().attr("href").substr(1);	
				var options = $.parseJSON(target.parent().attr("data-options"));
			} else {
				var field = target.attr("href").substr(1);
				var options = $.parseJSON(target.attr("data-options"));
			}
			BigTreeFileManager.formOpen("photo-gallery",field,options,useExistingFile);
			return false;
		};

		function saveCaption(data) {
			ActiveAttribution.val(data.attribution);
			ActiveAttribution = false;
			ActiveCaption.val(data.caption);
			ActiveCaption = false;
			ActiveLink.val(data.link);
			ActiveLink = false;
		};
		
		function saveNewFile(data) {
			var li = $('<li>').html('<figure></figure><a href="#" class="icon_delete"></a>');
			if (!DisableCaptions) {
				li.find("a").before('<a href="#" class="icon_edit"></a>');
			}
	
			// Try to get an image preview but fallback to the old upload message
			var img = FileInput.prev(".file_wrapper").find("img");
			if (img.length) {
				li.find("figure").append(img);
			} else {
				li.find("figure").append('<figcaption>Awaiting Upload</figcaption>');
			}
	
			// Move the hidden input into an image box for upload
			li.append(FileInput.hide());
			li.append($('<input type="hidden" name="' + Key + '[' + Counter + '][caption]" class="caption" />').val(data.caption));
			li.append($('<input type="hidden" name="' + Key + '[' + Counter + '][attribution]" class="attribution" />').val(data.attribution));
			li.append($('<input type="hidden" name="' + Key + '[' + Counter + '][link]" class="link_field" />').val(data.link));
			Container.find("ul").append(li);
	
			// Increment the photo counter
			Counter++;
			
			// Create a new hidden file input for the next image to be uploaded
			var new_file = $('<input type="file" class="custom_control photo_gallery_input" name="' + Key + '[' + Counter + '][image]">').hide();
			Container.find(".file_wrapper").after(new_file);
			
			// Wipe existing custom control information, assign the new input to it
			var customControl = FileInput.get(0).customControl;
			customControl.Container.find(".data").html("");
			new_file.get(0).customControl = customControl.connect(new_file.get(0));
			FileInput.get(0).customControl = false;
			FileInput = new_file;
		};
		
		function useExistingFile(path,caption,thumbnail) {
			var li = $('<li>').html('<figure><img src="' + thumbnail + '" alt="" /></figure><a href="#" class="icon_edit"></a><a href="#" class="icon_delete"></a>');
			li.append($('<input type="hidden" name="' + Key + '[' + Counter + '][existing]" />').val(path));
			li.append($('<input type="hidden" name="' + Key + '[' + Counter + '][caption]" class="caption" />').val(caption));
			Container.find("ul").append(li);
			Counter++;
		};

		// Init routine
		Key = settings.key;
		Container = $("#" + settings.container.replace("#",""));
		Counter = settings.count ? settings.count : 0;
		DisableCaptions = settings.disableCaptions;
		FileInput = Container.find("footer input").addClass("photo_gallery_input");
		
		Container.on("click",".icon_delete",deletePhoto)
				 .on("click",".icon_edit",editPhoto)
				 .on("imageloaded","input[type=file]",addPhoto);
		Container.find(".form_image_browser").click(openFileManager);
		Container.find("ul").sortable({ items: "li", placeholder: "ui-sortable-placeholder" });

		return { ActiveCaption: ActiveCaption, Container: Container, Counter: Counter, DisableCaptions: DisableCaptions, FileInput: FileInput, Key: Key, addPhoto: addPhoto, openFileManager: openFileManager, useExistingFile: useExistingFile };

	})(jQuery,settings);
};
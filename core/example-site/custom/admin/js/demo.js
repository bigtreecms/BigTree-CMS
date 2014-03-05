// Default Photo Gallery w/ Attribution and Link fields added
var CustomPhotoGallery = BigTreePhotoGallery.extend({
	activeAttribution: false,
	activeLink: false,
	
	addPhoto: function() {
		if (!this.fileInput.val()) {
			return false;
		}
		if (!this.disableCaptions) {
			var html = '<fieldset><label>Caption</label><input type="text" name="caption" /></fieldset>';
			html += '<fieldset><label>Attribution</label><input type="text" name="attribution" /></fieldset>';
			html += '<fieldset><label>Link</label><input type="text" name="link" /></fieldset>';
			
			new BigTreeDialog("Image Details",html,$.proxy(this.saveNewFile,this),"caption");
		} else {
			this.saveNewFile({ caption: "", attribution: "", link: "" });
		}
		return false;
	},
	
	editPhoto: function(ev) {
		link = $(ev.target);
		this.activeCaption = link.siblings(".caption");
		this.activeAttribution = link.siblings(".attribution");
		this.activeLink = link.siblings(".link");
		
		var html = '<fieldset><label>Caption</label><input type="text" name="caption" value="' + htmlspecialchars(this.activeCaption.val()) + '"/></fieldset>';
		html += '<fieldset><label>Attribution</label><input type="text" name="attribution" value="' + htmlspecialchars(this.activeAttribution.val()) + '"/></fieldset>';
		html += '<fieldset><label>Link</label><input type="text" name="link" value="' + htmlspecialchars(this.activeLink.val()) + '"/></fieldset>';
		
		new BigTreeDialog("Image Details",html,$.proxy(this.saveCaption,this),"caption");
		return false;
	},
	
	saveCaption: function(data) {
		this.activeCaption.val(data.caption);
		this.activeAttribution.val(data.attribution);
		this.activeLink.val(data.link);
		
		this.activeCaption = false;
		this.activeAttribution = false;
		this.activeLink = false;
	},
	
	saveNewFile: function(data) {
		if (this.disableCaptions) {
			li = $('<li>').html('<figure><figcaption>Awaiting Uploading</figcaption></figure><a href="#" class="icon_delete"></a>');
		} else {
			li = $('<li>').html('<figure><figcaption>Awaiting Uploading</figcaption></figure><a href="#" class="icon_edit"></a><a href="#" class="icon_delete"></a>');
		}
		li.append(this.fileInput.hide());
		li.append($('<input type="hidden" name="' + this.key + '[' + this.counter + '][caption]" class="caption" />').val(data.caption));
		li.append($('<input type="hidden" name="' + this.key + '[' + this.counter + '][attribution]" class="attribution" />').val(data.attribution));
		li.append($('<input type="hidden" name="' + this.key + '[' + this.counter + '][link]" class="link" />').val(data.link));
		this.container.find("ul").append(li);

		this.counter++;
		c = this.counter;
		
		new_file = $('<input type="file" class="custom_control" name="' + this.key + '[' + this.counter + '][image]">').hide();
		this.container.find(".file_wrapper").append(new_file);
		customControl = this.fileInput.get(0).customControl;
		new_file.get(0).customControl = customControl.connect(new_file.get(0));
		this.fileInput.get(0).customControl = false;
		this.fileInput = new_file;
	},
});
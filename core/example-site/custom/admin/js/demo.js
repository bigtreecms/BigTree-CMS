// !BigTree Photo Gallery Class
var BigTreePhotoGallery = Class.extend({
	container: false,
	counter: false,
	dragging: false,
	key: false,
	fileInput: false,
	activeCaption: false,
	activeAttribution: false,
	activeLink: false,
	disableCaptions: false,
	
	init: function(container,key,counter,disable_captions) {
		this.key = key;
		this.container = $("#" + container);
		this.counter = counter;
		this.disableCaptions = disable_captions;
		this.fileInput = this.container.find("footer input");
		
		this.container.find("ul").sortable({ items: "li", placeholder: "ui-sortable-placeholder" });
		this.container.on("click",".icon_delete",this.deletePhoto);
		this.container.on("click",".icon_edit",$.proxy(this.editPhoto,this));
		this.container.on("change","input[type=file]",$.proxy(this.addPhoto,this));
		this.container.find(".form_image_browser").click($.proxy(this.openFileManager,this));
	},
	
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
	
	deletePhoto: function() {
		new BigTreeDialog("Remove Photo",'<p class="confirm">Are you sure you want to remove this photo?</p>',$.proxy(function() {
			$(this).parents("li").remove();
		},this),"delete",false,"OK");
		
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
	
	openFileManager: function(ev) {
		target = $(ev.target);
		// In case they click the span instead of the button.
		if (!target.attr("href")) {
			field = target.parent().attr("href").substr(1);	
			options = eval('(' + target.parent().attr("data-options") + ')');
		} else {
			field = target.attr("href").substr(1);
			options = eval('(' + target.attr("data-options") + ')');
		}
		BigTreeFileManager.formOpen("photo-gallery",field,options,$.proxy(this.useExistingFile,this));
		return false;
	},
	
	useExistingFile: function(path,caption,thumbnail) {
		li = $('<li>').html('<figure><img src="' + thumbnail + '" alt="" /></figure><a href="#" class="icon_edit"></a><a href="#" class="icon_delete"></a>');
		li.find("img").load(function() {
			w = $(this).width();
			h = $(this).height();
			if (w > h) {
				perc = 75 / w;
				h = perc * h;
				style = { margin: Math.floor((75 - h) / 2) + "px 0 0 0" };
			} else {
				perc = 75 / h;
				w = perc * w;
				style = { margin: "0 0 0 " + Math.floor((75 - w) / 2) + "px" };
			}
			
			$(this).css(style);
		});
		li.append($('<input type="hidden" name="' + this.key + '[' + this.counter + '][existing]" />').val(path));
		li.append($('<input type="hidden" name="' + this.key + '[' + this.counter + '][caption]" class="caption" />').val(caption));
		this.container.find("ul").append(li);

		this.counter++;
		c = this.counter;
	}
});
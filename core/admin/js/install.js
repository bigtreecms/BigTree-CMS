$(document).ready(function() {
	BigTreeCustomControls();
	$("#loadbalanced").on("checked:click", function() {
		toggleWriteDatabase($(this));
	});
});

function toggleWriteDatabase(target) {
	if (target.is(':checked')) {
		$("#loadbalanced_settings").css({ display: "block" });
	} else {
		$("#loadbalanced_settings").css({ display: "none" });
	}
}

function BigTreeCustomControls() {
	// Setup custom checkboxes
	$("input[type=checkbox]").each(function() {
		if (!$(this).hasClass("custom_control")) {
			this.customControl = new BigTreeCheckbox(this);
			$(this).addClass("custom_control");
		}
	});
}

// !BigTreeCheckbox Class
var BigTreeCheckbox = Class.extend({

	Element: false,
	Link: false,

	init: function(element,text) {
		this.Element = $(element);
		
		label = this.Element.next("label");
		div = $("<div>").addClass("checkbox");
		a = $("<a>").attr("href","#checkbox");
		a.click($.proxy(this.click,this));
		label.click($.proxy(this.click,this));
		a.focus($.proxy(this.focus,this));
		a.blur($.proxy(this.blur,this));
		a.keydown($.proxy(this.keydown,this));
		
		if (element.checked) {
			a.addClass("checked");
		}
		
		if (element.tabIndex) {
			a.attr("tabindex",element.tabIndex);
		}
		
		this.Link = a;
		
		div.append(a);
		$(element).hide().after(div);
	},
	
	focus: function() {
		this.Link.addClass("focused");
	},
	
	blur: function() {
		this.Link.removeClass("focused");
	},
	
	keydown: function(event) {
		if (event.keyCode == 32) {
			$.proxy(this.click,this)();
			return false;
		}
	},

	click: function() {
		if (this.Link.hasClass("checked")) {
			this.Link.removeClass("checked");
			$(this.Element).attr("checked",false);
		} else {
			this.Link.addClass("checked");
			$(this.Element).attr("checked","");
		}
		this.Element.trigger("checked:click");
		return false;
	}
});
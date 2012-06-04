$(document).ready(function() {
	BigTreeCustomControls();
	$("#loadbalanced").on("click", function() {
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
	
	// Setup custom select boxes
	$("select").each(function() {
		if (!$(this).hasClass("custom_control")) {
			this.customControl = new BigTreeSelect(this);
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
		
		if (element.disabled) {
			a.addClass("disabled");
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
		if (!this.Element.attr("disabled")) {
			if (this.Link.hasClass("checked")) {
				this.Link.removeClass("checked");
			} else {
				this.Link.addClass("checked");
			}
			this.Element.trigger("click");
		}
		return false;
	}
});

// !BigTreeSelect Class
var BigTreeSelect = Class.extend({

	Element: false,
	Container: false,
	Open: false,
	Options: [],
	BoundWindowClick: false,
	WasRelative: false,
	KeyDownBind: false,
	
	init: function(element) {
		this.Element = $(element);
		
		$(element).css({ position: "absolute", left: "-1000000px" });
		div = $("<div>").addClass("select");
		tester = $("<div>").css({ position: "absolute", top: "-1000px", left: "-1000px", "font-size": "16px", "white-space": "nowrap" });
		$("body").append(tester);
		maxwidth = 0;
		
		html = "";
		selected = "";
		for (i = 0; i < element.options.length; i++) {
			op = element.options[i];
			this.Options[i] = op;
			
			// Get the size of this text.
			tester.html(op.text);
			width = tester.width();
			if (width > maxwidth) {
				maxwidth = width;
			}
			
			if (i == 0) {
				selected = op.text;
				html += '<span>' + op.text + '</span><datalist style="display: none;">';
			}
			html += '<data value="' + op.value + '">' + op.text + '</data>';
			
			if (op.selected) {
				selected = op.text;
			}
		}
		html += '</datalist>';
		div.html(html);
		
		spanwidth = maxwidth;
		// If we're in a section cell we may need to be smaller.
		if ($(element).parent().get(0).tagName.toLowerCase() == "section") {
			sectionwidth = $(element).parent().width();
			if (sectionwidth < (maxwidth + 56)) {
				spanwidth = sectionwidth - 56;
			}
		}
		
		div.find("span").css({ width: spanwidth + "px", height: "30px" }).html(selected).click($.proxy(this.click,this));
		div.find("datalist").css({ width: (maxwidth + 54) + "px" });
		div.find("data").click($.proxy(this.select,this));
		
		$(element).after(div);
		
		this.Container = div;
		
		// Observe focus on the select that's been hidden.
		this.Element.focus($.proxy(this.focus,this));
		this.Element.blur($.proxy(this.blur,this));
	},
	
	focus: function() {
		this.Container.addClass("focused");
		this.KeyBindDown = $.proxy(this.keydown,this);
		this.Element.keydown(this.KeyBindDown);
	},
	
	blur: function() {
		this.Container.removeClass("focused");
		this.Element.unbind("keydown");
	},
	
	keydown: function(ev) {
		// The original select element that's hidden off screen.
		el = this.Element.get(0);
		
		// If a modifier has been pressed, ignore this.
		if (ev.ctrlKey || ev.altKey || ev.metaKey) {
			return true;
		}
		
		// Get the original index and save it so we know when it changes.
		index = el.selectedIndex;
		oindex = index;
		
		// Up arrow pressed
		if (ev.keyCode == 38) {
			index--;
			if (index < 0) {
				index = 0;
			}
		// Down arrow pressed
		} else if (ev.keyCode == 40) {
			index++;
			if (index == el.options.length) {
				index--;
			}
		// A letter key was pressed
		} else if (ev.keyCode > 64 && ev.keyCode < 91) {
			spot = ev.keyCode - 65;
			letters = "abcdefghijklmnopqrstuvwxyz";
			letter = letters[spot];
			
			// Go through all the options in the select to see if any of them start with the letter that was pressed.
			for (i = index + 1; i < el.options.length; i++) {
				text = el.options[i].text;
				first_letter = text[0].toLowerCase();
				if (first_letter == letter) {
					index = i;
					break;
				}
			}
			
			// If we were already on that letter, find the next one with that same letter.
			if (index == oindex) {
				for (i = 0; i < oindex; i++) {
					text = el.options[i].text;
					first_letter = text[0].toLowerCase();
					if (first_letter == letter) {
						index = i;
						break;
					}
				}
			}
		}
		
		// We found a new element, fire an event saying the select changed and update the description in the styled dropdown.
		if (index != oindex) {
			// For some reason Firefox doesn't care that we stop the event and still changes the index of the hidden select area, so we're not going to update it if we're in Firefox.
			if (navigator.userAgent.indexOf("Firefox") == -1) {
				el.selectedIndex = index;
			}
			this.Container.find("span").html(el.options[index].text);
			this.Element.trigger("change", { value: el.options[index].value, text: el.options[index].text });
			return false;
		}
		
		// Stop the event if it's not a tab.
		if (ev.keyCode != 9) {
			return false;
		}
	},
	
	click: function() {
		this.Element.focus();
		
		// Check if we're in a sortable row and disable it's relative position if so.
		li = this.Element.parent("li");
		if (li.length) {
			if (li.css("position") == "relative") {
				li.css("position","");
				this.WasRelative = true;
			}
		}
		
		if (!this.Open) {
			this.Open = true;
			this.Container.find("datalist").show();
			this.Container.addClass("open");
			this.BoundWindowClick = $.proxy(this.close,this);
			$("body").click(this.BoundWindowClick);
		} else {
			this.Open = false;
			this.Container.removeClass("open");
			this.Container.find("datalist").hide();
			$("body").unbind("click",this.BoundWindowClick);
		}

		return false;
	},
	
	close: function() {
		this.Open = false;
		this.Container.removeClass("open");
		this.Container.find("datalist").hide();
		$("body").unbind("click",this.BoundWindowClick);
		
		// Reset relative position if applicable
		if (this.WasRelative) {
			this.Element.parent("li").css("position", "relative");
			this.WasRelative = false;
		}
		
		return false;
	},
	
	select: function(event) {
		el = event.target;
		this.Element.val(el.getAttribute("value"));
		this.Container.find("span").html(el.innerHTML);
		$("body").unbind("click",this.BoundWindowClick);
		this.close();
		this.Element.trigger("changed", { value: el.getAttribute("value"), text: el.innerHTML });
		this.Element.trigger("change", { value: el.getAttribute("value"), text: el.innerHTML });
	}
});
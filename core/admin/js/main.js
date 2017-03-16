$(document).ready(function() {
	BigTreeCustomControls();
	BigTreePageLoadHooks.init();

	// Set Busy signals for AJAX
	$(document).ajaxSend(function() {
		BigTree.Busy = true;
	}).ajaxStop(function() {
		BigTree.Busy = false;
	});
	
	// BigTree Quick Search
	$('nav.main form .qs_query').keyup(function(ev) {
		var v = $(this).val();
		if (v && ev.keyCode != 9) { //no tabs!
			$("#quick_search_results").load("admin_root/ajax/quick-search-results/", { query: v }, function() {
				$("#quick_search_results").show();
			});
		} else {
			$("#quick_search_results").hide().html("");
		}
	}).focus(function() {
		$(this).addClass("focus");
	}).blur(function() {
		setTimeout("$('nav.main form input[type=\"search\"]').removeClass(\"focus\").val(\"\"); $(\"#quick_search_results\").fadeOut(200, function() { $(this).html(\"\"); });", 300);
	});
	$("nav.main").on("click",".advanced_search",function() {
		$("#quick_search_results").parents().submit();
		return false;
	});

	// Subnav extras menu
	$("#sub_nav menu").hover(function() {
		$(this).parent().addClass("dropdown_open");
	},function() {
		$(this).parent().removeClass("dropdown_open");
	});

	// Growl Hooks
	$("#growl").on("click",".close",function() {
		$(this).parents("article").remove();

		return false;
	});

	// Fire ready
	BigTree.ready();
});

function BigTreeCustomControls(selector) {
	var parent = (typeof selector == "undefined") ? $("body") : $(selector);

	parent.find("input[type=checkbox]").each(function() {
		if (!$(this).hasClass("custom_control") && $(this).css("display") != "none") {
			this.customControl = BigTreeCheckbox(this);
		}
	});
	parent.find("select:not([multiple])").each(function() {
		if (!$(this).hasClass("custom_control") && $(this).css("display") != "none") {
			this.customControl = BigTreeSelect(this);
		}
	});
	parent.find("input[type=file]").each(function() {
		if (!$(this).hasClass("custom_control") && $(this).css("display") != "none") {
			this.customControl = BigTreeFileInput(this);
		}
	});
	parent.find("input[type=radio]").each(function() {
		if (!$(this).hasClass("custom_control") && $(this).css("display") != "none") {
			this.customControl = BigTreeRadioButton(this);
		}
	});
}

var BigTreePageLoadHooks = (function($) {

	var StickyControls = { element: false, stuck: false, top: false };

	function init() {

		// Link Finder
		$("#link_finder").keyup(function() {
			var q = $(this).val();
			if (q == "") {
				$("#link_finder_results").hide().html("");
			} else {
				$("#link_finder_results").load("admin_root/ajax/link-finder/", { query: q }, function() {
					$("#link_finder_results").show().children("a").click(function() { return false; });
				});
			}
		});
		
		// Sticky Controls
		StickyControls.element = $(".sticky_controls");
		if (StickyControls.element.length) {
			StickyControls.top = StickyControls.element.offset().top;
			
			if (window.scrollY >= StickyControls.top && !StickyControls.stuck) {
				StickyControls.stuck = true;
				StickyControls.element.addClass("stuck");
			}
			
			$(window).scroll(function() {
				if (window.scrollY >= StickyControls.top && !StickyControls.stuck) {
					StickyControls.stuck = true;
					StickyControls.element.addClass("stuck");
				}
				if (window.scrollY < StickyControls.top && StickyControls.stuck) {
					StickyControls.stuck = false;
					StickyControls.element.removeClass("stuck");
				}
			});
		}
	
		// Property Block Hide/Show
		$("h3.properties").click(function() {
			if ($(this).find(".icon_small").hasClass("icon_small_caret_right")) {
				// Set a cookie to keep it open next time.
				$.cookie("bigtree_admin[page_properties_open]","on", { expires: 365, path: "/" });
			} else {
				$.cookie("bigtree_admin[page_properties_open]","", { path: "/" });
			}
			$(this).find(".icon_small").toggleClass("icon_small_caret_right").toggleClass("icon_small_caret_down");
			$(".property_block").toggle().next().toggle();
			return false;
		});
	
		$(".inset_block .hide").click(function() {
			var id = $(this).attr("data-id");
			$.cookie("bigtree_admin[ignore_view_description][" + id + "]","on", { expires: 365, path: "/" });
			$(this).parent().hide();
		});
		
		// Tooltips
		$(".has_tooltip").each(function() {
			var width = BigTree.windowWidth();
			var offset = $(this).offset();
			if (offset.left > (width / 2)) {
				var position = "left";
			} else {
				var position = "right";
			}
			BigTreeToolTip({
				selector: $(this),
				content: $(this).attr("data-tooltip"),
				position: position
			});
		});
	
		BigTree.formHooks(".container form");
	}

	return { init: init }
}(jQuery));

var BigTreePasswordInput = function(element) {
	return (function($,element) {

		var Buffer;
		var Element = $(element);
		var FakeElement;

		function blur() {
			Buffer = FakeElement.val();
			FakeElement.val(str_repeat("•",FakeElement.val().length));
		}

		function change() {
			Element.val(FakeElement.val());
		}

		function disable() {
			Element.prop("disabled", true);
			FakeElement.prop("disabled", true);
		}

		function enable() {
			Element.prop("disabled", false);
			FakeElement.prop("disabled", false);
		}

		function focus() {
			FakeElement.val(Buffer);
		}

		// Init routine
		if (Element.hasClass("custom_control")) {
			return false;
		}

		FakeElement = $('<input type="text" />').attr("tabindex",$(element).attr("tabindex")).attr("data-tooltip",$(element).attr("data-tooltip"));
		FakeElement.on("blur",blur).focus(focus).change(change);
		FakeElement.get(0).className = Element.get(0).className;
		Element.addClass("custom_control").removeClass("required").hide().after(FakeElement);

		return { Element: Element, FakeElement: FakeElement, disable: disable, enable: enable };

	})(jQuery,element);
};

var BigTreeCheckbox = function(element) {
	return (function($,element) {

		var Element = $(element);
		var Link = false;

		function blur() {
			Link.removeClass("focused");
		}

		function clear() {
			Element.prop("checked",false);
			Link.removeClass("checked");
		}

		function click() {
			if (!Element.prop("disabled")) {
				if (Link.hasClass("checked")) {
					Link.removeClass("checked");
					Element.prop("checked",false);
				} else {
					Link.addClass("checked");
					Element.prop("checked",true);
				}
				Element.triggerHandler("click");
				Element.triggerHandler("change");
			}
			return false;
		}
	
		function disable() {
			Link.addClass("disabled");
			Element.prop("disabled",true);
		}
		
		function enable() {
			Link.removeClass("disabled");
			Element.prop("disabled",false);
		}

		function focus() {
			if (!Element.prop("disabled")) {
				Link.addClass("focused");
			}
		}
		
		function keydown(event) {
			if (event.keyCode == 32) {
				click();
				return false;
			}
		}

		// Init routine
		if (Element.hasClass("custom_control")) {
			return false;
		}

		// Have label clicks affect the checkbox but let links inside of the labels still work properly
		Element.addClass("custom_control")
			   .next("label").click(click)
			   .find("a").click(function(ev) { ev.stopPropagation(); });
		
		// Create our clickable fake checkbox
		Link = $("<a>").attr("href","#checkbox").click(click).focus(focus).blur(blur).keydown(keydown);

		if (element.checked) {
			Link.addClass("checked");
		}
		
		if (element.disabled) {
			Link.addClass("disabled")
				.attr("tabindex","-1");
		} else if (element.tabIndex) {
			Link.attr("tabindex",element.tabIndex);
		}
		
		$(element).hide().after($('<div class="checkbox">').append(Link));

		return { Element: Element, Link: Link, blur: blur, clear: clear, click: click, disable: disable, enable: enable, focus: focus };

	})(jQuery,element);
};

var BigTreeSelect = function(element) {
	return (function($,element) {

		var Container = $("<div>").addClass("select");
		var Element = $(element);
		var Open = false;
		var Options = [];
		var WasRelative = false;

		function add(value,text) {
			// Add to the actual select.
			Element.get(0).options[Element.get(0).options.length] = new Option(text,value);
			// Add to the styled select.
			var a = $('<a href="#">' + text + '</a>').attr("data-value",value);
			Container.find(".select_options").append(a);
	
			// Test the size of this new element and see if we need to increase the width.
			var tester = $("<div>").css({ position: "absolute", top: "-1000px", left: "-1000px", "font-size": "11px", "font-family": "Helvetica", "white-space": "nowrap" });
			$("body").append(tester);
			tester.html(text);
			var width = tester.width();
			
			var span = Container.find("span");
	
			// If we're in a section cell we may need to be smaller.
			if (Element.parent().get(0).tagName.toLowerCase() == "section") {
				var sectionwidth = Element.parent().width();
				if (sectionwidth < (width + 56)) {
					width = sectionwidth - 80;
					span.css({ overflow: "hidden", padding: "0 0 0 10px" });
				}
			}
	
			if (width > span.width()) {
				span.css({ width: (width + 10) + "px" });
				Container.find(".select_options").css({ width: (width + 64) + "px" });
			}
	
			tester.remove();
		}
		
		function blur() {
			Container.removeClass("focused");
		}
	
		function click() {
			if (Container.hasClass("disabled")) {
				return false;
			}
	
			if (!Open) {
				// Tooltips and menus sometimes show over the dropdown when using TinyMCE 4
				try {
					tinyMCE.ui.FloatPanel.hideAll();
				} catch (err) {}
			
				$("select").not(Element).trigger("closeNow");
				Element.focus();
				
				// Check if we're in a sortable row and disable it's relative position if so.
				var li = Element.parent("li");
				if (li.length) {
					if (li.css("position") == "relative") {
						li.css("position","");
						WasRelative = true;
					}
				}
				
				var select_options = Container.find(".select_options").show();
				Open = true;
				Container.addClass("open");
				$("body").click(close);
				
				// Find out if we're in a dialog and have an overflow
				var overflow = Container.parents(".overflow");
				if (overflow.length) {
					if (Container.parents(".callout_fields, #matrix_resources").length) {
						// WebKit needs fixin.
						if ($.browser.webkit) {
							select_options.css("marginTop",-1 * $(".callout_fields, #matrix_resources").last().scrollTop() + "px");
						}
						// When someone scrolls the overflow, close the select or the dropdown will detach.
						setTimeout(function() { $(".callout_fields, #matrix_resources").last().scroll(close); },500);
					} else {
						// WebKit needs fixin.
						if ($.browser.webkit) {
							select_options.css("marginTop",-1 * overflow.scrollTop() + "px");
						}
						// When someone scrolls the overflow, close the select or the dropdown will detach.
						setTimeout(function() { overflow.scroll(close); },500);
					}

					// If the options would hang off the window, shrink the options menu down
					var offset = select_options.offset().top - window.scrollY + select_options.height();
					var difference = offset - BigTree.windowHeight();
					if (difference > 0) {
						select_options.css({ height: select_options.height() - difference - 5 + "px" });
					}
				} else {
					// If the select drops below the visible area, scroll down a bit.
					var toScroll = (select_options.offset().top + select_options.height()) - window.scrollY - $(window).height();
					if (toScroll > 0) {
						$('html, body').animate({ scrollTop: window.scrollY + toScroll + 5 }, 200);
					}
				}
			} else {
				close();
			}
	
			return false;
		}

		function close() {
			Open = false;
			Container.removeClass("open").find(".select_options").hide();
			// Remove events for closing the dropdown
			$("body").unbind("click",close);
			$(".callout_fields").unbind("scroll",close);
			Container.parents(".overflow").unbind("scroll",close);
			
			// Reset relative position if applicable
			if (WasRelative) {
				Element.parent("li").css("position", "relative");
				WasRelative = false;
			}
			
			return false;
		}
	
		function disable() {
			Element.prop("disabled",true);
			Container.addClass("disabled");
		}
	
		function enable() {
			Element.prop("disabled",false);
			Container.removeClass("disabled");
		}
	
		function focus() {
			Container.addClass("focused");
		}
		
		function keydown(ev) {
			// If a modifier has been pressed, ignore this.
			if (ev.ctrlKey || ev.altKey || ev.metaKey) {
				return true;
			}
	
			if (ev.keyCode == 13 && Open) {
				close();
				return false;
			}
	
			// The original select element that's hidden off screen.
			var el = Element.get(0);
			
			// Get the original index and save it so we know when it changes.
			var index = el.selectedIndex;
			var originalIndex = index;
			
			// Up or left arrow pressed
			if (ev.keyCode == 38 || ev.keyCode == 37) {
				index--;
				// Make sure we're not on a disabled option
				while (index < 0 || el.options[index].disabled) {
					index--;
					if (index < 0) {
						index = originalIndex;
					}
				}
			// Down or right arrow pressed
			} else if (ev.keyCode == 40 || ev.keyCode == 39) {
				index++;
				// Make sure we're not on a disabled option
				while (index == el.options.length || el.options[index].disabled) {
					index++;
					if (index >= el.options.length) {
						index = originalIndex;
					}
				}
			// A letter key was pressed
			} else if (ev.keyCode > 64 && ev.keyCode < 91) {
				var spot = ev.keyCode - 65;
				var letters = "abcdefghijklmnopqrstuvwxyz";
				var letter = letters[spot];
				
				// Go through all the options in the select to see if any of them start with the letter that was pressed.
				for (var i = index + 1; i < el.options.length; i++) {
					if (!el.options[i].disabled) {
						var text = el.options[i].text;
						if (text) {
							var first_letter = text[0].toLowerCase();
							if (first_letter == letter) {
								index = i;
								break;
							}
						}
					}
				}
				
				// If we were already on that letter, find the next one with that same letter.
				if (index == originalIndex) {
					for (var i = 0; i < originalIndex; i++) {
						if (!el.options[i].disabled) {
							var text = el.options[i].text;
							if (text) {
								var first_letter = text[0].toLowerCase();
								if (first_letter == letter) {
									index = i;
									break;
								}
							}
						}
					}
				}
			}

			// We found a new element, fire an event saying the select changed and update the description in the styled dropdown.
			if (index != originalIndex) {
				// Update the new selected option
				var select_options_container = Container.find(".select_options");
				var ops = select_options_container.find("a");
				ops.eq(originalIndex).removeClass("active");
				ops.eq(index).addClass("active");
	
				// Find out if we can see this option
				var selected_y = (index + 1) * 25;
				if (selected_y >= select_options_container.height() + select_options_container.scrollTop()) {
					select_options_container.animate({ scrollTop: selected_y - select_options_container.height() + "px" }, 250);
				} else if (selected_y <= select_options_container.scrollTop()) {
					select_options_container.animate({ scrollTop: selected_y - 25 + "px" }, 250);
				}
		
				// Firefox wants to handle this change itself, so we'll give it a shot until they fix their browser engine.
				if ($.browser.mozilla && ev.keyCode > 36 && ev.keyCode < 41) {
					// Fire delayed change event since Firefox doesn't cooperate
					setTimeout(function() {
						Element.trigger("change", { value: el.options[index].value, text: el.options[index].text });
					},200);
				} else {
					el.selectedIndex = index;
					Element.trigger("change", { value: el.options[index].value, text: el.options[index].text });
				}
	
				Container.find("span").html('<figure class="handle"></figure>' + el.options[index].text);
				
				return false;
			}
			
			// Stop the event if it's not a tab.
			if (ev.keyCode != 9) {
				return false;
			}
		}
	
		function remove(value) {
			// Remove it from the actual select.
			var ops = Element.find("option");
			for (var i = 0; i < ops.length; i++) {
				if (ops.eq(i).val() == value) {
					ops.eq(i).remove();
				}
			}
			// Remove it from the styled one.
			var as = Container.find(".select_options a");
			for (var i = 0; i < as.length; i++) {
				if (as.eq(i).attr("data-value") == value) {
					var text_was = as.eq(i).html();
					as.eq(i).remove();
				}
			}
			// If the current selected state is the value we're removing, switch to the first available.
			var sel = Container.find("span").eq(0);
			var select_options = Container.find(".select_options a");
			if (select_options.length > 0) {
				if (sel.html() == '<figure class="handle"></figure>' + text_was) {
					sel.html('<figure class="handle"></figure>' + select_options.eq(0).html());
				}
			} else {
				sel.html('<figure class="handle"></figure>');
			}
		}
		
		function select(ev) {
			ev.preventDefault();
			var option = $(this);

			// Disabled options aren't clickable
			if (option.hasClass("disabled")) {
				ev.stopPropagation();
				return;
			}

			// Set the <select> to the new value
			Element.val(option.attr("data-value"));

			// Update the selected state of the custom dropdown
			Container.find("span").html('<figure class="handle"></figure>' + option.html());
			Container.find("a").removeClass("active");
			option.addClass("active");
			
			// Close the dropdown
			close();
			
			// Tell the <select> it has changed.
			Element.trigger("change", { value: option.attr("data-value"), text: option.innerHTML });
		}

		function update() {
			var el = Element.get(0);
			Container.find("span").html('<figure class="handle"></figure>' + el.options[el.selectedIndex].text);
			Container.find("a").removeClass("active").eq(el.selectedIndex).addClass("active");
		}

		// Init routine
		if (Element.hasClass("custom_control")) {
			return false;
		}
		Element.addClass("custom_control");
		
		// WebKit likes to freak out when we focus a position: absolute <select> in an overflow: scroll area
		if ($.browser.webkit) {
			Element.css({ position: "relative", left: "-1000000px", float: "left", width: "1px", marginRight: "-1px" });
		} else {
			Element.css({ position: "absolute", left: "-1000000px" });
		}

		var tester = $("<div>").css({ position: "absolute", top: "-1000px", left: "-1000px", "font-size": "11px", "font-family": "Helvetica", "white-space": "nowrap" });
		$("body").append(tester);
		var maxwidth = 0;
		
		var html = "";
		var selected = "";
		var selected_option = "";
		
		// Need to find all children since we have to account for options in and out of optgroups
		var first_level = Element.children();
		var y = 0;
		for (var i = 0; i < first_level.length; i++) {
			var el = first_level.get(i);
			if (el.nodeName.toLowerCase() == "optgroup") {
				var l = $(el).attr("label");
				Options.push($('<div class="group">' + l + '</div>'));
				// Get the size of this text.
				tester.html(l);
				var width = tester.width();
				if (width > maxwidth) {
					maxwidth = width;
				}
				
				var options = $(el).find("option");
				for (x = 0; x < options.length; x++) {
					y++;
					var option = options.eq(x);
					var text = option.html();
					var val = option.attr("value");
					if (!val) {
						val = text;
					}
					
					// Get the size of this text.
					tester.html(text);
					width = tester.width();
					if (width + 20 > maxwidth) {
						maxwidth = width + 20;
					}
					
					if (y == 1) {
						selected_option = text;
					}
					
					if (option.prop("selected")) {
						Options.push($('<a class="optgroup active" href="#" data-value="' + val + '">' + text + '</a>'));
						selected_option = text;
					} else if (option.prop("disabled")) {
						Options.push($('<a class="optgroup disabled" href="#" data-value="' + val + '">' + text + '</a>'));
					} else {
						Options.push($('<a class="optgroup" href="#" data-value="' + val + '">' + text + '</a>'));
					}
				}
			} else {
				y++;
				var option = $(el);
				var text = option.html();
				var val = option.attr("value");
				if (!val) {
					val = text;
				}
				
				// If we're making a tree-like dropdown
				if (option.attr("data-depth")) {
					var depth = parseInt(option.attr("data-depth")) * 10;
				} else {
					var depth = 0;
				}

				// Get the size of this text.
				tester.html(text);
				var width = tester.width() + depth;
				if (width > maxwidth) {
					maxwidth = width;
				}
				
				if (y == 1) {
					selected_option = text;
				}
				
				if (option.prop("selected")) {
					Options.push($('<a style="border-left: ' + depth + 'px solid #CCC;" class="active" href="#" data-value="' + val + '">' + text + '</a>'));
					selected_option = text;
				} else if (option.prop("disabled")) {
					Options.push($('<a style="border-left: ' + depth + 'px solid #CCC;" class="disabled" href="#" data-value="' + val + '">' + text + '</a>'));
				} else {
					Options.push($('<a style="border-left: ' + depth + 'px solid #CCC;" href="#" data-value="' + val + '">' + text + '</a>'));
				}
			}
		}
		
		Container.html('<span><figure class="handle"></figure>' + selected_option + '</span><div class="select_options" style="display: none;"></div>');

		var spanwidth = maxwidth;
		// If we're in a section cell we may need to be smaller.
		if (Element.parent().get(0).tagName.toLowerCase() == "section") {
			var sectionwidth = $(element).parent().width();
			if (sectionwidth < (maxwidth + 56)) {
				spanwidth = sectionwidth - 80;
				maxwidth = spanwidth - 44;
				Container.find("span").css({ overflow: "hidden", padding: "0 0 0 10px" });
			}
		}
		
		Container.find("span").css({ width: (spanwidth + 10) + "px", height: "30px" }).html('<figure class="handle"></figure>' + selected_option).click(click);
		Container.find(".select_options").append(Options).css({ width: (maxwidth + 64) + "px" });
		Container.on("click","a",select);
		Container.find(".handle").click(click);
		
		// Add it to the DOM
		Element.before(Container);		

		// See if this select is disabled
		if (Element.prop("disabled")) {
			Container.addClass("disabled");
		}
		
		// Observe focus, blur, and keydown on the hidden element.
		Element.focus(focus).blur(blur).keydown(keydown);
		// Custom event to force open lists closed when another select opens.
		Element.on("closeNow",close);

		// Cleanup
		tester.remove();

		return { Container: Container, Element: Element, Options: Options, add: add, blur: blur, click: click, close: close, disable: disable, enable: enable, focus: focus, remove: remove, update: update };

	})(jQuery,element);
};

var BigTreeFileInput = function(element) {
	return (function($,element) {

		var Container =  $("<div>").addClass("file_wrapper").html('<span class="handle">Upload</span><span class="data"></span>');
		var Element = $(element);

		function checkUploads() {
			// Max file size
			var max_size = parseInt($("#bigtree_max_file_size").val());
	
			// No content or early browser fallback? Just draw the input's value
			if (!Element.get(0).files.length) {
				Container.find(".data").html('<span class="name wider">' + Element.get(0).value + '	</span>');
			} else {
				// If this input allows for multiple uploads we're not going to handle it directly, watch its change event yourself
				if (Element.attr("multiple") && Element.get(0).files.length > 1) {
					Container.find(".data").html('<span class="name">' + Element.get(0).files.length + ' Files</span>');
				// Single upload? Show the thumbnail and file name / size
				} else {
					// Get file reference
					var file = Element.get(0).files[0];
	
					// See if the file is too big
					if (max_size && max_size < file.size) {
						// Clear it out
						Container.find(".data").html('<span class="size">' + formatBytes(file.size) + '</span><span class="name error wider">File Too Large (Max ' + formatBytes(max_size) + ')</span>');
						Element.val("");
					// File size is ok
					} else {
						Container.find(".data").html('<span class="size">' + formatBytes(file.size) + '</span><span class="name">' + file.name + '</span>');
						// If this is an image, draw a thumbnail
						if (file.type == "image/jpeg" || file.type == "image/png" || file.type == "image/gif") {
							var img = document.createElement("img");
							var reader = new FileReader();
							reader.onload = function(e) {
								img.src = reader.result;
								img.onload = function() {
									var min_height = parseInt(Element.attr("data-min-height"));
									var min_width = parseInt(Element.attr("data-min-width"));
									var fieldset = Container.parents("fieldset");

									// Clear any existing errors
									fieldset.removeClass("form_error").find(".image_field_error").remove();
									Element.removeClass("error");

									// Minimum dimensions not met? Show an error message
									if (img.height < min_height || img.width < min_width) {
										var div = $('<div class="image_field_error">').html('The chosen image does not meet the minimum dimensions of ' + min_width + 'x' + min_height + '.');
										fieldset.addClass("form_error");

										var currently = fieldset.find(".currently");
										if (currently.length) {
											currently.before(div);
										} else {
											fieldset.find(".image_field").after(div);
										}
										Element.addClass("error");
									} else {
										Element.trigger("imageloaded");
									}

									// Add image preview
									Container.find(".data").prepend(img);
								};
							};
							reader.readAsDataURL(file);
						// Not an image? Give more room for the file name
						} else {
							Container.find(".name").addClass("wider");
						}
					}
				}
			}
		}

		function clear() {
			Element.val("");
			checkUploads();
		}
	
		function connect(el) {
			Element = $(el).on("change",checkUploads);
			return { Container: Container, Element: Element, clear: clear, connect: connect };
		}

		function disable() {
			Element.prop("disabled", true);
			Container.addClass("disabled");
		}

		function enable() {
			Element.prop("disabled", false);
			Container.removeClass("disabled");
		}
	
		// Courtesy of Aliceljm on StackOverflow
		function formatBytes(bytes) {
			var sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
			var i = Math.floor(Math.log(bytes) / Math.log(1000));
			return (bytes / Math.pow(1000, i)).toPrecision(3) + sizes[i];
		}

		// Init routine
		if (Element.hasClass("custom_control")) {
			return false;
		}

		Container.find(".handle").click(function() { Element.click(); });
		Element.addClass("custom_control").hide().on("change",checkUploads).before(Container);

		if (Element.prop("disabled")) {
			Container.addClass("disabled");
		}
		
		return { Container: Container, Element: Element, clear: clear, connect: connect, disable: disable, enable: enable };

	})(jQuery,element);
};

var BigTreeRadioButton = function(element) {
	return (function($,element) {

		var Element = $(element);
		var Link = false;

		function blur() {
			Link.removeClass("focused");
		}

		function clear(ev) {
			$('input[name="' + Element.attr("name") + '"]').each(function() {
				this.customControl.Link.removeClass("checked");
				$(this).prop("checked",true);
				$(this).trigger("change");
			});
		}

		function click(ev) {
			if (Link.hasClass("checked")) {
				// If it's already clicked, nothing happens for radio buttons.
			} else {
				Link.addClass("checked");
				Element.prop("checked",true);
				$('input[name="' + Element.attr("name") + '"]').not(Element).each(function() {
					this.customControl.Link.removeClass("checked");
					$(this).trigger("change");
				});
			}
			Element.triggerHandler("click");
			Element.triggerHandler("change");
			return false;
		}

		function disable() {
			Link.addClass("disabled");
			Element.prop("disabled", true);
		}

		function enable() {
			Link.removeClass("disabled");
			Element.prop("disabled", false);
		}

		function focus() {
			Link.addClass("focused");
		}
	
		function keydown(ev) {
			if (ev.keyCode == 32) {
				click(ev);
				return false;
			}
			if (ev.keyCode == 39 || ev.keyCode == 40) {
				next(ev);
				return false;
			}
			if (ev.keyCode == 37 || ev.keyCode == 38) {
				previous(ev);
				return false;
			}
		}
	
		function next(ev) {
			var all = $('input[name="' + Element.attr("name") + '"]');
			var index = all.index(Element);
			if (index != all.length - 1) {
				all[index + 1].customControl.Link.focus();
				all[index + 1].customControl.click(ev);
			}
		}
		
		function previous(ev) {
			var all = $('input[name="' + Element.attr("name") + '"]');
			var index = all.index(Element);
			if (index != 0) {
				all[index - 1].customControl.Link.focus();
				all[index - 1].customControl.click(ev);
			}
		}

		// Init routine
		if (Element.hasClass("custom_control")) {
			return false;
		}
		
		// Have label clicks affect the checkbox but let links inside of the labels still work properly
		Element.addClass("custom_control")
			   .next("label").click(click)
			   .find("a").click(function(ev) { ev.stopPropagation(); });

		Link = $("<a>").attr("href","#radio").click(click).focus(focus).blur(blur).keydown(keydown);
		
		if (element.checked) {
			Link.addClass("checked");
		}
		
		if (element.disabled) {
			Link.addClass("disabled")
				.attr("tabindex","-1");
		} else if (element.tabIndex) {
			Link.attr("tabindex",element.tabIndex);
		}
		
		Element.hide().after($('<div class="radio_button">').append(Link));

		return { Element: Element, Link: Link, blur: blur, click: click, clear: clear, disable: disable, enable: enable, focus: focus };

	})(jQuery,element);
};

var BigTreePhotoGallery = function(settings) {
	// BigTree < 4.2 style
	if (!is_object(settings)) {
		settings = { container: arguments[0], key: arguments[1], counter: arguments[2], disableCaptions: arguments[3] };
	}

	return (function($,settings) {

		var ActiveCaption = false;
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
					content: '<fieldset><label>Caption</label><input type="text" name="caption" /></fieldset>',
					callback: saveNewFile,
					icon: "caption"
				});
			} else {
				saveNewFile({ caption: "" });
			}
			return false;
		}
		
		function deletePhoto() {
			BigTreeDialog({
				title: "Remove Photo",
				content: '<p class="confirm">Are you sure you want to remove this photo?</p>',
				icon: "delete",
				alternateSaveText: "OK",
				callback: $.proxy(function() { $(this).parents("li").remove(); },this)
			});
			
			return false;
		}
		
		function editPhoto(ev) {
			var link = $(ev.target);
			ActiveCaption = link.siblings(".caption");
	
			BigTreeDialog({
				title: "Image Caption",
				content: '<fieldset><label>Caption</label><input type="text" name="caption" value="' + htmlspecialchars(ActiveCaption.val()) + '"/></fieldset>',
				callback: saveCaption,
				icon: "caption"
			});
	
			return false;
		}

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
		}

		function saveCaption(data) {
			ActiveCaption.val(data.caption);
			ActiveCaption = false;
		}
		
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
		}
		
		function useExistingFile(path,caption,thumbnail) {
			var li = $('<li>').html('<figure><img src="' + thumbnail + '" alt="" /></figure><a href="#" class="icon_edit"></a><a href="#" class="icon_delete"></a>');
			li.append($('<input type="hidden" name="' + Key + '[' + Counter + '][existing]" />').val(path));
			li.append($('<input type="hidden" name="' + Key + '[' + Counter + '][caption]" class="caption" />').val(caption));
			Container.find("ul").append(li);
			Counter++;
		}

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

var BigTreeTagAdder = (function($) {
	
	var Dropdown = false;
	var LastSearch = false;
	var Searching = false;
	var SelectedTag = -1;
	var TagEntry = false;
	var TagList = false;
	var TagResults = false;
	
	function init() {
		TagEntry = $("#tag_entry").keydown(checkKeys).keyup(searchTags);
		TagList = $("#tag_list").on("click","a",deleteHook);
		TagResults = $("#tag_results");
	}

	function addTag(ev) {
		var tag = TagEntry.val();

		if (tag) {
			ActiveTagName = tag;

			$.secureAjax("admin_root/ajax/tags/create-tag/", { type: "POST", data: { tag: tag }, success: addedTag });
		}
	}
	
	function addedTag(id) {
		TagList.append($('<li class="tag">').html('<a href="#"><input type="hidden" name="_tags[]" value="' + id + '" />' + ActiveTagName + '<span>x</span></a>'));
		TagEntry.val("").focus();
		TagResults.hide();
		Dropdown = false;
	}
	
	function checkKeys(ev) {
		if (ev.keyCode == 13) {
			if (SelectedTag > -1 && Dropdown) {
				var v = TagResults.find("li").eq(SelectedTag).find("a").html().replace("<span>","").replace("</span>","");
				TagEntry.val(v);
			}
			addTag(ev);
			return false;
		}
		if (ev.keyCode == 38) {
			moveUp(ev);
			return false;
		}
		if (ev.keyCode == 40) {
			moveDown(ev);
			return false;
		}
	}

	function chooseTag(ev) {
		var el = ev.target;
		var tag = el.innerHTML.replace("<span>","").replace("</span>","");

		if (tag) {
			ActiveTagName = tag;

			$.secureAjax("admin_root/ajax/tags/create-tag/", { type: "POST", data: { tag: tag }, success: addedTag });
		}

		return false;
	}

	function deleteHook(ev) {
		$(this).parents("li").remove();
		return false;
	}

	function hookResults() {
		SelectedTag = -1;
		if (TagResults.html()) {
			TagResults.show();
			Dropdown = true;
			TagResults.find("li a").click(chooseTag);
		} else {
			Dropdown = false;
			TagResults.hide();
		}
	}

	function moveDown(ev) {
		var li = TagResults.find("li");
		var max = li.length - 1;
		if (!Dropdown || SelectedTag == max) {
			return;
		}
		if (SelectedTag > -1) {
			li.eq(SelectedTag).removeClass("selected");
		}
		SelectedTag++;
		li.eq(SelectedTag).addClass("selected");
	}
	
	function moveUp(ev) {
		if (!Dropdown || SelectedTag < 0) {
			return;
		}
		var li = TagResults.find("li");
		li.eq(SelectedTag).removeClass("selected");
		SelectedTag--;
		if (SelectedTag > -1) {
			li.eq(SelectedTag).addClass("selected");
		}
	}
	
	function searchTags(ev) {
		var tag = TagEntry.val();
		if (tag != LastSearch) {
			LastSearch = tag;
			if (tag.length > 3) {
				TagResults.load("admin_root/ajax/tags/search/", { tag: tag }, hookResults);
			} else {
				TagResults.hide();
			}
		}
	}

	return { init: init };
})(jQuery);

var BigTreeDialog = function(settings) {
	// BigTree < 4.2 style
	if (!is_object(settings)) {
		settings = { title: arguments[0], content: arguments[1], callback: arguments[2], icon: arguments[3], noFooter: arguments[4], alternateSaveText: arguments[5], preSubmissionCallback: arguments[6], cancelHook: arguments[7] };
	}

	return (function($,settings) {
		var DialogHeight;
		var DialogWidth;
		var DialogWindow;
		var HeightWatchTimer;
		var OnComplete = false;
		var OnCancel = false;

		function checkForEsc(e) {
			if (e.keyCode == 27) {
				dialogClose();
			}
		}
	
		function dialogClose() {
			// Call the cancel hook once, if it requests that we close the dialog, don't run it again.
			if (OnCancel) {
				OnCancel();
				OnCancel = false;
			} else {
				$(".bigtree_dialog_overlay").last().remove();
				$(".bigtree_dialog_window").last().remove();
				$("body").off("keyup",checkForEsc);
				BigTree.ZIndex -= 2;
			}
			return false;
		}
	
		function dialogSubmit(ev) {
			ev.preventDefault();
			ev.stopPropagation();
	
			// Let's move all the TinyMCE content back.
			if (typeof tinyMCE != "undefined") {
				DialogWindow.find("textarea:hidden").each(function() {
					var id = $(this).attr("id");
					$(this).val(tinyMCE.get(id).getContent());
				});
			}
	
			// Pass the form data to our callback as JSON
			OnComplete(BigTree.cleanObject(DialogWindow.find(".bigtree_dialog_form").serializeJSON()));
			
			// Remove the dialog
			$(".bigtree_dialog_overlay").last().remove();
			$(".bigtree_dialog_window").last().remove();
			$("body").off("keyup",checkForEsc);
		}

		function watchHeight() {
			var height = DialogWindow.height();
			if (height != DialogHeight) {
				DialogHeight = height;
				windowResize(false,true);
			}
		}

		function windowResize(ev,animate) {
			var left_offset = parseInt((BigTree.windowWidth() - DialogWidth) / 2);
			var top_offset = parseInt((BigTree.windowHeight() - DialogHeight) / 2);
			
			if (animate) {
				DialogWindow.animate({ "top": top_offset + "px", "left": left_offset + "px" }, 200);
			} else {
				DialogWindow.css({ "top": top_offset + "px", "left": left_offset + "px" });
			}
		}

		// Setup a callback to give the data to once they submit their dialog
		OnComplete = settings.callback;
		OnCancel = settings.cancelHook;

		// If they hit escape, close the dialog
		$("body").on("keyup",checkForEsc);

		// Build our window
		var overlay = $('<div class="bigtree_dialog_overlay" style="z-index: ' + (BigTree.ZIndex++) + ';">');
		DialogWindow = $('<div class="bigtree_dialog_window">').css({ zIndex: BigTree.ZIndex++ });
		$("body").append(overlay).append(DialogWindow);

		// Fill the window
		var html = '<h2>';
		if (settings.icon) {
			html += '<span class="icon_dialog_' + settings.icon + '"></span>';
		}
		html += settings.title;
		if (settings.helpLink) {
			html += '<a href="' + settings.helpLink + '" target="_blank" class="icon_small icon_small_help"></a>';
		}
		html += '</h2><form class="bigtree_dialog_form" method="post" enctype="multipart/form-data" action="" class="module"><div class="overflow">' +  settings.content + '</div>';
		if (!settings.noFooter) {
			var saveText = settings.alternateSaveText ? settings.alternateSaveText : "Save";
			html += '<footer><a class="button bigtree_dialog_close">Cancel</a><input type="submit" class="button blue" value="' + saveText + '" /></footer>';
		}
		html += '</form>';
		DialogWindow.html(html);
		BigTreeCustomControls(DialogWindow);

		DialogWidth = DialogWindow.width();
		DialogHeight = DialogWindow.height();
		DialogWindow.css({ left: parseInt((BigTree.windowWidth() - DialogWidth) / 2) + "px", top: parseInt((BigTree.windowHeight() - DialogHeight) / 2) + "px" });

		BigTree.formHooks(DialogWindow);
				
		// Hook cancel button
		DialogWindow.find(".bigtree_dialog_close").click(dialogClose);
		
		// Hook form submission, if they don't want the submission just call the complete callback
		if (settings.preSubmissionCallback) {
			DialogWindow.find(".bigtree_dialog_form").submit(OnComplete);
		} else {
			DialogWindow.find(".bigtree_dialog_form").submit(dialogSubmit);
		}
		
		// For confirmation dialogs, enter should immediately close
		DialogWindow.find("input[type=submit]").focus();
		
		// Handle moving the dialog around if the window changes
		$(window).resize(windowResize);
		
		// Set a timer to watch for a change in the dialog height to recenter.
		HeightWatchTimer = setInterval(watchHeight,250);

		return { close: dialogClose, submit: dialogSubmit };

	})(jQuery,settings);
};

var BigTreeFileManager = (function($) {

	// Properties

	var AvailableThumbs = false;
	var Browser = false;
	var Callback = false;
	var CurrentFolder = 0;
	var CurrentlyKey = false;
	var CurrentlyName = false;
	var FieldName = false;
	var MinHeight = false;
	var MinWidth = false;
	var StartSearchTimer = false;
	var TitleSaveTimer = false;
	var Type = false;
	var UploadDialog = false;
	
	// Methods
	
	function addFile() {
		UploadDialog = BigTreeDialog({
			title: "Upload Files",
			content: '<input type="hidden" name="folder" value="' + CurrentFolder + '" /><fieldset><label>Select File(s)</label><input type="file" multiple name="files[]" /></fieldset>',
			icon: "folder",
			alternateSaveText: "Upload Files",
			preSubmissionCallback: true,
			callback: createFile
		});

		return false;
	}
	
	function addFolder() {
		UploadDialog = BigTreeDialog({
			title: "New Folder",
			content: '<input type="hidden" name="folder" value="' + CurrentFolder + '" /><fieldset><label>Folder Name</label><input type="text" name="name" /></fieldset>',
			callback: createFolder,
			icon: "folder",
			alternateSaveText: "Create Folder",
			preSubmissionCallback: true
		});
		
		return false;
	}
	
	function chooseImageSize() {
		$("#file_browser_upload").unbind("click").html("").css({ cursor: "default" }).click(function() { return false; });
		$("#file_browser_form .footer input.blue").hide();
		$("#file_browser_info_pane").css({ height: "437px", marginTop: 0, marginLeft: "-1px" });

		var size_pane = $("#file_browser_size_pane").html('<h3>Select Image Size</h3><p>Click on an image size below to insert into your content.</p>');
		// Add all available thumbnail sizes as buttons
		for (var i = 0; i < AvailableThumbs.length; i++) {
			var size = AvailableThumbs[i];
			var link = $('<a class="button">').attr("href",size.file.replace("{wwwroot}", "www_root/").replace("{staticroot}","static_root/")).html(size.name);
			size_pane.append(link);
		}
		// Add original size button and move the size pane to the left
		size_pane.append($('<a class="button">').attr("href",$("#file_browser_selected_file").val().replace("{wwwroot}", "www_root/").replace("{staticroot}","static_root/")).html("Original"));
		size_pane.css({ marginLeft: "210px" });

		// Hook the size buttons to change the selected URL
		size_pane.find("a").click(function() {
			FieldName.value = $(this).attr("href");
			closeFileBrowser();
			return false;
		});

		return false;
	}
	
	function closeFileBrowser() {
		$(".bigtree_dialog_overlay").last().remove();
		$("#file_browser").remove();
		BigTree.ZIndex = BigTree.ZIndexBackup;
		$("#mceModalBlocker, #mce-modal-block").show();
		
		return false;
	}
	
	function createFile() {
		var last_dialog = $(".bigtree_dialog_form").last();

		$("body").append($('<iframe name="file_manager_upload_frame" style="display: none;" id="file_manager_upload_frame">'));
		last_dialog.attr("action","admin_root/ajax/file-browser/upload/")
				   .attr("target","file_manager_upload_frame");
		last_dialog.prepend('<input type="hidden" name="' + CSRFTokenField + '" value="' + CSRFToken + '">');
		last_dialog.find("footer *").hide();
		last_dialog.find("footer").append($('<p style="line-height: 16px; color: #333;"><img src="admin_root/images/spinner.gif" alt="" style="float: left; margin: 0 5px 0 0;" /> Uploading files. Please wait…</p>'));
	}
	
	function createFolder(data) {
		var last_dialog = $(".bigtree_dialog_form").last();

		$("body").append($('<iframe name="file_manager_upload_frame" style="display: none;" id="file_manager_upload_frame">'));
		last_dialog.attr("action","admin_root/ajax/file-browser/create-folder/")
				   .attr("target","file_manager_upload_frame");
		last_dialog.prepend('<input type="hidden" name="' + CSRFTokenField + '" value="' + CSRFToken + '">');
		last_dialog.find("footer *").hide();
		last_dialog.find("footer").append($('<p style="line-height: 16px; color: #333;"><img src="admin_root/images/spinner.gif" alt="" style="float: left; margin: 0 5px 0 0;" /> Creating folder. Please wait…</p>'));
	}

	function deleteFile(ev) {
		var count = parseInt($(this).attr("data-allocation"));
		
		ev.preventDefault();
		ev.stopPropagation();
		
		if (count) {
			var c = confirm("This file is in use in " + count + " locations.\nThese links or images will become empty or broken.\n\nAre you sure you want to delete this file?");
		} else {
			var c = confirm("Are you sure you want to delete this file?");
		}

		if (c) {
			$.secureAjax("admin_root/ajax/file-browser/delete/", { type: "POST", data: { file: $("#file_browser_selected_file").val() } });
			
			$("#file_browser_contents .selected").remove();
			$("#file_browser_info_pane").html("");
			$("#file_browser .footer .blue").hide();
		}
	}

	function deleteFolder(ev) {
		ev.stopPropagation();
		ev.preventDefault();

		// Prevent double clicks
		if (BigTree.Busy) {
			return;
		}

		$.ajax("admin_root/ajax/file-browser/folder-allocation/", { type: "POST", data: { folder: CurrentFolder }, complete: function(r) {
			var j = $.parseJSON(r.responseText);
			
			if (confirm("This folder has " + j.folders + " sub-folder(s) and " + j.resources + " file(s) which will be deleted.\n\nFiles in this folder are in use in " + j.allocations + " location(s).\n\nAre you sure you want to delete this folder?")) {
				$.secureAjax("admin_root/ajax/file-browser/delete-folder/", { type: "POST", data: { folder: CurrentFolder }, complete: function(r) {
					if (Type == "image" || Type == "photo-gallery") {
						openImageFolder(r.responseText);	
					} else {
						openFileFolder(r.responseText);
					}
				}});
			}
		}});
	}
	
	function disableCreate() {
		$("#file_browser .header a").hide();		
	}
	
	function enableCreate() {
		$("#file_browser .header a").show();
	}
	
	function fileBrowser() {
		$("#file_browser_type_icon").addClass("icon_folder");
		$("#file_browser_type .title").html("File Browser");
		openFileFolder(0);
	}
	
	function fileBrowserPopulated() {
		$("#file_browser_contents a").click(fileClick);
	}
	
	function fileClick() {				
		if ($(this).hasClass("disabled")) {
			return false;
		}
		
		if ($(this).hasClass("folder")) {
			$("#file_browser .footer .blue").hide();
			openFileFolder($(this).attr("href").substr(1));
			return false;
		}

		// Show the "Use" button now that something is selected.
		$("#file_browser .footer .blue").show();
		
		$("#file_browser_contents a").removeClass("selected");
		$(this).addClass("selected");
		$("#file_browser_selected_file").val($(this).attr("href").replace("{wwwroot}","www_root/").replace("{staticroot}","static_root/"));
		$("#file_browser_info_pane").html('<span class="spinner"></span>');
		$("#file_browser_info_pane").load("admin_root/ajax/file-browser/file-info/",
			{ file: $(this).attr("href") },
			function() {
				$("#file_browser_detail_title_input").keyup(function() {
					clearTimeout(TitleSaveTimer);
					TitleSaveTimer = setTimeout(saveFileTitle,500);
				});
				$("#file_browser_info_pane .replace").click(replaceFile);
				$("#file_browser_info_pane .delete").click(deleteFile);
				$(".file_browser_detail_folder_button").click(function() {
					openFileFolder($(this).data("folder"));
				});
			}
		);
		
		return false;
	}
	
	function finishedUpload(errors) {
		UploadDialog.close();
		UploadDialog = false;
		
		if (Type == "image" || Type == "photo-gallery") {
			openImageFolder(CurrentFolder);	
		} else {
			openFileFolder(CurrentFolder);
		}
	}
	
	function formOpen(type,field_name,options,callback) {
		CurrentlyName = field_name;
		CurrentlyKey = options.currentlyKey;
		// We set this because fieldName is used by the TinyMCE hook, I know the naming doesn't make sense.
		FieldName = false;
		Callback = callback;
		open(type,options.minWidth,options.minHeight);
	}

	function hideDeleteFolder() {
		$("#file_browser .delete_folder").hide();
	}
	
	function imageBrowser() {
		$("#file_browser_type_icon").addClass("icon_images");
		$("#file_browser_type .title").html("Image Library");
		openImageFolder(0);
	}
	
	function imageBrowserPopulated() {
		$("#file_browser_contents a").click(imageClick);
	}
	
	function imageClick() {
		if ($(this).hasClass("disabled")) {
			return false;
		}
		
		if ($(this).hasClass("folder")) {
			$("#file_browser .footer .blue").hide();
			openImageFolder($(this).attr("href").substr(1));
			return false;
		}

		// Show the "Use" button now that something is selected.
		$("#file_browser .footer .blue").show();

		
		$("#file_browser_contents a").removeClass("selected");
		$(this).addClass("selected");
		
		var data = $.parseJSON($(this).attr("href"));
		AvailableThumbs = data.thumbs;
		$("#file_browser_selected_file").val(data.file.replace("{wwwroot}","www_root/").replace("{staticroot}","static_root/"));
		
		$("#file_browser_info_pane").html('<span class="spinner"></span>');
		$("#file_browser_info_pane").load("admin_root/ajax/file-browser/file-info/",
			{ file: data.file },
			function() {
				$("#file_browser_detail_title_input").keyup(function() {
					clearTimeout(TitleSaveTimer);
					TitleSaveTimer = setTimeout(saveFileTitle,500);
				});
				$("#file_browser_info_pane .replace").click(replaceFile);
				$("#file_browser_info_pane .delete").click(deleteFile);
				$(".file_browser_detail_folder_button").click(function() {
					openImageFolder($(this).data("folder"));
				});
			}
		);
		
		return false;
	}
	
	function open(type,min_width,min_height) {
		if ($.browser.msie  && parseInt($.browser.version, 10) === 7) {
			alert("This feature is not supported in Internet Explorer 7.  Please upgrade your browser.");
			return false;
		}

		Type = type;
		MinWidth = min_width;
		MinHeight = min_height;
			
		// Figure out where to put the window.
		var width = BigTree.windowWidth();
		var height = BigTree.windowHeight();
		var left_offset = Math.round((width - 820) / 2);
		var top_offset = Math.round((height - 500) / 2);

		// Set BigTree's zIndex super high because TinyMCE will try to be on top
		BigTree.ZIndexBackup = BigTree.ZIndex;
		BigTree.ZIndex = 500000;
		
		// Create the window.
		var overlay = $('<div class="bigtree_dialog_overlay" style="z-index:' + (BigTree.ZIndex++) + ';">');
		
		Browser = $('<div id="file_browser" style="z-index: ' + (BigTree.ZIndex++) + ';">');
		Browser.css({ top: top_offset + "px", left: left_offset + "px" });
		Browser.html('\
<div class="header">\
	<input class="form_search" id="file_browser_search" placeholder="Search" />\
	<span class="form_search_icon"></span>\
	<a href="#" class="button add_file">Upload Files</a>\
	<a href="#" class="button add_folder">New Folder</a>\
	<a href="#" class="button red delete_folder" style="display: none;">Delete Folder</a>\
	<span id="file_browser_type_icon"></span>\
	<h2 id="file_browser_type"><em class="title"></em><em class="suffix"></em></h2>\
</div>\
<ul id="file_browser_breadcrumb"><li><a href="#0">Home</a></li></ul>\
<div id="file_browser_upload_window" style="display: none;">\
	<span style="display: none;" id="file_browser_spinner" class="spinner"></span>\
	<iframe name="resource_frame" id="file_browser_upload_frame" style="display: none;" src="admin_root/ajax/file-browser/busy/"></iframe>\
	<form id="file_browser_upload_form" target="resource_frame" method="post" enctype="multipart/form-data" action="admin_root/ajax/file-browser/upload/">\
		<input type="hidden" name="MAX_FILE_SIZE" value="$max_file_size" />\
		<input type="file" name="file" id="file_browser_file_input" /> \
		<input type="submit" class="shorter blue" value="Upload" />\
	</form>\
</div>\
<form method="post" action="" id="file_browser_form">\
	<input type="hidden" id="file_browser_selected_file" value="" />\
	<div id="file_browser_contents"></div>\
	<div id="file_browser_info_pane"></div>\
	<section id="file_browser_size_pane"></section>\
	<div class="footer">\
		<input type="submit" class="button white" value="Cancel" id="file_browser_cancel" />\
		<input type="submit" class="button blue" value="Use Selected Item" style="display: none;" />\
	</div>\
</form>');

		$("body").append(overlay).append(Browser);
		
		// Hook the cancel, submit, and search.
		$("#file_browser_cancel").click(closeFileBrowser);
		$("#file_browser_form").submit(submitSelectedFile);
		$("#file_browser_search").keyup(function() {
			clearTimeout(StartSearchTimer);
			StartSearchTimer = setTimeout(search,300);
		});
		
		// Hide TinyMCE's default modal background, we're using our own.
		$("#mceModalBlocker, #mce-modal-block").hide();
		
		// Handle the clicks on the breadcrumb of folders
		$("#file_browser_breadcrumb").on("click","a",function() {
			var folder = $(this).attr("href").substr(1);

			if (Type == "image" || Type == "photo-gallery") {
				openImageFolder(folder);
			} else {
				openFileFolder(folder);
			}
			
			return false;
		});
		
		// Handle the create new folder / file clicks
		$("#file_browser .header .add_file").click(addFile);
		$("#file_browser .header .add_folder").click(addFolder);
		$("#file_browser .header .delete_folder").click(deleteFolder);
		
		// Open the right browser
		if (Type == "image" || Type == "photo-gallery") {
			imageBrowser();
		} else {
			fileBrowser();
		}
	}
	
	function openFileFolder(folder) {
		CurrentFolder = folder;
		$("#file_browser_selected_file").val("");
		$("#file_browser_info_pane").html("");
		$("#file_browser_form .footer .blue").hide();
		$("#file_browser_contents").scrollTop(0).load("admin_root/ajax/file-browser/get-files/", { folder: folder }, fileBrowserPopulated);
	}
	
	function openImageFolder(folder) {
		CurrentFolder = folder;
		$("#file_browser_selected_file").val("");
		$("#file_browser_info_pane").html("");
		$("#file_browser_form .footer .blue").hide();
		$("#file_browser_contents").scrollTop(0).load("admin_root/ajax/file-browser/get-images/", { minWidth: MinWidth, minHeight: MinHeight, folder: folder }, imageBrowserPopulated);
	}

	function replaceFile() {
		UploadDialog = BigTreeDialog({
			title: "Replace File",
			content: '<input type="hidden" name="replace" value="' + $(this).attr("data-replace") + '" /><fieldset><label>Select A File</label><input type="file" name="files[]" /></fieldset>',
			callback: replaceFileProcess,
			icon: "folder",
			alternateSaveText: "Replace File",
			preSubmissionCallback: true
		});

		return false;
	}

	function replaceFileProcess(data) {
		$("body").append($('<iframe name="file_manager_upload_frame" style="display: none;" id="file_manager_upload_frame">'));
		$(".bigtree_dialog_form").last().attr("action","admin_root/ajax/file-browser/upload/").attr("target","file_manager_upload_frame");
		$(".bigtree_dialog_form").last().find("footer *").hide();
		$(".bigtree_dialog_form").last().find("footer").append($('<p style="line-height: 16px; color: #333;"><img src="admin_root/images/spinner.gif" alt="" style="float: left; margin: 0 5px 0 0;" /> Replacing file. Please wait…</p>'));
	}
	
	function saveFileTitle() {
		var title = $("#file_browser_detail_title_input").val();
		var file = $("#file_browser_selected_file").val();

		$.secureAjax("admin_root/ajax/file-browser/save-title/", { type: "POST", data: { file: file, title: title } });
	}
	
	function search() {
		var query = $("#file_browser_search").val();
		$("#file_browser_info_pane").html("");
		$("#file_browser_selected_file").val("");
		
		if (Type == "image" || Type == "photo-gallery") {
			$("#file_browser_contents").load("admin_root/ajax/file-browser/get-images/", { minWidth: MinWidth, minHeight: MinHeight, query: query, folder: CurrentFolder }, imageBrowserPopulated);
		} else {
			$("#file_browser_contents").load("admin_root/ajax/file-browser/get-files/", { query: query, folder: CurrentFolder }, fileBrowserPopulated);
		}
	}
	
	function setBreadcrumb(contents) {
		$("#file_browser_breadcrumb").html(contents);
	}
	
	function setTitleSuffix(suffix) {
		$("#file_browser_type .suffix").html(suffix);
	}

	function showDeleteFolder() {
		$("#file_browser .delete_folder").show();
	}
	
	function submitSelectedFile() {
		if (FieldName) {
			if (Type == "image" && AvailableThumbs.length) {
				chooseImageSize();
				return false;
			} else {
				FieldName.value = $("#file_browser_selected_file").val();
				return closeFileBrowser();
			}
		} else {
			if (Type == "image") {
				var input = $('<input type="hidden" name="' + CurrentlyKey + '">');
				input.val("resource://" + $("#file_browser_selected_file").val());
				var img = new $('<img alt="">');
				img.attr("src",$("#file_browser_selected_file").val());
				var container = $(document.getElementById(CurrentlyName));
				container.find("img, input").remove();
				container.append(input).find(".currently_wrapper").append(img);
				container.show();

				// If a user already selected something to upload, replace it
				container.siblings("input").get(0).customControl.clear();
			} else if (Type == "photo-gallery") {
				Callback($("#file_browser_selected_file").val(),$("#file_browser_detail_title_input").val(),$(".file_browser_images .selected img").attr("src"));
			}
			return closeFileBrowser();
		}
	}
	
	function tinyMCEOpen(field_name,url,type,win) {
		CurrentlyName = false;
		// TinyMCE 3
		FieldName = win.document.forms[0].elements[field_name];
		if (!FieldName) {
			FieldName = $("#" + field_name).get(0);
		}
		open(type,false,false);
	}

	function uploadError(message,successes) {
		var last_dialog = $(".bigtree_dialog_form").last();
		last_dialog.find("p,fieldset,input").remove();
		last_dialog.find(".overflow").prepend($('<p class="error_message">' + message + '</p><p>' + successes + '</p>'));
		last_dialog.find("footer a").show().html("Ok");

		if (Type == "image" || Type == "photo-gallery") {
			openImageFolder(CurrentFolder);	
		} else {
			openFileFolder(CurrentFolder);
		}
	}

	return {
		disableCreate: disableCreate,
		enableCreate: enableCreate,
		finishedUpload: finishedUpload,
		formOpen: formOpen,
		hideDeleteFolder: hideDeleteFolder,
		setBreadcrumb: setBreadcrumb,
		setTitleSuffix: setTitleSuffix,
		showDeleteFolder: showDeleteFolder,
		tinyMCEOpen: tinyMCEOpen,
		uploadError: uploadError
	};

}(jQuery));

var BigTreeFormNavBar = (function() {

	var Container;
	var ContainerOffset;
	var MoreContainer;
	var Nav;
	var NextButton;
	var Sections;
	
	function init() {
		Container = $(".container");
		ContainerOffset = Container.offset().top;
		Nav = Container.find("nav a");
		NextButton = Container.find("footer .next");
		Sections = $(".container > form > section, .container > section");

		// Generic tab controls
		Nav.click(tabClick);
		// Next Button controls
		NextButton.click(nextClick);

		// Form Validation
		BigTreeFormValidator(Container.find("form"),function(errors) {
			// Hide all the pages tab sections
			Sections.hide();
			// Unset all the active states on tabs
			Nav.removeClass("active");
			// Figure out what section the first error occurred in and show that section.
			Nav.filter("[href=#" + errors[0].parents("section").show().attr("id") + "]").addClass("active");
		});

		// For when there are too many tabs, we need to setup scrolling
		var calc_nav_container = Container.find("nav .more div");
		var nav_width = calc_nav_container.width();
		if (nav_width > 928) {
			// If we're larger than 928, we're splitting into pages
			MoreContainer = calc_nav_container.parent();
			
			var page_count = 0;
			var current_width = 0;
			var current_page = $('<div class="nav_page active">');
			Nav.each(function() {
				var width = $(this).width() + 47;
				
				if ((current_width + width) > 848) {
					page_count++;
					if (page_count > 1) {
						var lessButton = $('<a class="more_nav" href="#">').html("&laquo;").click(function() {
							MoreContainer.animate({ marginLeft: + (parseInt(MoreContainer.css("margin-left")) + 928) + "px" }, 300);
							return false;
						});
						current_page.prepend(lessButton);
					}
					
					var moreButton = $('<a class="more_nav" href="#">').html("&raquo;").click(function() {
						MoreContainer.animate({ marginLeft: + (parseInt(MoreContainer.css("margin-left")) - 928) + "px" }, 300);
						return false;
					});
					current_page.append(moreButton);
					
					MoreContainer.append(current_page);
					current_page = $('<div class="nav_page">');
					current_width = 0;
				}
				
				current_width += width;
				current_page.append($(this));
			});
			
			
			var lessButton = $('<a class="more_nav" href="#">').html("&laquo;").click(function() {
				MoreContainer.animate({ marginLeft: + (parseInt(MoreContainer.css("margin-left")) + 928) + "px" }, 300);
				return false;
			});
			current_page.prepend(lessButton);
			
			MoreContainer.append(current_page);
			calc_nav_container.remove();
		}
	}

	function nextClick(ev) {
		ev.preventDefault();

		var tab = Nav.filter(".active");
		tab.removeClass("active");
		var next = tab.next("a").addClass("active");
		
		$("#" + next.attr("href").substr(1)).show();
		$("#" + tab.attr("href").substr(1)).hide();
		
		if (Nav.index(tab) == Nav.filter(":visible").length - 2) {
			$(this).hide();
		}
	}

	function switchPanel(id) {
		// Force # to the left of id
		id = id[0] == "#" ? id : "#" + id;

		// Reset
		Sections.hide();
		Nav.removeClass("active");

		// Figure out which tab it is
		for (var i = 0; i < Nav.length; i++) {
			if (Nav.eq(i).attr("href") == id) {
				Nav.eq(i).addClass("active");
			}
		}

		// Show new panel
		$(id).show();
	}

	function tabClick(ev) {
		ev.preventDefault();

		if (window.scrollY > ContainerOffset) {
			$("html, body").animate({ scrollTop: ContainerOffset + 3 }, 200);
		}
		
		var href = $(this).attr("href").substr(1);
		Sections.hide();
		Nav.removeClass("active");
		$(this).addClass("active");
		$("#" + href).show();
		
		// Manage the "Next" buttons
		var index = Nav.index(this);
		if (index == Nav.filter(":visible").length - 1) {
			NextButton.hide();
		} else {
			NextButton.show();				
		}
	}

	return { init: init, switchPanel: switchPanel };
}());


var BigTreeListMaker = function(settings) {
	// BigTree < 4.2 style
	if (!is_object(settings)) {
		settings = { element: arguments[0], name: arguments[1], title: arguments[2], columns: arguments[3], keys: arguments[4], existing: arguments[5] };
	}
	
	return (function($,settings) {

		var Container;
		var Count = 0;
		var Keys = [];
		var Name;

		function addOption() {
			var html = '<li><span class="icon_sort"></span>';
			for (var x = 0; x < Keys.length; x++) {
				if (Keys[x].type == "select") {
					html += '<span><select class="custom_control" name="' + Name + '[' + Count + '][' + Keys[x].key + ']">';
					for (var v in Keys[x].list) {
						html += '<option value="' + htmlspecialchars(v) + '">' + htmlspecialchars(Keys[x].list[v]) + '</option>';
					}
					html += '</select></span>';
				} else {
					html += '<span><input type="text" name="' + Name + '[' + Count + '][' + Keys[x].key + ']" /></span>';
				}
			}
			html += '<a class="delete icon_small icon_small_delete" href="#"></a></li>';

			// Add the option, increment the count
			Container.find("ul").append(html);
			Count++;

			// We're guaranteed at least one option now, so show the header.
			Container.find("summary").show();
	
			return false;
		}
		
		function deleteOption() {
			var ul = $(this).parents("ul").eq(0);
			$(this).parents("li").eq(0).remove();
			// Hide the header if we're out of options
			if (ul.find("li").length == 0) {
				ul.prev("summary").hide();
			}
	
			return false;
		}

		// Init routine
		Container = $(settings.element);
		Keys = settings.keys;
		Name = settings.name;

		var html = '<h4>' + settings.title + ' <a href="#" class="add_option icon_small icon_small_add"></a></h4>';
		html += '<fieldset class="list_options_widget list_options_widget_' + Keys.length + '">';
		// Add column headers
		html += '<summary>';
		for (var i = 0; i < settings.columns.length; i++) {
			html += '<span>' + settings.columns[i] + '</span>';
		}
		html += '</summary>';
		// Add options
		html += '<ul>';
		var count = 0;
		for (var i in settings.existing) {
			html += '<li><span class="icon_sort"></span>';
			for (var x = 0; x < Keys.length; x++) {
				if (Keys[x].type == "select") {
					html += '<span><select class="custom_control" name="' + Name + '[' + count + '][' + Keys[x].key + ']">';
					for (var v in Keys[x].list) {
						html += '<option value="' + htmlspecialchars(v) + '"';
						if (v == settings.existing[i][Keys[x].key]) {
							html += ' selected="selected"';
						}
						html += '>' + htmlspecialchars(Keys[x].list[v]) + '</option>';
					}
					html += '</select></span>';
				} else {
					html += '<span><input type="text" name="' + Name + '[' + count + '][' + Keys[x].key + ']" value="' + htmlspecialchars(settings.existing[i][Keys[x].key]) + '" /></span>';
				}
			}
			html += '<a class="delete icon_small icon_small_delete" href="#"></a></li>';
			count++;
		}
		html += '</ul>';
		Count = count;
		Container.html(html);

		// Hide the summary if we have no options
		if (!settings.existing || settings.existing.length == 0) {
			Container.find("summary").hide();
		}
		// Hook the add button
		Container.find(".add_option").click(addOption);
		// Hook delete buttons
		Container.on("click",".delete",deleteOption);
		// Make it sortable
		Container.sortable({
			handle: ".icon_sort",
			axis: "y",
			containment: "parent",
			items: "li",
			placeholder: "ui-sortable-placeholder",
			update: function() {
				// Reset keys, JSON.stringify doesn't care what order the data was in.
				var x = 0;
				var rows = Container.find("li");
				rows.each(function() {
					var fields = $(this).find("input,select");
					for (i = 0; i < Keys.length; i++) {
						fields.eq(i).attr("name",Name + "[" + x + "][" + Keys[i].key + "]");
					}
					x++;
				});
			}
		});

		return { addOption: addOption };
	})(jQuery,settings);
};

var BigTreeManyToMany = function(settings) {
	// BigTree < 4.2 style
	if (!is_object(settings)) {
		settings = { id: arguments[0], count: arguments[1], key: arguments[2], sortable: arguments[3], keepOptions: arguments[4] };
	}

	return (function($,settings) {
		var Count = 0;
		var DeleteTarget;
		var Field;
		var Key;
		var List;
		var Select;
		var Sortable;
		var KeepOptions;

		function addAll(ev) {
			ev.preventDefault();

			// Keep track of the stuff we're adding so we can remove it from the dropdown
			var vals = [];

			for (var i = 0; i < Select.options.length; i++) {
				var val = Select.options[i].value;
				var text = Select.options[i].text;
	
				if (Sortable) {
					var li = $('<li><input type="hidden" name="' + Key + '[' + Count + ']" /><span class="icon_sort"></span><p></p><a href="#" class="icon_delete"></a></li>');
				} else {
					var li = $('<li><input type="hidden" name="' + Key + '[' + Count + ']" /><p></p><a href="#" class="icon_delete"></a></li>');		
				}
				li.find("p").html(text);
				li.find("input").val(val);

				List.append(li);
				Field.trigger("addedItem", { element: li, index: Count });
				Count++;
				vals.push(val);
			}
			
			// Remove the options from the select.
			if (!KeepOptions) {
				for (i = 0; i < vals.length; i++) {
					Select.customControl.remove(vals[i]);
				}
			}

			// Hide the instructions saying there haven't been any items tagged.
			Field.find("section").hide();
		}

		function addItem(ev) {
			ev.preventDefault();

			if (Select.selectedIndex < 0) {
				return;
			}

			var val = Select.value;
			var text = Select.options[Select.selectedIndex].text;

			if (Sortable) {
				var li = $('<li><input type="hidden" name="' + Key + '[' + Count + ']" /><span class="icon_sort"></span><p></p><a href="#" class="icon_delete"></a></li>');
			} else {
				var li = $('<li><input type="hidden" name="' + Key + '[' + Count + ']" /><p></p><a href="#" class="icon_delete"></a></li>');		
			}
			li.find("p").html(text);
			li.find("input").val(val);
	
			// Remove the option from the select.
			if (!KeepOptions) {
				Select.customControl.remove(val);
			}

			List.append(li);
			Field.trigger("addedItem", { element: li, index: Count });
			Count++;
			
			// Hide the instructions saying there haven't been any items tagged.
			Field.find("section").hide();
		}
	
		function deleteItem(ev) {
			ev.preventDefault();

			// If this is the last item we're removing, show the instructions again.
			if (List.find("li").length == 1) {
				Field.find("section").show();
			}
			
			var li = $(this).parents("li");
			var val = li.find("input").val();
			var text = li.find("p").html();
			
			// Add the option back to the select
			if (!KeepOptions) {
				Select.customControl.add(val,text);
			}

			li.remove();
			Field.trigger("removedItem", { value: val, description: text });
		}

		function reset(ev) {
			ev.preventDefault();

			// Remove everything
			List.find("li").each(function() {
				var li = $(this);
				var val = li.find("input").val();
				var text = li.find("p").html();
				
				// Add the option back to the select
				if (!KeepOptions) {
					Select.customControl.add(val,text);
				}
	
				li.remove();
				Field.trigger("removedItem", { value: val, description: text });
			});

			// Show the empty message
			Field.find("section").show();
		}

		// Init routine
		Field = $("#" + settings.id);
		Count = settings.count;
		Key = settings.key;
		if (settings.sortable) {
			Sortable = true;
		}
		if (settings.keepOptions) {
			KeepOptions = true;
		}

		List = Field.find("ul");
		Select = Field.find("select").get(0);

		if (Sortable) {
			List.sortable({ items: "li", handle: ".icon_sort" });
		}

		Field.find(".add").click(addItem);
		Field.find(".add_all").click(addAll);
		Field.find(".reset").click(reset);
		Field.on("click",".icon_delete",deleteItem);

		return { Field: Field, List: List, Select: Select };

	})(jQuery,settings);
};

var BigTreeFieldSelect = function(settings) {
	return (function($,settings) {

		var Callback;
		var Container;
		var CurrentElement = 0;
		var CurrentlyContainer;
		var CurrentlyP;
		var Dropdown;
		var Elements;

		function addField(field,title) {
			Dropdown.append($('<a href="#' + title + '">' + field + '</a>'));
			CurrentlyContainer.append($('<a href="#' + title + '">' + field + '</a>'));
			Elements.push({ field: field, title: title });

			// If we previously had no fields, show it again.
			if (Elements.length == 1) {
				Container.find("p").html(Elements[0].field);
				Container.show();
			}
		}
		
		function click(ev) {
			if (Dropdown.hasClass("open")) {
				close();
			} else {
				if (Dropdown.find("a").length > 1) {
					Dropdown.show().addClass("open");
					$("body").bind("click",close);
				}
			}
			return false;
		}
		
		function close() {
			$(window).unbind("click",close);
			Dropdown.removeClass("open").hide();
		}
		
		function removeCurrent() {
			Dropdown.find("a").eq(CurrentElement).remove();
			CurrentlyContainer.find("a").eq(CurrentElement).remove();
			Elements.splice(CurrentElement,1);
			CurrentElement = 0;
			
			if (Elements.length == 0) {
				Container.hide();
			} else {
				CurrentlyP.html(Elements[0].field);
			}
		}

		// Init routine
		Container = $('<div class="field_selector">');
		Elements = settings.elements;
		Callback = settings.callback;

		// Build the element
		var option_html = "";
		for (var i = 0; i < Elements.length; i++) {
			option_html += '<a href="#' + Elements[i].title + '">' + Elements[i].field + '</a>';
		}
		if (Elements.length == 0) {
			Container.html('<a href="#" class="add_field"></a><div><span class="dd">' + option_html + '</span></div><span class="handle"></span><span class="current"><p></p>' + option_html + '</span>');
		} else {
			Container.html('<a href="#" class="add_field"></a><div><span class="dd">' + option_html + '</span></div><span class="handle"></span><span class="current"><p>' + Elements[0].field + '</p>' + option_html + '</span>');
		}
		$(settings.selector).prepend(Container);

		// Cache some DOM
		Dropdown = Container.find(".dd");
		CurrentlyContainer = Container.find(".current");
		CurrentlyP = CurrentlyContainer.find("p");
		
		// Action hooks
		Container.find("p, .handle").click(click);
		Container.find(".add_field").click(function() {
			var element = Elements[CurrentElement];
			Callback(element,{ Callback: Callback, Container: Container, CurrentElement: CurrentElement, Elements: Elements, addField: addField, removeCurrent: removeCurrent });
			return false;
		});

		Dropdown.on("click","a",function(ev) {
			var element = ev.currentTarget;
			CurrentlyP.html($(element).html());
			CurrentElement = Dropdown.hide().find("a").index(element);
			return false;
		});
		
		if (Elements.length == 0) {
			Container.hide();
		}

		return { Callback: Callback, Container: Container, CurrentElement: CurrentElement, Elements: Elements, addField: addField, removeCurrent: removeCurrent };

	})(jQuery,settings);
};

var BigTreeFormValidator = function(selector,callback) {
	return (function($,selector,callback) {

		var Form;
		var Callback;

		// in_dialog and embedded are never called by the submit event, they are only for manual calls to validateForm
		function validateForm(ev,in_dialog,embedded) {
			var errors = [];

			Form.data("initial-state",Form.serialize());
			
			Form.find(".form_error").removeClass("form_error");
			Form.find(".form_error_reason").remove();
			Form.find("input.required, select.required, textarea.required").each(function() {
				// TinyMCE 3
				if ($(this).nextAll(".mceEditor").length) {
					var val = tinyMCE.get($(this).attr("id")).getContent();
				// Tiny MCE 4
				} else if ($(this).prevAll(".mce-tinymce").length) {
					var val = tinymce.get($(this).attr("id")).getContent();
				// File/Image Uploads
				} else if ($(this).parents("fieldset").find(".currently, .currently_file").length) {
					var val = $(this).parents("fieldset").find(".currently, .currently_file").find("input").val();
					if (!val) {
						val = $(this).val();
					}
				// Regular input fields
				} else {
					var val = $(this).val();
					// If this is a file field, see if there's a regular input with the same name
					if (!val && $(this).attr("type") == "file") {
						$("input[name='" + $(this).attr("name") + "']").each(function(index) {
							if (!val) {
								val = $(this).val();
							}
						});
					}
				}
				if (!val) {
					errors[errors.length] = $(this);
					$(this).parents("fieldset").eq(0).addClass("form_error");
					$(this).prevAll("label").append($('<span class="form_error_reason">Required</span>'));
					$(this).parents("div").prevAll("label").append($('<span class="form_error_reason">Required</span>'));
				}
			});
			Form.find("input.numeric").each(function() {
				if (isNaN($(this).val())) {
					errors[errors.length] = $(this);
					$(this).parents("fieldset").eq(0).addClass("form_error");
					$(this).prevAll("label").append($('<span class="form_error_reason">This Field Must Be Numeric</span>'));
				}
			});
			Form.find("input.email").each(function() {
				var reg = /^(([^<>()[\]\\.,;:\s@\"]+(\.[^<>()[\]\\.,;:\s@\"]+)*)|(\".+\"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
				var val = $(this).val();
				if (val && !reg.test(val)) {
					errors[errors.length] = $(this);
					$(this).parents("fieldset").eq(0).addClass("form_error");
					$(this).prevAll("label").append($('<span class="form_error_reason">This Field Must Be An Email Address</span>'));
				}
			});
			Form.find("input.link").each(function() {
				var reg = /(ftp|http|https):\/\/(\w+:{0,1}\w*@)?(\S+)(:[0-9]+)?(\/|\/([\w#!:.?+=&%@!\-\/]))?/;
				var val = $(this).val();
				if (val && !reg.test(val)) {
					errors[errors.length] = $(this);
					$(this).parents("fieldset").eq(0).addClass("form_error");
					$(this).prevAll("label").append($('<span class="form_error_reason">This Field Must Be A Valid URL</span>'));
				}
			});
	
			// If this is an embedded form, we want to generate a hash of everything
			var complete_submission = "";
			if ($("#bigtree_hashcash_field").length) {
				Form.find("input,select,textarea").not("#bigtree_hashcash_field").each(function() {
					if ($(this).is("textarea") && $(this).css("display") == "none") {
						var mce = tinyMCE.get($(this).attr("id"));
						if (mce) {
							complete_submission += mce.getContent();
						} else {
							complete_submission += $(this).val();
						}
					} else {
						var t = $(this).attr("type");
						if (t != "file" && $(this).attr("name")) {
							if ((t != "radio" && t != "checkbox") || $(this).is(":checked")) {
								complete_submission += $(this).val();
							}
						}
					}
				});
				$("#bigtree_hashcash_field").val(md5(complete_submission));
			}
			
			if (Form.find(".form_error").length) {
				Form.find(".warning_message").hide();
				Form.find(".error_message").show();
				if (!in_dialog) {
					$("html, body").animate({ scrollTop: $(".container").offset().top }, 200);
					if (window.parent.BigTreeEmbeddableForm) {
						window.parent.BigTreeEmbeddableForm.scrollToTop();
					}
				} else {
					Form.find(".overflow, .callout_fields").animate({ scrollTop: 0 }, 200);
				}
				if (Callback) {
					Callback(errors);
				}

				// Try to stop the form submission
				try {
					ev.stopImmediatePropagation();
					ev.stopPropagation();
					ev.preventDefault();
				} catch (error) {};

				return false;
			} else {
				window.onbeforeunload = null;
				return true;
			}
		}

		// Init routine
		Form = $(selector);
		Form.submit(validateForm);
		if (callback) {
			Callback = callback;
		}

		// Make forms verify you wish to leave if you've made changes.
		// Init this 5 seconds after people hit the page so quick interactions aren't interrupted.
		$(document).ready(function() {
			setTimeout(function() {
				// Save TinyMCE fields before getting initial state, sometimes they alter the markup
				try {
					for (editor_id in tinymce.editors) {
						tinymce.editors[editor_id].save();
					}
				} catch (er) {}
				// Save initial state
				Form.data("initial-state",Form.serialize());
				
				// Hook unload
				window.onbeforeunload = function(ev) {
					// Try to save TinyMCE fields
					try {
						for (editor_id in tinymce.editors) {
							tinymce.editors[editor_id].save();
						}
					} catch (er) {}
		
					if (Form.serialize() != Form.data("initial-state")) {
						return "You have unsaved changes.";
					}
				};
			},5000);
		});

		return { Form: Form, Callback: Callback, validateForm: validateForm };

	})(jQuery,selector,callback);
};

var BigTreeToolTip = function(settings) {
	return (function($,settings) {

		var Container;
		var Content;
		var Position;
		var Settings = { hover: true, position: "above", icon: "alert" };
		var Target;

		function hide() {
			Container.stop().fadeTo(200,0,function() { Container.hide(); });
			BigTree.ZIndex--;
			return false;
		}
		
		function show() {
			// Figure out where the target is in the DOM, add the container to the DOM so we can get its width/height for some positions.
			var offset = Target.offset();
			var w = parseInt(Target.css("width"));
			var h = parseInt(Target.css("height"));
			
			// The tip is below the target.
			if (Settings.position == "below") {
				var l = offset.left - 28 + Math.round(w / 2);
				var t = offset.top + h + 5;
			}
			
			// The tip is to the right of the target.
			if (Settings.position == "right") {
				var l = offset.left + w + 5;
				var t = offset.top - 28 + Math.round(h / 2);
			}
			
			// The tip is to the left of the target.
			if (Settings.position == "left") {
				var l = offset.left - Container.width() - 5;
				var t = offset.top - 28 + Math.round(h / 2);
			}
			
			// The tip is above of the target.
			if (Settings.position == "above") {
				var l = offset.left - 28 + Math.round(w / 2);
				var t = offset.top - Container.height() - 5;
			}
			
			Container.css({ left: l + "px", top: t + "px", zIndex: (BigTree.ZIndex++) }).stop().fadeTo(200, 1);
		}

		// Init routine
		for (var i in settings) {
			Settings[i] = settings[i];
		}
		Target = $(settings.selector);
		Content = settings.content;
		Container = $('<div class="tooltip" style="display: none;">');
		
		// The arrow is below the tip if the position is above.
		if (Settings.position != "above") {
			Container.append($('<span class="arrow">'));
		}

		var tip = $('<article>').html('<section class="icon_tooltip icon_growl_' + Settings.icon + '"></section><section class="content">' + Content + '</section>');
		// If the tip should stay open, add a close button.  Otherwise it'll close when you roll off the target.
		if (!Settings.hover) {
			tip.append($('<a href="#" class="close"></a>'));
			tip.find(".close").click(hide);
		}

		Container.append(tip).addClass("tooltip_" + Settings.position);
		if (Settings.position == "above") {
			Container.append($('<span class="arrow">'));
		}

		$("body").append(Container);
		
		if (Settings.hover) {
			Target.mouseenter(show).mouseleave(hide);
		} else {
			Target.click(show);
		}

		return { Container: Container, Content: Content, Position: Position, Target: Target, hide: hide, show: show };

	})(jQuery,settings);
};

var BigTreeFilesystemBrowser = function(settings) {
	return (function($,settings) {

		var BaseDirectory = settings.baseDir ? settings.baseDir : "";
		var Bucket = settings.bucket ? settings.bucket : "";
		var Callback = settings.callback ? settings.callback : false;
		var CloudServices = settings.disableCloud ? false : true;
		var Container;
		var File = settings.file ? settings.file : "";
		var Directory = settings.directory ? settings.directory : "/";
		var Form;
		var Location = settings.location ? settings.location : "";
		var Overlay;

		function close(ev) {
			ev.preventDefault();
			Overlay.remove();
			Container.remove();
			BigTree.ZIndex -= 2;
		}

		function navigate(ev) {
			ev.preventDefault();
			var type = $(this).attr("data-type");
			if (type == "location") {
				Location = $(this).attr("href");
				Bucket = "";
				Directory = "";
			} else if (type == "container") {
				Bucket = $(this).attr("href");
				Directory = "";
			} else {
				var d = $(this).attr("href");
				if (d == "..") {
					if (Location && !Directory) {
						Bucket = "";
					} else {
						var parts = Directory.split("/");
						parts.splice(parts.length - 2,1);
						Directory = parts.join("/");
					}
				} else {
					Directory = Directory + d + "/";
				}
			}
			reload();
		}

		function reload() {
			Form.load("admin_root/ajax/developer/extensions/file-browser/", { base_directory: BaseDirectory, directory: Directory, cloud_disabled: !CloudServices, file: File, location: Location, container: Bucket });
		}

		function select(ev) {
			ev.preventDefault();
			$(".browser_pane li").removeClass("selected");
			$(this).addClass("selected");
			$("#bigtree_foundry_file").val($(this).find("p").html());
		}

		function submit() {
			var data = { file: $("#bigtree_foundry_file").val(), directory: $("#bigtree_foundry_directory").val(), container: $("#bigtree_foundry_container").val(), location: $("#bigtree_foundry_location").val() };
			if (Callback) {
				Callback(data);
			}
			$(".bigtree_dialog_overlay").last().remove();
			Container.remove();
			BigTree.ZIndex -= 2;
			return false;
		}

		// Init routine
		Overlay = $('<div class="bigtree_dialog_overlay" style="z-index: ' + (BigTree.ZIndex++) + ';">');
		Container = $('<div id="bigtree_foundry_browser_window" style="z-index: ' + (BigTree.ZIndex++) + ';">').html('<h2>File Browser</h2><form id="bigtree_foundry_browser_form" method="post" action="">Loading&hellip;</form>');
		Container.on("click",".navigation_pane a",navigate)
				 .on("click",".browser_pane li",select)
				 .on("click","a.button",close);

		Form = Container.find("form");
		Form.submit(submit);
		reload();

		$("body").append(Overlay).append(Container);

		return { Callback: Callback, Container: Container, submit: submit };

	})(jQuery,settings);
};

var BigTreeCallouts = function(settings) {
	return (function($,settings) {
		var Container = $(settings.selector);

		var AddButton = Container.find(".add_callout");
		var Count;
		var CurrentItem;
		var Description;
		var DescriptionField;
		var Groups = settings.groups;
		var Key = settings.key;
		var LastDialog;
		var List = Container.find(".contain");
		var Max = settings.max ? settings.max : 0;
		var Noun = settings.noun;

		function addCallout(e) {
			e.preventDefault();

			// Prevent double clicks
			if (BigTree.Busy) {
				return;
			}

			BigTree.TabIndexDepth++;

			$.ajax("admin_root/ajax/callouts/add/", { type: "POST", data: { count: Count, groups: Groups, key: Key, tab_depth: BigTree.TabIndexDepth }, complete: function(response) {
				BigTreeDialog({
					title: "Add " + Noun,
					content: response.responseText,
					icon: "callout",
					preSubmissionCallback: true,
					callback: function(e) {		
						e.preventDefault();
						
						var item;
						if (item = getCallout()) {		
							// Add the callout and hide the dialog.
							List.append(item);
							removeDialog();
							Count++;
							var count = List.find("article").length;
							if (Max && count >= Max) {
								AddButton.hide();
							}
						}
					}
				});
			}});
		}

		function deleteCallout(e) {
			e.preventDefault();

			CurrentItem = $(this).parents("article");
			BigTreeDialog({
				title: "Delete " + Noun,
				content: '<p class="confirm">Are you sure you want to delete this ' + Noun.toLowerCase() + '?</p>',
				callback: function() {
					CurrentItem.remove();
					var count = List.find("article").length;
					if (count < Max) {
						AddButton.show();
					}
				},
				icon: "delete",
				alternateSaveText: "OK"
			});
			return false;
		}

		function editCallout(e) {
			e.preventDefault();

			// Prevent double clicks
			if (BigTree.Busy) {
				return;
			}

			BigTree.TabIndexDepth++;

			CurrentItem = $(this).parents("article");
			$.ajax("admin_root/ajax/callouts/edit/", { type: "POST", data: { count: Count, data: CurrentItem.find(".callout_data").val(), groups: Groups, key: Key, tab_depth: BigTree.TabIndexDepth }, complete: function(response) {
				BigTreeDialog({
					title: "Edit " + Noun,
					content: response.responseText,
					icon: "callout",
					preSubmissionCallback: true,
					callback: function(e) {
						e.preventDefault();
						
						var item;
						if (item = getCallout()) {
							CurrentItem.replaceWith(item);
							removeDialog();
							Count++;
						}
					}
				});
			}});
		}

		function getCallout() {
			LastDialog = $(".bigtree_dialog_form").last();
			BigTree.TabIndexDepth--;

			var type_select = LastDialog.find(".callout_type select").get(0);
	
			// Validate required fields.
			var validator = BigTreeFormValidator(LastDialog);
			if (!validator.validateForm(false,true)) {
				return false;
			}
			
			var article = $('<article>');
			article.html('<h4></h4><p>' + type_select.options[type_select.selectedIndex].text + '</p><div class="bottom"><span class="icon_drag"></span><a href="#" class="icon_delete"></a></div>');
			
			// Try our best to find some way to describe the callout
			Description = "";
			DescriptionField = LastDialog.find("[name='" + LastDialog.find(".display_field").val() + "']");
			if (DescriptionField.is('select')) {
				Description = DescriptionField.find("option:selected").text();
			} else {
				Description = DescriptionField.val();
			}
			if ($.trim(Description) == "") {
				Description = LastDialog.find(".display_default").val();
			}
			
			// Append all the relevant fields into the callout field so that it gets saved on submit with the rest of the form.
			LastDialog.find("input, textarea, select").each(function() {
				if ($(this).attr("type") != "submit") {
					if ($(this).is("textarea") && $(this).css("display") == "none") {
						var mce = tinyMCE.get($(this).attr("id"));
						if (mce) {
							mce.save();
							mce.remove();
						}
					}
					$(this).hide().get(0).className = "";
					article.append($(this));
				}
			});

			article.find("h4").html(strip_tags(Description) + '<input type="hidden" name="' + Key + '[' + Count + '][display_title]" value="' + htmlspecialchars(strip_tags(Description)) + '" />');
	
			return article;
		}

		function removeDialog() {
			LastDialog.parents("div").remove();
			LastDialog.remove();
			$(".bigtree_dialog_overlay").last().remove();
			BigTree.zIndex -= 2;
		}

		// Init routine
		Count = List.find("article").length;
		// Hide the add button if we're at or above the limit
		if (Max && Count >= Max) {
			AddButton.hide();
		}
		Container.on("click",".add_callout",addCallout)
				 .on("click",".icon_edit",editCallout)
				 .on("click",".icon_delete",deleteCallout);
		List.sortable({ containment: "parent", handle: ".icon_drag", items: "article", placeholder: "ui-sortable-placeholder", tolerance: "pointer" });

		return { Container: Container, Count: Count, Key: Key, Groups: Groups, List: List, addCallout: addCallout };
			
	})(jQuery,settings);
};

var BigTreeMatrix = function(settings) {
	return (function($,settings) {

		var AddButton;
		var Columns;
		var Container;
		var Count;
		var CurrentItem;
		var Key;
		var LastDialog;
		var List;
		var Max;
		var NoItemsMessage;
		var Style;
		var Subtitle;
		var Title;

		function addItem(e) {
			e.preventDefault();

			// Prevent double clicks
			if (BigTree.Busy) {
				return;
			}

			BigTree.TabIndexDepth++;

			$.ajax("admin_root/ajax/matrix-field/", {
				type: "POST",
				data: { columns: Columns, count: Count, key: Key, tab_depth: BigTree.TabIndexDepth },
				complete: function(response) {
					BigTreeDialog({
						title: "Add Item",
						content: response.responseText,
						icon: "add",
						preSubmissionCallback: true,
						callback: function(e) {		
							e.preventDefault();
							
							var item;
							if (item = getItem()) {
								// Add the item, remove the dialog, increase the count.
								List.append(item);
								NoItemsMessage.hide();
								removeDialog();						
								Count++;
								var count = List.find("article, li").length;
								if (Max && count >= Max) {
									AddButton.hide();
								}
							}
						}
					});
				}
			});
		}

		function deleteItem(e) {
			e.preventDefault();

			if (Style === "list") {
				CurrentItem = $(this).parents("li");
			} else {
				CurrentItem = $(this).parents("article");
			}

			BigTreeDialog({
				title: "Delete Item",
				content: '<p class="confirm">Are you sure you want to delete this item?</p>',
				callback: function() {
					CurrentItem.remove();
					var count = List.find("article, li").length;
					if (count < Max) {
						AddButton.show();
						if (!count) {
							NoItemsMessage.show();
						}
					}
				},
				icon: "delete",
				alternateSaveText: "OK"
			});
		}

		function editItem(e) {
			e.preventDefault();

			// Prevent double clicks
			if (BigTree.Busy) {
				return;
			}

			BigTree.TabIndexDepth++;

			// Set the current element that we're going to replace
			if (Style === "list") {
				CurrentItem = $(this).parents("li");
			} else {
				CurrentItem = $(this).parents("article");
			}
			
			$.ajax("admin_root/ajax/matrix-field/", {
				type: "POST",
				data: { columns: Columns, count: Count, data: CurrentItem.find(".bigtree_matrix_data").val(), key: Key, tab_depth: BigTree.TabIndexDepth },
				complete: function(response) {
					BigTreeDialog({
						title: "Edit Item",
						content: response.responseText,
						icon: "edit",
						preSubmissionCallback: true,
						callback: function(e) {
							e.preventDefault();
							
							var item;
							if (item = getItem()) {
								// Replace the item, remove the dialog, increase the count
								CurrentItem.replaceWith(item);
								removeDialog();
								Count++;
							}
						}
					});
				}
			});
		}
		
		function getItem() {
			LastDialog = $(".bigtree_dialog_form").last();
			BigTree.TabIndexDepth--;
	
			// Validate required fields.
			var validator = BigTreeFormValidator(LastDialog);
			if (!validator.validateForm(false,true)) {
				return false;
			}
			
			if (Style == "list") {
				var entry = $('<li>').html('<span class="icon_sort"></span><p></p><a href="#" class="icon_delete"></a>');
			} else {
				var entry = $('<article>').html('<h4></h4><p></p><div class="bottom"><span class="icon_drag"></span><a href="#" class="icon_delete"></a></div>');
			}
	
			// Try our best to find some way to describe the item
			Title = Subtitle = "";
			LastDialog.find(".matrix_title_field").each(function(index,el) {
				if (!Title || !Subtitle) {
					var item = $(el).find("input[type=checkbox],input[type=text],input[type=email],input[type=url],input[type=hidden],textarea,select").not("[type=file]");
					if (item.length) {
						// Going to check for multi-part inputs like names, address, phone
						var parent = item.parent();
						if (parent.hasClass("input_name") || parent.hasClass("input_phone_3") || parent.hasClass("input_address_street")) {
							var value = "";
							item.parent().siblings('section').each(function() {
								if (parent.hasClass("input_phone_3")) {
									value += "-" + $(this).children("input").val();
								} else {
									value += " " + $(this).children("input, select").val();
								}
							});
							// Remove the leading -
							value = $.trim(value.substr(1));
						} else if (item.is("select")) {
							var value = $.trim(item.find("option:selected").text());
						} else {
							var value = $.trim(item.val());
						}
						// Reset value if item is an unchecked checkbox
						if (item.attr('type') == 'checkbox' && !item.is(":checked")){
							value = false;
						}
						if (value) {
							if (!Title) {
								Title = strip_tags(value);
							} else {
								Subtitle = strip_tags(value);
							}
						} 
					}
				}
			});
			
			// Append all the relevant fields into the matrix field so that it gets saved on submit with the rest of the form.
			LastDialog.find("input, textarea, select").each(function() {
				if ($(this).attr("type") != "submit") {
					if ($(this).is("textarea") && $(this).css("display") == "none") {
						var mce = tinyMCE.get($(this).attr("id"));
						if (mce) {
							mce.save();
							mce.remove();
						}
					}
					$(this).hide().get(0).className = "";
					entry.append($(this));
				}
			});

			if (Style == "list") {
				entry.find("p").html(Title + '<small>' + Subtitle + '</small>');
				entry.append('<input type="hidden" name="' + Key + '[' + Count + '][__internal-title]" value="' + htmlspecialchars(Title) + '" />');
				entry.append('<input type="hidden" name="' + Key + '[' + Count + '][__internal-subtitle]" value="' + htmlspecialchars(Subtitle) + '" />');
			} else {
				entry.find("h4").html(Title + '<input type="hidden" name="' + Key + '[' + Count + '][__internal-title]" value="' + htmlspecialchars(Title) + '" />');
				entry.find("p").html(Subtitle + '<input type="hidden" name="' + Key + '[' + Count + '][__internal-subtitle]" value="' + htmlspecialchars(Subtitle) + '" />');
			}

			return entry;
		}

		function removeDialog() {
			LastDialog.parents("div").remove();
			LastDialog.remove();
			$(".bigtree_dialog_overlay").last().remove();
			BigTree.zIndex -= 2;
		}

		// Init routine
		Key = settings.key;
		Columns = settings.columns;
		Container = $(settings.selector);
		AddButton = Container.find(".add_item");
		List = Container.find(".contain, ul");
		Count = List.find("article, li").length;
		Max = settings.max ? settings.max : 0;
		Style = settings.style ? settings.style : "list";
		NoItemsMessage = Container.find("section");

		// If they've exceed or are at the max, hide the add button
		if (Max && Count >= Max) {
			AddButton.hide();
		}

		Container.on("click",".add_item",addItem)
				 .on("click",".icon_edit",editItem)
				 .on("click",".icon_delete",deleteItem);

		if (Style == "list") {
			List.sortable({ containment: "parent", handle: ".icon_sort", items: "li", placeholder: "ui-sortable-placeholder", tolerance: "pointer" });
		} else {
			List.sortable({ containment: "parent", handle: ".icon_drag", items: "article", placeholder: "ui-sortable-placeholder", tolerance: "pointer" });
		}

		return { Container: Container, Count: Count, Key: Key, List: List, addItem: addItem };

	})(jQuery,settings);
};

var BigTree = {
	Busy: false,
	Growling: false,
	GrowlTimer: false,
	GrowlClearTimer: false,
	ReadyCountdown: 0,
	ReadyHooks: [],
	TabIndexDepth: 0,
	ZIndex: 1000,

	cleanHref: function(href) {
		return href.substr(href.indexOf("#")+1);
	},

	cleanObject: function(o) {
		if (typeof o != "object") {
			return o;
		}

		if (Object.prototype.toString.call(o) === '[object Array]') {
			var j = [];
			for (var i = 0; i < o.length; i++) {
				if (typeof o[i] != "undefined") {
					j[j.length] = o[i];
				}
			}
		} else {
			var j = {};
			for (var i in o) {
				j[i] = BigTree.cleanObject(o[i]);
			}
		}
		return j;
	},

	formHooks: function(selector) {
		// Undo any existing click handlers.
		$(selector).off("click");
		
		// Remove Existing Uploads
		$(selector).on("click",".remove_resource",function() {
			var p = $(this).parent();
			if (p.hasClass("currently_file")) {
				p.remove();
			} else {
				p.hide().find("input, img").remove();
			}
			return false;
		// Form Image Browser
		}).on("click",".form_image_browser",function() {
			var options = $.parseJSON($(this).attr("data-options"));
			var field = $(this).attr("href").substr(1);
			BigTreeFileManager.formOpen("image",field,options);
			return false;
		}).on("click",".date_picker_clear",function() {
			$(this).siblings('input').val("");
			$(this).siblings('.date_picker_inline, .date_time_picker_inline').find('.ui-state-default.ui-state-active').removeClass('ui-state-active');
		});
		
		// Pickers
		$(".date_picker").datepicker({ dateFormat: BigTree.dateFormat, duration: 200, showAnim: "slideDown" });
		$(".time_picker").timepicker({ duration: 200, showAnim: "slideDown", ampm: true, hourGrid: 6, minuteGrid: 10, timeFormat: "hh:mm tt" });
		$(".date_time_picker").datetimepicker({ dateFormat: BigTree.dateFormat, timeFormat: "hh:mm tt", duration: 200, showAnim: "slideDown", ampm: true, hourGrid: 6, minuteGrid: 10 });	
		
		// Inline pickers
		$(".date_picker_inline").each(function() {
			$(this).datepicker({ dateFormat: BigTree.dateFormat, defaultDate: $(this).attr("data-date"), onSelect: function(dateText) {
				$(this).prev("input").val(dateText);
			}});
			
			if (typeof $(this).attr("data-date") == 'undefined' || $(this).attr("data-date") == '') {
				$(this).find('.ui-state-default.ui-state-highlight.ui-state-active').removeClass('ui-state-active');
			}
		});
		$(".time_picker_inline").each(function() {
			var hour = $(this).attr("data-hour");
			var minute = $(this).attr("data-minute");
			$(this).timepicker({ timeFormat: "hh:mm tt", hour: hour, minute: minute, ampm: true, hourGrid: 6, minuteGrid: 10, onSelect: function(dateText) {
				$(this).prev("input").val(dateText);
			}});
		});
		$(".date_time_picker_inline").each(function() {
			$(this).datetimepicker({ dateFormat: BigTree.dateFormat, timeFormat: "hh:mm tt", defaultDate: $(this).attr("data-date"), ampm: true, hour: $(this).attr("data-hour"), minute: $(this).attr("data-minute"), hourGrid: 6, minuteGrid: 10, onSelect: function(dateText) {
				$(this).prev("input").val(dateText);
			}});
			
			if (typeof $(this).attr("data-date") == 'undefined' || $(this).attr("data-date") == '') {
				$(this).find('.ui-state-default.ui-state-highlight.ui-state-active').removeClass('ui-state-active');
			}
		});
	},

	growl: function(title,message,time,type) {
		if (!time) {
			time = 2000;
		}

		if (!type) {
			type = "success";
		}

		// Reset the fade out timer, show the growl container
		clearTimeout(BigTree.GrowlTimer);
		clearTimeout(BigTree.GrowlClearTimer);
		var growl_box = $("#growl").addClass("visible");

		// If a growl already exists, fade that one out and slide it up adding another to the box
		if (BigTree.Growling) {
			var last_growl = growl_box.find("article:last-child");
			last_growl.addClass("hidden").css({ marginTop: (last_growl.outerHeight() * -1) + "px" });
			growl_box.append($('<article><a class="close" href="#"></a><span class="icon_growl_' + type + '"></span><section><h3>' + title + '</h3><p>' + message + '</p></section></article>'));
		// If a visible growl doesn't exist, replace the node contents (helps if a bunch of growls filled the DOM via fast clicking rather than adding another)
		} else {
			growl_box.html('<article><a class="close" href="#"></a><span class="icon_growl_' + type + '"></span><section><h3>' + title + '</h3><p>' + message + '</p></section></article>');
		}

		// Fade in takes half a second, so we set the timer for the time given + 500 milliseconds
		BigTree.Growling = true;
		BigTree.GrowlTimer = setTimeout(function() {
			growl_box.removeClass("visible");
			BigTree.Growling = false;
			BigTree.GrowlClearTimer = setTimeout(function() {
				growl_box.find("article").remove();
			}, 500);
		}, time + 500);
	},

	hookReady: function(callback) {
		BigTree.ReadyHooks.push(callback);
	},

	ready: function() {
		// We need to wait for something to finish loading
		if (BigTree.ReadyCountdown > 0) {
			setTimeout(BigTree.ready,100);
		} else {
			for (var i = 0; callback = BigTree.ReadyHooks[i]; i++) {
				callback();
			}
			BigTree.ReadyHooks = [];
		}
	},
	
	setPageCount: function(selector,pages,current_page) {
		// We have to have at least one page.
		if (pages == 0) {
			pages = 1;
		}

		// Figure out what previous and next buttons should do.
		if (current_page == 1) {
			var prev_page = 1;
		} else {
			var prev_page = current_page - 1;
		}
		if (current_page == pages) {
			var next_page = pages;
		} else {
			var next_page = current_page + 1;
		}
		
		// If we have 10 or less pages, just draw them all.
		if (pages < 11) {
			var start_page = 1;
			var end_page = pages;
		// Otherwise we need to figure out where we are...
		} else {
			if (current_page < 7) {
				var start_page = 1;
				var end_page = 9;
			} else if (current_page > pages - 7) {
				var start_page = pages - 9;
				var end_page = pages;
			} else {
				var start_page = current_page - 4;
				var end_page = current_page + 5;
			}
		}

		var content = '<a class="first" href="#' + prev_page + '"><span>&laquo;</span></a>';
		if (start_page > 1) {
			content += '<a class="ellipsis" href="#1">…</a>';
		}
		for (var i = start_page; i <= end_page; i++) {
			content += '<a href="#' + i + '"';
			if (i == current_page) {
				content += ' class="active"';
			}
			content += '>' + i + '</a>';
		}
		if (end_page < pages) {
			content += '<a class="ellipsis" href="#' + pages + '">…</a>';
		}
		content += '<a class="last" href="#' + next_page + '"><span>&raquo;</span></a>';
		
		$(selector).html(content);
		if (pages == 1) {
			$(selector).hide();
		} else {
			$(selector).show();
		}
	},

	windowHeight: function() {
		return $(window).innerHeight();
	},

	windowWidth: function() {
		return $("body").innerWidth();
	}
}

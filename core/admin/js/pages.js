var BigTreePages = {
	calloutCount: 0,
	calloutDescription: false,
	calloutNumber: 0,
	currentCallout: false,
	currentPage: false,
	currentTemplate: false,
	pageTitleDidFocus: false,
	rememberedExternal: false,
	rememberedTemplate: false,

	init: function() {
		// Handle the template selection boxes
		$(".box_select").click(function() {
			// Uncheck external link but remember what it was in case they switch back.
			BigTreePages.rememberedExternal = $("#external_link").removeClass("active").val();
			$("#external_link").val("");
			
			// Uncheck redirect lower
			$("input[name=redirect_lower]").attr("checked",false).next("div").find("a").removeClass("checked");
			
			$("#template").val($(this).attr("href").substr(1));
			$(".box_select").removeClass("active");
			$(this).addClass("active");
			
			return false;
		});
		
		// If the redirect lower checkbox is checked, remove selected template, otherwise reset it
		$("input[name=redirect_lower]").click(function() {
			if ($(this).attr("checked")) {
				BigTreePages.rememberedTemplate = $("#template").val();
				BigTreePages.rememberedExternal = $("#external_link").val();
				$(".box_select").removeClass("active");
				$("#template").val("!");
				$("#external_link").removeClass("active").val("");
			} else {
				if (BigTreePages.rememberedTemplate == "" && BigTreePages.rememberedExternal) {
					$("#external_link").addClass("active").val(BigTreePages.rememberedExternal);
					$("#template").val();
				} else if (BigTreePages.rememberedTemplate) {
					$("#template").val(BigTreePages.rememberedTemplate);
					$(".box_select[href=#" + BigTreePages.rememberedTemplate + "]").addClass("active");
				} else {
					$("#template").val($(".box_select").eq(0).addClass("active").attr("href").substr(1));
				}
			}
		});
		
		// Watch for entry into the external link field and switch to a blank template.
		$("input[name=external]").bind("focus",function() {
			// Backup the existing one.
			BigTreePages.rememberedTemplate = $("#template").val();
			if (BigTreePages.rememberedTemplate && BigTreePages.rememberedExternal) {
				$(this).val(BigTreePages.rememberedExternal);
			}
			$(".box_select").removeClass("active");
			$("#template").val("");
			if (BigTreePages.rememberedTemplate == "!") {
				$("input[name=redirect_lower]").attr("checked",false).next("div").find("a").removeClass("checked");
			}
		}).bind("blur",function() {
			if ($(this).val() == "") {
				$("#template").val(BigTreePages.rememberedTemplate);
				if (BigTreePages.rememberedTemplate == "!") {
			  		$("input[name=redirect_lower]").attr("checked",true).next("div").find("a").addClass("checked");
				} else {
					$(".box_select[href=#" + BigTreePages.rememberedTemplate + "]").addClass("active");
				}
			}
		});
		
		// Walk through each step of page creation.
		$(".next").click(function() {
			nav = $(".container nav a");
			
			tab = $(".container nav a.active");
			tab.removeClass("active");
			next = tab.next("a").addClass("active");
			
			$("#" + next.attr("href").substr(1)).show();
			$("#" + tab.attr("href").substr(1)).hide();
			
			if (nav.index(tab) == nav.length - 2) {
				$(this).hide();
			}
			
			return false;
		});
		
		// Setup the date pickers
		$("#publish_at, #expire_at").datepicker({ duration: 200, showAnim: "slideDown" });
		
		// Tagger
		BigTreeTagAdder.init("bigtree_tag_browser");
		
		// Watch for changes in the template, update the Content tab.
		checkTimer = setInterval(BigTreePages.CheckTemplate,500);
		
		$(".save_and_preview").click(function() {
			sform = $(this).parents("form");
			sform.attr("action","admin_root/pages/update/?preview=true");
			sform.submit();
			
			return false;
		});
		
		// Observe the Nav Title for auto filling the Page Title the first time around.
		$("#nav_title").keyup(function() {
			if (!$("#page_title").get(0).defaultValue && !BigTreePages.pageTitleDidFocus) {
				$("#page_title").val($("#nav_title").val());
			}
		});
		$("#page_title").focus(function() { BigTreePages.pageTitleDidFocus = true; });
		
		// Callouts
		$("#template_type").on("click","#bigtree_callouts .add_callout",function() {
			$.ajax("admin_root/ajax/pages/add-callout/", { type: "POST", data: { count: BigTreePages.calloutCount }, complete: function(response) {
				new BigTreeDialog("Add Callout",response.responseText,function(e) {		
					e.preventDefault();
					
					article = BigTreePages.GetCallout();
					if (!article) {
						return false;
					}
	
					// Add the callout and hide the dialog.
					$("#bigtree_callouts .contain").append(article);
					last_dialog.parents("div").remove();
					last_dialog.remove();
					$(".bigtree_dialog_overlay").last().remove();
					
					// Fill out the callout description.
					article.find("h4").html(BigTreePages.calloutDescription + '<input type="hidden" name="callouts[' + BigTreePages.calloutNumber + '][display_title]" value="' + htmlspecialchars(BigTreePages.calloutDescription) + '" />');
					
					BigTreePages.calloutCount++;
					
					return false;
				},"callout",false,false,true);
			}});
			
			return false;
		}).on("click","#bigtree_callouts .icon_edit",function() {
			BigTreePages.currentCallout = $(this).parents("article");
			
			$.ajax("admin_root/ajax/pages/edit-callout/", { type: "POST", data: { count: BigTreePages.calloutCount, data: BigTreePages.currentCallout.find(".callout_data").val() }, complete: function(response) {
				new BigTreeDialog("Edit Callout",response.responseText,function(e) {
					e.preventDefault();
					
					article = BigTreePages.GetCallout();
					if (!article) {
						return false;
					}
	
					BigTreePages.currentCallout.replaceWith(article);
					last_dialog.parents("div").remove();
					last_dialog.remove();
					$(".bigtree_dialog_overlay").last().remove();
					
					article.find("h4").html(BigTreePages.calloutDescription + '<input type="hidden" name="callouts[' + BigTreePages.calloutNumber + '][display_title]" value="' + htmlspecialchars(BigTreePages.calloutDescription) + '" />');
					
					BigTreePages.calloutCount++;
					
					return false;
				},"callout",false,false,true);
			}});
			
			return false;
		}).on("click","#bigtree_callouts .icon_delete",function() {
			new BigTreeDialog("Delete Callout", '<p class="confirm">Are you sure you want to delete this callout?</p>', $.proxy(function() {
				$(this).parents("article").remove();
			},this),"delete",false,"OK");
			return false;
		});
		
		$("#bigtree_callouts .contain").sortable({ containment: "parent", handle: ".icon_drag", items: "article", placeholder: "ui-sortable-placeholder", tolerance: "pointer" });
	},

	CheckTemplate: function() {
		tval = $("input[name=template]");
		if (tval.length) {
			if (BigTreePages.currentTemplate != tval.val()) {
				// Unload all TinyMCE fields.
				if (tinyMCE) {
					for (id in BigTree.TinyMCEFields) {
						tinyMCE.execCommand('mceFocus', false, BigTree.TinyMCEFields[id]);
						tinyMCE.execCommand("mceRemoveControl", false, BigTree.TinyMCEFields[id]);
					}
				}
				BigTreePages.currentTemplate = tval.val();
				if (BigTreePages.currentPage !== false) {
					$("#template_type").load("admin_root/ajax/pages/get-template-form/", { page: BigTreePages.currentPage, template: BigTreePages.currentTemplate }, function() { BigTreeCustomControls("#template_type"); });
				} else {
					$("#template_type").load("admin_root/ajax/pages/get-template-form/", { template: BigTreePages.currentTemplate }, function() { BigTreeCustomControls("#template_type"); });
				}
			}
		}
	},

	GetCallout: function() {
		last_dialog = $(".bigtree_dialog_form").last();

		// Validate required fields.
		v = new BigTreeFormValidator(last_dialog);
		if (!v.validateForm(false,true)) {
			return false;
		}
		
		article = $('<article>');
		article.html('<h4></h4><p>' + $("#callout_type select").get(0).options[$("#callout_type select").get(0).selectedIndex].text + '</p><div class="bottom"><span class="icon_drag"></span><a href="#" class="icon_delete"></a></div>');
		
		BigTreePages.calloutNumber = last_dialog.find("input.callout_count").val();
		// Try our best to find some way to describe the callout
		BigTreePages.calloutDescription = "";
		BigTreePages.calloutDescription_field = last_dialog.find("[name='" + last_dialog.find(".display_field").val() + "']");
		if (BigTreePages.calloutDescription_field.is('select')) {
			BigTreePages.calloutDescription = BigTreePages.calloutDescription_field.find("option:selected").text();
		} else {
			BigTreePages.calloutDescription = BigTreePages.calloutDescription_field.val();
		}
		if ($.trim(BigTreePages.calloutDescription) == "") {
			BigTreePages.calloutDescription = last_dialog.find(".display_default").val();
		}
		
		// Append all the relevant fields into the callout field so that it gets saved on submit with the rest of the form.
		last_dialog.find("input, textarea, select").each(function() {
			if ($(this).attr("type") != "submit") {
				if ($(this).is("textarea") && $(this).css("display") == "none") {
					var mce = tinyMCE.get($(this).attr("id"));
					if (mce) {
						mce.save();
						tinyMCE.execCommand('mceRemoveControl',false,$(this).attr("id"));
					}
				}
				$(this).hide();
				article.append($(this));
			}
		});

		return article;
	}
};

$(document).ready(BigTreePages.init);
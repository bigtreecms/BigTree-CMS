var BigTreePages = {
	calloutCount: 0,
	calloutDescription: false,
	calloutNumber: 0,
	currentCallout: false,
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
		BigTree.localTimer = setInterval(BigTreePages.CheckTemplate,500);
		
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
	},

	CheckTemplate: function() {
		tval = $("input[name=template]");
		if (tval.length) {
			if (BigTree.currentPageTemplate != tval.val()) {
				// Unload all TinyMCE fields.
				if (tinyMCE) {
					for (id in BigTree.TinyMCEFields) {
						tinyMCE.execCommand('mceFocus', false, BigTree.TinyMCEFields[id]);
						tinyMCE.execCommand("mceRemoveControl", false, BigTree.TinyMCEFields[id]);
					}
				}
				BigTree.currentPageTemplate = tval.val();
				if (BigTree.currentPage !== false) {
					$("#template_type").load("admin_root/ajax/pages/get-template-form/", { page: BigTree.currentPage, template: BigTree.currentPageTemplate }, function() { BigTreeCustomControls("#template_type"); });
				} else {
					$("#template_type").load("admin_root/ajax/pages/get-template-form/", { template: BigTree.currentPageTemplate }, function() { BigTreeCustomControls("#template_type"); });
				}
			}
		}
	}
};

$(document).ready(BigTreePages.init);
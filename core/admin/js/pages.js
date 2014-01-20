var BigTreePages = {
	calloutCount: 0,
	calloutDescription: false,
	calloutNumber: 0,
	currentCallout: false,
	pageTitleDidFocus: false,

	init: function() {
		$("input[name=redirect_lower]").click(function() {
			if ($(this).attr("checked")) {
				$("#template_select").get(0).customControl.disable();
				$("#external_link").attr("disabled","disabled");
				$("#new_window").get(0).customControl.disable();
			} else {
				$("#template_select").get(0).customControl.enable();
				$("#external_link").removeAttr("disabled");
				$("#new_window").get(0).customControl.enable();
			}
		});
		$("input[name=external]").on("keyup",function() {
			if ($(this).val()) {
				$("#template_select").get(0).customControl.disable();
			} else {
				$("#template_select").get(0).customControl.enable();
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
		var template_select = $("select[name=template]");
		if (template_select.length) {
			if ($("#redirect_lower").attr("checked")) {
				var current_template = "!";
			} else if ($("#external_link").val()) {
				var current_template = "";
			} else {
				var current_template = template_select.val();
			}
			if (BigTree.currentPageTemplate != current_template) {
				// Unload all TinyMCE fields.
				if (tinyMCE) {
					for (id in BigTree.TinyMCEFields) {
						tinyMCE.execCommand('mceFocus', false, BigTree.TinyMCEFields[id]);
						tinyMCE.execCommand("mceRemoveControl", false, BigTree.TinyMCEFields[id]);
					}
				}
				BigTree.currentPageTemplate = current_template;
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
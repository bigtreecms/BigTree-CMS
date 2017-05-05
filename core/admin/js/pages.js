var BigTreePages = (function() {
	var ExternalLink;
	var NewWindow;
	var PageTitle;
	var PageTitleDidFocus = false;
	var RedirectLower;
	var SaveAndPreview;
	var TemplateSelect;
	var Timer;

	function init() {
		PageTitle = $("#page_title");
		RedirectLower = $("input[name=redirect_lower]");
		ExternalLink = $("input[name=external]");
		NewWindow = $("#new_window");
		TemplateSelect = $("select[name=template]");
		SaveAndPreview = $(".save_and_preview");

		RedirectLower.click(function() {
			if ($(this).prop("checked")) {
				TemplateSelect.get(0).customControl.disable();
				ExternalLink.prop("disabled", true);
				NewWindow.get(0).customControl.disable();
				SaveAndPreview.hide();
			} else {
				TemplateSelect.get(0).customControl.enable();
				ExternalLink.prop("disabled", false);
				NewWindow.get(0).customControl.enable();
				SaveAndPreview.show();
			}
		});

		ExternalLink.on("keyup",function() {
			if ($(this).val()) {
				TemplateSelect.get(0).customControl.disable();
				SaveAndPreview.hide();
			} else {
				TemplateSelect.get(0).customControl.enable();
				SaveAndPreview.show();
			}
		});
		
		// Tagger
		BigTreeTagAdder.init();
		
		// Watch for changes in the template, update the Content tab.
		Timer = setInterval(checkTemplate, 500);

		SaveAndPreview.click(function(ev) {
			ev.preventDefault();
			ev.stopPropagation();

			var form = $(this).parents("form");
			form.attr("action", "admin_root/pages/update/?preview=true");
			form.submit();
		});
		
		// Observe the Nav Title for auto filling the Page Title the first time around.
		$("#nav_title").keyup(function() {
			if (!PageTitle.get(0).defaultValue && !PageTitleDidFocus) {
				PageTitle.val($(this).val());
			}
		});

		PageTitle.focus(function() {
			PageTitleDidFocus = true;
		});
	}

	function checkTemplate() {
		var current_template;

		if (TemplateSelect.length) {
			if (RedirectLower.prop("checked")) {
				current_template = "!";
			} else if (ExternalLink.val()) {
				current_template = "";
			} else {
				current_template = TemplateSelect.val();
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

	return { init: init };
})();

$(document).ready(BigTreePages.init);
var BigTreePages = (function() {
	var CurrentPage;
	var CurrentPageTemplate;
	var ExternalLinkField;
	var ExternalTimer;
	var FooterInputs;
	var NavTitle;
	var NewWindowCheckbox;
	var NewWindowControl;
	var LockTimer;
	var PageTitle;
	var PageTitleTimer;
	var RedirectLowerField;
	var RedirectLowerFieldControl;
	var SaveAndPreviewButton;
	var TemplateSelect;
	var TemplateSelectControl;
	var TemplateTimer;
	var TrunkField;

	function init(settings) {
		CurrentPage = settings.page;
		CurrentPageTemplate = settings.template;
		ExternalLinkField = $("#external_link");
		FooterInputs = $(".js-pages-form-footer input");
		NavTitle = $("#nav_title");
		NewWindowCheckbox = $("#new_window");
		PageTitle = $("#page_title");
		SaveAndPreviewButton = $(".save_and_preview");
		TemplateSelect = $("#template_select");
		TemplateSelectControl = TemplateSelect.get(0).customControl;
		TrunkField = $("#page_field_trunk");

		// Homepage editing won't have these controls
		if (NewWindowCheckbox.length) {
			NewWindowControl = NewWindowCheckbox.get(0).customControl;
			RedirectLowerField = $("#redirect_lower");
			RedirectLowerFieldControl = RedirectLowerField.get(0).customControl;

			RedirectLowerField.on("click", function() {
				if ($(this).prop("checked")) {
					TemplateSelectControl.disable();
					ExternalLinkField.prop("disabled", true);
					NewWindowControl.disable();
				} else {
					TemplateSelectControl.enable();
					ExternalLinkField.prop("disabled", false);
					NewWindowControl.enable();
				}
			});

			ExternalTimer = setInterval(checkExternal, 300);
		}

		// Tagger
		BigTreeTagAdder.init();

		// Watch for changes in the template, update the Content tab.
		TemplateTimer = setInterval(checkTemplate, 300);

		if (!PageTitle.get(0).defaultValue) {
			PageTitleTimer = setInterval(checkPageTitle, 300);

			PageTitle.focus(function() {
				if (PageTitleTimer) {
					clearInterval(PageTitleTimer);
				}
			});
		}

		SaveAndPreviewButton.on("click", function(ev) {
			ev.preventDefault();

			if ($(this).hasClass("disabled")) {
				return;
			}

			submit();

			var sform = $(this).parents("form");
			sform.attr("action","admin_root/pages/update/?preview=true");
			sform.submit();
		});

		FooterInputs.on("click", submit);

		// Setup lock timer if we're editing a page
		if (CurrentPage) {
			LockTimer = setInterval(function() {
				$.secureAjax('admin_root/ajax/refresh-lock/', {
					type: 'POST',
					data: {
						table: 'bigtree_pages',
						id: CurrentPage
					}
				});
			}, 60000);
		}
	}

	function submit() {
		SaveAndPreviewButton.addClass("disabled");
		FooterInputs.addClass("disabled");
		$(".next").addClass("disabled");
		$(".js-pages-form-footer").append('<span class="button_loader"></span>');
	}

	function checkExternal() {
		if (ExternalLinkField.val()) {
			TemplateSelectControl.disable();
			RedirectLowerFieldControl.disable();

			if (TrunkField.length) {
				TrunkField.get(0).customControl.disable();
			}
		} else {
			RedirectLowerFieldControl.enable();

			if (TrunkField.length) {
				TrunkField.get(0).customControl.enable();
			}

			if (!RedirectLowerField.prop("checked")) {
				TemplateSelectControl.enable();
			}
		}
	}

	function checkPageTitle() {
		PageTitle.val(NavTitle.val());
	}

	function checkTemplate() {
		if (TemplateSelect.length) {
			var current_template;

			if (typeof RedirectLowerField !== "undefined" && RedirectLowerField.prop("checked")) {
				current_template = "!";
			} else if (ExternalLinkField.val()) {
				current_template = "";
			} else {
				current_template = TemplateSelect.val();
			}

			if (CurrentPageTemplate !== current_template) {
				// Unload all TinyMCE fields.
				if (tinyMCE) {
					for (var x = 0; x < BigTree.TinyMCEFields.length; x++) {
						tinyMCE.execCommand('mceFocus', false, BigTree.TinyMCEFields[x]);
						tinyMCE.execCommand("mceRemoveControl", false, BigTree.TinyMCEFields[x]);
					}
				}

				CurrentPageTemplate = current_template;

				if (CurrentPage !== false) {
					$("#template_type").load("admin_root/ajax/pages/get-template-form/", {
						page: CurrentPage,
						template: CurrentPageTemplate
					}, function() {
						BigTreeCustomControls("#template_type");
					});
				} else {
					$("#template_type").load("admin_root/ajax/pages/get-template-form/", {
						template: CurrentPageTemplate
					}, function() {
						BigTreeCustomControls("#template_type");
					});
				}
			}
		}
	}

	return { init: init };
})();

var swfu;
var files_queued = 0;
var active_callout_edit;
var callout_desc;
var last_seo_data = false;
var pageTitleDidFocus = false;
var rememberedTemplate;
var rememberedExternal;

$(document).ready(function() {
	
	// Handle the template selection boxes
	$(".box_select").click(function() {
		// Uncheck external link but remember what it was in case they switch back.
		rememberedExternal = $("#external_link").removeClass("active").val();
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
		// For some reason the click is firing before the checked status changes, so we're doing the opposite of what makes sense.
		if (!$(this).attr("checked")) {
			rememberedTemplate = $("#template").val();
			rememberedExternal = $("#external_link").val();
			$(".box_select").removeClass("active");
			$("#template").val("!");
			$("#external_link").removeClass("active").val("");
		} else {
			if (rememberedTemplate == "" && rememberedExternal) {
				$("#external_link").addClass("active").val(rememberedExternal);
				$("#template").val();
			} else if (rememberedTemplate) {
				$("#template").val(rememberedTemplate);
				$(".box_select[href=#" + rememberedTemplate + "]").addClass("active");
			} else {
				$("#template").val($(".box_select").eq(0).addClass("active").attr("href").substr(1));
			}
		}
	});
	
	// Watch for entry into the external link field and switch to a blank template.
	$("input[name=external]").bind("focus",function() {
		// Backup the existing one.
		rememberedTemplate = $("#template").val();
		if (rememberedTemplate && rememberedExternal) {
			$(this).val(rememberedExternal);
		}
		$(".box_select").removeClass("active");
		$("#template").val("");
		if (rememberedTemplate == "!") {
			$("input[name=redirect_lower]").attr("checked",false).next("div").find("a").removeClass("checked");
		}
	}).bind("blur",function() {
		if ($(this).val() == "") {
			$("#template").val(rememberedTemplate);
			if (rememberedTemplate == "!") {
		  		$("input[name=redirect_lower]").attr("checked",true).next("div").find("a").addClass("checked");
			} else {
				$(".box_select[href=#" + rememberedTemplate + "]").addClass("active");
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
	BigTreeTagAdder.init(0,false,"bigtree_tag_browser");
	
	// Watch for changes in the template, update the Content tab.
	checkTimer = setInterval(checkTemplate,500);
	
	$(".save_and_preview").click(function() {
		sform = $(this).parents("form");
		sform.attr("action","admin_root/pages/update/preview/");
		sform.submit();
		
		return false;
	});
	
	// Observe the Nav Title for auto filling the Page Title the first time around.
	$("#nav_title").keyup(function() {
		if (!$("#page_title").get(0).defaultValue && !pageTitleDidFocus) {
			$("#page_title").val($("#nav_title").val());
		}
	});
	$("#page_title").focus(function() { pageTitleDidFocus = true; });
	
	// Callouts
	$("#bigtree_callouts .add_callout").click(function() {
		$.ajax("admin_root/ajax/pages/add-callout/", { type: "POST", data: { count: callout_count }, complete: function(response) {
			new BigTreeDialog("Add Callout",response.responseText,function(e) {		
				e.preventDefault();
				
				li = BigTree.GetCallout();
				if (!li) {
					return false;
				}

				// Add the callout and hide the dialog.
				$("#bigtree_callouts ul").append(li);
				last_dialog.parents("div").remove();
				last_dialog.remove();
				$(".bigtree_dialog_overlay").last().remove();
				
				// Fill out the callout description.
				li.find("h4").html(callout_desc + '<input type="hidden" name="callouts[' + callout_number + '][display_title]" value="' + htmlspecialchars(callout_desc) + '" />');
				
				callout_count++;
				
				return false;
			},"callout",false,false,true);
		}});
		
		return false;
	});
	
	$("#bigtree_callouts").on("click",".icon_edit",function() {
		active_callout_edit = $(this).parents("li");
		
		$.ajax("admin_root/ajax/pages/edit-callout/", { type: "POST", data: { count: callout_count, data: active_callout_edit.find(".callout_data").val() }, complete: function(response) {
			new BigTreeDialog("Edit Callout",response.responseText,function(e) {
				e.preventDefault();
				
				li = BigTree.GetCallout();
				if (!li) {
					return false;
				}

				active_callout_edit.replaceWith(li);
				last_dialog.parents("div").remove();
				last_dialog.remove();
				$(".bigtree_dialog_overlay").last().remove();
				
				li.find("h4").html(callout_desc + '<input type="hidden" name="callouts[' + callout_number + '][display_title]" value="' + htmlspecialchars(callout_desc) + '" />');
				
				callout_count++;
				
				return false;
			},"callout",false,false,true);
		}});
		
		return false;
	});
	
	$("#bigtree_callouts").on("click",".icon_delete",function() {
		new BigTreeDialog("Delete Callout", '<p class="confirm">Are you sure you want to delete this callout?</p>', $.proxy(function() {
			$(this).parents("li").remove();
		},this),"delete",false,"OK");
		return false;
	});
	
	$("#bigtree_callouts ul").sortable({ containment: "parent", handle: ".icon_drag", items: "li", placeholder: "ui-sortable-placeholder", tolerance: "pointer" });
});

function checkTemplate() {
	tval = $("input[name=template]");
	if (tval.length) {
		if (template != tval.val()) {
			template = tval.val();
			if (page !== false) {
				$("#template_type").load("admin_root/ajax/pages/get-template-form/", { page: page, template: template });
			} else {
				$("#template_type").load("admin_root/ajax/pages/get-template-form/", { template: template });
			}
		}
	}
}
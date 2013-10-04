<?
	$module = $admin->getModule($_GET["module"]);
	$table = $_GET["table"];
	
	if (!$title) {
		$title = $module["name"];
		if (substr($title,-3,3) == "ies") {
			$title = substr($title,0,-3)."y";
		} else {
			$title = rtrim($title,"s");
		}
	}
	
	$title = htmlspecialchars(urldecode($title));
	
	$cached_types = $admin->getCachedFieldTypes();
	$types = $cached_types["module"];
?>
<div class="container">
	<header>
		<p>Step 2: Creating Your Form</p>
	</header>
	<form method="post" action="<?=$developer_root?>modules/designer/form-create/" class="module">
		<input type="hidden" name="module" value="<?=$module["id"]?>" />
		<input type="hidden" name="table" value="<?=$table?>" />
		<section>
			<p class="error_message"<? if (!count($e)) { ?> style="display: none;"<? } ?>>Errors found! Please fix the highlighted fields before submitting.</p>
			<div class="left">
				<fieldset>
					<label class="required">Item Title <small>(for example, "Question" as in "Adding Question")</small></label>
					<input type="text" class="required" name="title" value="<?=$title?>" />
				</fieldset>
			</div>
		</section>
		<section id="field_area" class="sub">
			<fieldset<? if (isset($e["fields"])) { ?> class="form_error"<? } ?>>
				<label class="required">Fields<? if (isset($e["fields"])) { ?><span class="form_error_reason">One Or More Fields Required</span><? } ?></label>
				<div class="form_table">
					<header>
						<a class="add add_geocoding" href="#"><span></span>Geocoding</a>
						<a class="add add_many_to_many" href="#"><span></span>Many-To-Many</a>
						<a class="add add_field" href="#"><span></span>Field</A>
					</header>
					<div class="labels">
						<span class="developer_resource_form_title">Title</span>
						<span class="developer_resource_form_subtitle">Subtitle</span>
						<span class="developer_resource_type">Type</span>
						<span class="developer_resource_action">Edit</span>
						<span class="developer_resource_action">Delete</span>
					</div>
					<ul id="resource_table"></ul>
				</div>
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Create" />
		</footer>
	</form>
</div>

<script>
	new BigTreeFormValidator("form.module");

	BigTree.localCurrentFieldKey = false;
	BigTree.localMTMCount = 0;
	BigTree.localKeyCount = 0;
	BigTree.localHooks = function() {
		$("#resource_table").sortable({ axis: "y", containment: "parent", handle: ".icon_sort", items: "li", placeholder: "ui-sortable-placeholder", tolerance: "pointer" });
		BigTreeCustomControls();
	};
	
	$(".form_table").on("click",".icon_settings",function() {
		BigTree.localCurrentFieldKey = $(this).attr("name");
		
		$.ajax("<?=ADMIN_ROOT?>ajax/developer/load-field-options/", { type: "POST", data: { type: $("#type_" + BigTree.localCurrentFieldKey).val(), data: $("#options_" + BigTree.localCurrentFieldKey).val() }, complete: function(response) {
			new BigTreeDialog("Field Options",response.responseText,function(data) {
				$.ajax("<?=ADMIN_ROOT?>ajax/developer/save-field-options/?key=" + BigTree.localCurrentFieldKey, { type: "POST", data: data });
			});
		}});
		
		return false;
	}).on("click",".icon_delete",function() {
		new BigTreeDialog("Delete Field",'<p class="confirm">Are you sure you want to delete this field?</p>',$.proxy(function() {
			li = $(this).parents("li");
			title = li.find("input").val();
			type = li.find(".developer_resource_type").find("input,select").eq(0).val();
			if (title) {
				key = $(this).attr("name");
				if (key != "geocoding" && type != "many-to-many") {
					sel = $("#unused_field").get(0);
					sel.options[sel.options.length] = new Option(key,title,false,false);
				}
			}
			li.remove();
		},this),"delete",false,"OK");
		
		return false;
	});
	
	$(".add_field").click(function() {
		BigTree.localKeyCount++;
		c = BigTree.localKeyCount;

		li = $('<li id="row_' + c + '">');
		li.html('<section class="developer_resource_form_title"><span class="icon_sort"></span><input type="text" name="titles[' + c + ']" value="" class="required" /></section><section class="developer_resource_form_subtitle"><input type="text" name="subtitles[' + c + ']" value="" /></section><section class="developer_resource_type"><select name="type[' + c + ']" id="type_' + c + '"><? foreach ($types as $k => $v) { ?><option value="<?=$k?>"><?=$v?></option><? } ?></select><a href="#" class="options icon_settings" name="' + c + '"></a><input type="hidden" name="options[' + c + ']" value="" id="options_' + c + '" /></section><section class="developer_resource_action"><a href="#" class="icon_delete" name="' + c + '"></a></section>');
		
		$("#resource_table").append(li);
		BigTree.localHooks();
		
		return false;
	});
	
	$(".add_geocoding").click(function() {
		li = $('<li id="row_geocoding">');
		li.html('<section class="developer_resource_form_title"><span class="icon_sort"></span><input type="text" name="titles[geocoding]" value="Geocoding" disabled="disabled" /></section><section class="developer_resource_form_subtitle"><input type="hidden" name="subtitles[geocoding]" value="" />&nbsp;</section><section class="developer_resource_type"><input name="type[geocoding]" id="type_geocoding" type="hidden" />&nbsp;<a href="#" class="options icon_settings" name="geocoding"></a><input type="hidden" name="options[geocoding]" value="" id="options_geocoding" /></section><section class="developer_resource_action"><a href="#" class="icon_delete" name="geocoding"></a></section>');
		
		$("#resource_table").append(li);
		BigTree.localHooks();
		
		return false;
	});
	
	$(".add_many_to_many").click(function() {
		BigTree.localMTMCount++;
			
		li = $('<li id="mtm_row_' + BigTree.localMTMCount + '">');
		li.html('<section class="developer_resource_form_title"><span class="icon_sort"></span><input type="text" name="titles[mtm_' + BigTree.localMTMCount + ']" value="" /></section><section class="developer_resource_form_subtitle"><input type="text" name="subtitles[mtm_' + BigTree.localMTMCount + ']" value="" /></section><section class="developer_resource_type"><input name="type[mtm_' + BigTree.localMTMCount + ']" id="type_mtm_' + BigTree.localMTMCount + '" type="hidden" value="many-to-many" /><p>Many To Many</p><a href="#" class="options icon_settings" name="mtm_' + BigTree.localMTMCount + '"></a><input type="hidden" name="options[mtm_' + BigTree.localMTMCount + ']" value="" id="options_mtm_' + BigTree.localMTMCount + '" /></section><section class="developer_resource_action"><a href="#" class="icon_delete" name="mtm_' + BigTree.localMTMCount + '"></a></section>');
		
		$("#resource_table").append(li);
		BigTree.localHooks();
		
		return false;
	});
	
	BigTree.localHooks();
</script>
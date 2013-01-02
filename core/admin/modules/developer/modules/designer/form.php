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

	var current_editing_key;
	var mtm_count = 0;
	var key = 0;
	
	$(".icon_settings").live("click",function() {
		key = $(this).attr("name");
		current_editing_key = key;
		
		$.ajax("<?=ADMIN_ROOT?>ajax/developer/load-field-options/", { type: "POST", data: { type: $("#type_" + key).val(), data: $("#options_" + key).val() }, complete: function(response) {
			new BigTreeDialog("Field Options",response.responseText,function(data) {
				$.ajax("<?=ADMIN_ROOT?>ajax/developer/save-field-options/?key=" + current_editing_key, { type: "POST", data: data });
			});
		}});
		
		return false;
	});
		
	$(".icon_delete").live("click",function() {
		new BigTreeDialog("Delete Resource",'<p class="confirm">Are you sure you want to delete this field?</p>',$.proxy(function() {
			li = $(this).parents("li");
			title = li.find("input").val();
			if (title) {
				key = $(this).attr("name");
				if (key != "geocoding") {
					sel = $("#unused_field").get(0);
					sel.options[sel.options.length] = new Option(key,title,false,false);
				}
			}
			li.remove();
		},this),"delete",false,"OK");
		
		return false;
	});
	
	$(".add_field").click(function() {
		key++;
		
		li = $('<li id="row_' + key + '">');
		li.html('<section class="developer_resource_form_title"><span class="icon_sort"></span><input type="text" name="titles[' + key + ']" value="" class="required" /></section><section class="developer_resource_form_subtitle"><input type="text" name="subtitles[' + key + ']" value="" /></section><section class="developer_resource_type"><select name="type[' + key + ']" id="type_' + key + '"><? foreach ($types as $k => $v) { ?><option value="<?=$k?>"><?=$v?></option><? } ?></select><a href="#" class="options icon_settings" name="' + key + '"></a><input type="hidden" name="options[' + key + ']" value="" id="options_' + key + '" /></section><section class="developer_resource_action"><a href="#" class="icon_delete" name="' + key + '"></a></section>');
		
		$("#resource_table").append(li);
		_local_hooks();
		
		return false;
	});
	
	$(".add_geocoding").click(function() {
		li = $('<li id="row_geocoding">');
		li.html('<section class="developer_resource_form_title"><span class="icon_sort"></span><input type="text" name="titles[geocoding]" value="Geocoding" disabled="disabled" /></section><section class="developer_resource_form_subtitle"><input type="hidden" name="subtitles[geocoding]" value="" />&nbsp;</section><section class="developer_resource_type"><input name="type[geocoding]" id="type_geocoding" type="hidden" />&nbsp;<a href="#" class="options icon_settings" name="geocoding"></a><input type="hidden" name="options[geocoding]" value="" id="options_geocoding" /></section><section class="developer_resource_action"><a href="#" class="icon_delete" name="geocoding"></a></section>');
		
		$("#resource_table").append(li);
		_local_hooks();
		
		return false;
	});
	
	$(".add_many_to_many").click(function() {
		mtm_count++;
			
		li = $('<li id="mtm_row_' + mtm_count + '">');
		li.html('<section class="developer_resource_form_title"><span class="icon_sort"></span><input type="text" name="titles[mtm_' + mtm_count + ']" value="" /></section><section class="developer_resource_form_subtitle"><input type="text" name="subtitles[mtm_' + mtm_count + ']" value="" /></section><section class="developer_resource_type"><input name="type[mtm_' + mtm_count + ']" id="type_mtm_' + mtm_count + '" type="hidden" value="many_to_many" /><p>Many To Many</p><a href="#" class="options icon_settings" name="mtm_' + mtm_count + '"></a><input type="hidden" name="options[mtm_' + mtm_count + ']" value="" id="options_mtm_' + mtm_count + '" /></section><section class="developer_resource_action"><a href="#" class="icon_delete" name="mtm_' + mtm_count + '"></a></section>');
		
		$("#resource_table").append(li);
		_local_hooks();
		
		return false;
	});
	
	function _local_hooks() {
		$("#resource_table").sortable({ axis: "y", containment: "parent", handle: ".icon_sort", items: "li", placeholder: "ui-sortable-placeholder", tolerance: "pointer" });
		BigTreeCustomControls();
	}
	
	_local_hooks();
</script>
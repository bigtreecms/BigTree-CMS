<?
	if (isset($_GET["table"])) {
		$table = $_GET["table"];
	}
	
	$reserved = $admin->ReservedColumns;

	$used = array();
	$unused = array();

	$tblfields = array();
	// To tolerate someone selecting the blank spot again when creating a view.
	if ($table) {
		$table_description = BigTree::describeTable($table);
	} else {
		$table_description = array("columns" => array());
	}
	foreach ($table_description["columns"] as $column => $details) {
		$tblfields[] = $column;
	}
	
	if (isset($fields)) {
		foreach ($fields as $key => $field) {
			$used[] = $key;
		}
		// Figure out the fields we're not using so we can offer them back.
		foreach ($tblfields as $field) {
			if (!in_array($field,$reserved) && !in_array($field,$used)) {
				$unused[] = array("field" => $field, "title" => ucwords(str_replace("_"," ",$field)));
			}
		}		
	}
	
	$preview_field = isset($view["preview_field"]) ? $view["preview_field"] : "id";
	
	$unused[] = array("field" => "— Custom —", "title" => "");
	if (count($tblfields)) {
?>
<fieldset id="fields"<? if ($type == "images" || $type == "images-grouped") { ?> style="display: none;"<? } ?>>
	<label>Fields</label>
	
	<div class="form_table">
		<header></header>
		<div class="labels">
			<span class="developer_view_title">Title</span>
			<span class="developer_view_parser">Parser</span>
			<span class="developer_resource_action">Delete</span>
		</div>
		<ul id="sort_table">
			<?
				// If we're loading an existing data set.
				$mtm_count = 0;
				if (isset($fields)) {
					foreach ($fields as $key => $field) {
						$used[] = $key;
			?>
			<li id="row_<?=$key?>">
				<input type="hidden" name="fields[<?=$key?>][width]" value="<?=$field["width"]?>" />
				<section class="developer_view_title"><span class="icon_sort"></span><input type="text" name="fields[<?=$key?>][title]" value="<?=$field["title"]?>" /></section>
				<section class="developer_view_parser"><input type="text" name="fields[<?=$key?>][parser]" value="<?=htmlspecialchars($field["parser"])?>" class="parser" placeholder="PHP code to transform $value (which contains the column value.)" /></section>
				<section class="developer_resource_action"><a href="#" class="icon_delete"></a></section>
			</li>
			<?
					}			
				// Otherwise we're loading a new data set based on a table.
				} else {
					foreach ($tblfields as $key) {
						if (!in_array($key,$reserved)) {
			?>
			<li id="row_<?=$key?>">
				<section class="developer_view_title"><span class="icon_sort"></span><input type="text" name="fields[<?=$key?>][title]" value="<?=htmlspecialchars(ucwords(str_replace("_"," ",$key)))?>" /></section>
				<section class="developer_view_parser"><input type="text" name="fields[<?=$key?>][parser]" value="" class="parser" placeholder="PHP code to transform $value (which contains the column value.)" /></section>
				<section class="developer_resource_action"><a href="#" class="icon_delete"></a></section>
			</li>
			<?	
						}
					}
				}
			?>
		</ul>
	</div>
</fieldset>
<fieldset class="last">
	<label>Actions <small>(click to deselect, drag bottom tab to rearrange)</small></label>
	<div class="developer_action_list">
		<ul>
			<?
				$used_actions = array();
				if (!empty($actions)) {
					foreach ($actions as $key => $action) {
						if ($action != "on") {
							$data = json_decode($action,true);
							$key = $data["route"];
							$class = $data["class"];
						} else {
							$class = "icon_$key";
							if ($key == "feature" || $key == "approve") {
								$class .= " icon_".$key."_on";
							}
						}
						$used_actions[] = $key;
			?>
			<li>
				<input class="custom_control" type="checkbox" name="actions[<?=$key?>]" checked="checked" value="<?=htmlspecialchars($action)?>" />
				<a href="#" class="action active">
					<span class="<?=$class?>"></span>
				</a>
				<div class="handle"><? if ($action != "on") { ?><span class="edit"></span><? } ?></div>
			</li>
			<?
					}
				}
				foreach ($admin->ViewActions as $key => $action) {
					if (!in_array($key,$used_actions) && (in_array($action["key"],$tblfields) || isset($bigtree["module_designer_view"]))) {
						$checked = false;
						if (isset($actions[$key]) || (!isset($actions) && !isset($bigtree["module_designer_view"])) || (isset($bigtree["module_designer_view"]) && ($key == "edit" || $key == "delete"))) {
							$checked = true;
						}
			?>
			<li>
				<input class="custom_control" type="checkbox" name="actions[<?=$key?>]" value="on" <? if ($checked) { ?>checked="checked" <? } ?>/>
				<a href="#" class="action<? if ($checked) { ?> active<? } ?>">
					<span class="<?=$action["class"]?>"></span>
				</a>
				<div class="handle"></div>
			</li>
			<?
					}
				}
			?>
		</ul>
		<a href="#" class="button add_action">Add</a>
	</div>
</fieldset>

<script>
	var _local_BigTreeCustomAction = false;

	$(".form_table").on("click",".icon_delete",function() {
		tf = $(this).parents("li").find("section").find("input");
		
		title = tf.val();
		key = tf.attr("name").substr(7);
		key = key.substr(0,key.length-8);
		
		fieldSelect.addField(key,title);

		$(this).parents("li").remove();		
		return false;
	});
		
	
	$(".developer_action_list").on("click",".action",function() {
		if ($(this).hasClass("active")) {
			$(this).removeClass("active");
			$(this).prev("input").attr("checked",false);
		} else {
			$(this).addClass("active");
			$(this).prev("input").attr("checked","checked");
		}
		
		return false;
	}).on("click",".edit",function() {
		_local_BigTreeCustomAction = $(this).parents("li");
		j = $.parseJSON(_local_BigTreeCustomAction.find("input").val());
		new BigTreeDialog("Edit Custom Action",'<fieldset><label>Name</label><input type="text" name="name" value="' + htmlspecialchars(j.name) + '" /></fieldset><fieldset><label>Icon Class <small>(i.e. icon_preview)</small></label><input type="text" name="class" value="' + htmlspecialchars(j.class) + '" /></fieldset><fieldset><label>Route</label><input type="text" name="route" value="' + htmlspecialchars(j.route) + '" /></fieldset><fieldset><label>Link Function <small>(if you need more than simply /route/id/)</small></label><input type="text" name="function" value="' + htmlspecialchars(j.function) + '" /></fieldset>',function(data) {
			_local_BigTreeCustomAction.load("<?=ADMIN_ROOT?>ajax/developer/add-view-action/", data);
		});
	}).sortable({ axis: "x", containment: "parent", items: "li", placeholder: "ui-sortable-placeholder", tolerance: "pointer" });
		
	$(".add_action").click(function() {
		new BigTreeDialog("Add Custom Action",'<fieldset><label>Name</label><input type="text" name="name" /></fieldset><fieldset><label>Icon Class <small>(i.e. icon_preview)</small></label><input type="text" name="class" /></fieldset><fieldset><label>Route</label><input type="text" name="route" /></fieldset><fieldset><label>Link Function <small>(if you need more than simply /route/id/)</small></label><input type="text" name="function" /></fieldset>',function(data) {
			li = $('<li>');
			li.load("<?=ADMIN_ROOT?>ajax/developer/add-view-action/", data);
			$(".developer_action_list li:first-child").before(li);
		});
		
		return false;
	});
	
	BigTree.localHooks = function() {
		$("#sort_table").sortable({ axis: "y", containment: "parent", handle: ".icon_sort", items: "li", placeholder: "ui-sortable-placeholder", tolerance: "pointer" });
	};
	
	BigTree.localHooks();
	
	fieldSelect = new BigTreeFieldSelect(".form_table header",<?=json_encode($unused)?>,function(el,fs) {
		title = el.title;
		key = el.field;
		
		if (title) {
			li = $('<li id="row_' + key + '">');
			li.html('<section class="developer_view_title"><span class="icon_sort"></span><input type="text" name="fields[' + key + '][title]" value="' + title + '" /></section><section class="developer_view_parser"><input type="text" class="parser" name="fields[' + key + '][parser]" value="" placeholder="PHP code to transform $value (which contains the column value.)"/></section><section class="developer_resource_action"><a href="#" class="icon_delete"></a></section>');
		
			fs.removeCurrent();
			$("#sort_table").append(li);
			BigTree.localHooks();
		} else {
			new BigTreeDialog("Add Custom Column",'<fieldset><label>Column Key <small>(must be unique)</small></label><input type="text" name="key" /></fieldset><fieldset><label>Column Title</label><input type="text" name="title" /></fieldset>',function(data) {
				key = htmlspecialchars(data.key);
				title = htmlspecialchars(data.title);
				
				li = $('<li id="row_' + key + '">');
				li.html('<section class="developer_view_title"><span class="icon_sort"></span><input type="text" name="fields[' + key + '][title]" value="' + title + '" /></section><section class="developer_view_parser"><input type="text" class="parser" name="fields[' + key + '][parser]" value="" placeholder="PHP code to transform $value (which contains the column value.)" /></section><section class="developer_resource_action"><a href="#" class="icon_delete"></a></section>');
				$("#sort_table").append(li);
				BigTree.localHooks();
			});
		}
	});
</script>
<?
	} else {
?>
<p>Please choose a table to populate this area.</p>
<?
	}
?>
<?
	if (isset($_GET["table"])) {
		$table = $_GET["table"];
	}
	
	$reserved = $admin->ReservedColumns;

	$used = array();
	$unused = array();

	$tblfields = array();
	$table_description = BigTree::describeTable($table);
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
	
	$cached_types = $admin->getCachedFieldTypes();
	$types = $cached_types["module"];
	
	$unused[] = array("field" => "-- Custom --", "title" => "");
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
					if (!isset($table)) {
						$table = $_POST["table"];
					}
					$q = sqlquery("describe ".$table);
					while ($f = sqlfetch($q)) {
						if (!in_array($f["Field"],$reserved)) {
							$key = $f["Field"];
			?>
			<li id="row_<?=$key?>">
				<section class="developer_view_title"><span class="icon_sort"></span><input type="text" name="fields[<?=$key?>][title]" value="<?=htmlspecialchars(ucwords(str_replace("_"," ",$f["Field"])))?>" /></section>
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
<fieldset>
	<label>Actions <small>(click to deselect)</small></label>
	<ul class="developer_action_list">
		<?
			if (!empty($actions)) {
				foreach ($actions as $action) {
					if ($action != "on") {
						$data = json_decode($action,true);
		?>
		<li>
			<input class="custom_control" type="checkbox" name="actions[<?=$data["route"]?>]" checked="checked" value="<?=htmlspecialchars($action)?>" />
			<a href="#" class="action active">
				<span class="<?=$data["class"]?>"></span>
			</a>
		</li>
		<?
					}
				}
			}
			foreach ($admin->ViewActions as $key => $action) {
				if (in_array($action["key"],$tblfields) || isset($allow_all_actions)) {
					$checked = false;
					if (isset($actions[$key]) || (!isset($actions) && !isset($allow_all_actions)) || (isset($allow_all_actions) && ($key == "edit" || $key == "delete"))) {
						$checked = true;
					}
		?>
		<li>
			<input class="custom_control" type="checkbox" name="actions[<?=$key?>]" value="on" <? if ($checked) { ?>checked="checked" <? } ?>/>
			<a href="#" class="action<? if ($checked) { ?> active<? } ?>">
				<span class="<?=$action["class"]?>"></span>
			</a>
		</li>
		<?
				}
			}
		?>
		<li><a href="#" class="button add_action">Add</a></li>
	</ul>
</fieldset>

<script>
	var current_editing_key;
	
	$(".form_table .icon_delete").live("click",function() {
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
	});
		
	$(".add_action").click(function() {
		new BigTreeDialog("Add Custom Action",'<fieldset><label>Action Name</label><input type="text" name="name" /></fieldset><fieldset><label>Action Image Class <small>(i.e. button_edit)</small></label><input type="text" name="class" /></fieldset><fieldset><label>Action Route</label><input type="text" name="route" /></fieldset><fieldset><label>Link Function <small>(if you need more than simply /route/id/)</small></label><input type="text" name="function" /></fieldset>',function(data) {
			li = $('<li>');
			li.load("<?=ADMIN_ROOT?>ajax/developer/add-view-action/", data);
			$(".developer_action_list li:first-child").before(li);
		});
		
		return false;
	});
	
	function _local_hooks() {
		$("#sort_table").sortable({ axis: "y", containment: "parent", handle: ".icon_sort", items: "li", placeholder: "ui-sortable-placeholder", tolerance: "pointer" });
	}
	
	_local_hooks();
	
	fieldSelect = new BigTreeFieldSelect(".form_table header",<?=json_encode($unused)?>,function(el,fs) {
		title = el.title;
		key = el.field;
		
		if (title) {
			li = $('<li id="row_' + key + '">');
			li.html('<section class="developer_view_title"><span class="icon_sort"></span><input type="text" name="fields[' + key + '][title]" value="' + title + '" /></section><section class="developer_view_parser"><input type="text" class="parser" name="fields[' + key + '][parser]" value="" placeholder="PHP code to transform $value (which contains the column value.)"/></section><section class="developer_resource_action"><a href="#" class="icon_delete"></a></section>');
		
			fs.removeCurrent();
			$("#sort_table").append(li);
			_local_hooks();
		} else {
			new BigTreeDialog("Add Custom Column",'<fieldset><label>Column Key <small>(must be unique)</small></label><input type="text" name="key" /></fieldset><fieldset><label>Column Title</label><input type="text" name="title" /></fieldset>',function(data) {
				key = htmlspecialchars(data.key);
				title = htmlspecialchars(data.title);
				
				li = $('<li id="row_' + key + '">');
				li.html('<section class="developer_view_title"><span class="icon_sort"></span><input type="text" name="fields[' + key + '][title]" value="' + title + '" /></section><section class="developer_view_parser"><input type="text" class="parser" name="fields[' + key + '][parser]" value="" placeholder="PHP code to transform $value (which contains the column value.)" /></section><section class="developer_resource_action"><a href="#" class="icon_delete"></a></section>');
				$("#sort_table").append(li);
				_local_hooks();
			});
		}
	});
</script>
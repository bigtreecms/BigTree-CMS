<?
	$id = end($path);
	$breadcrumb[] = array("title" => "Edit Module", "link" => "#");
	
	$mod = $admin->getModule($id);
	
	$actions = $admin->getModuleActions($id);
	$views = array();
	$forms = array();
	foreach ($actions as $action) {
		if ($action["view"]) {
			$view = BigTreeAutoModule::getView($action["view"]);
			if (!in_array($view,$views)) {
				$views[] = $view;
			}
		}
		if ($action["form"]) {
			$form = BigTreeAutoModule::getForm($action["form"]);
			if (!in_array($form,$forms)) {
				$forms[] = $form;
			}
		}
	}
	
	$gbp = is_array($mod["gbp"]) ? $mod["gbp"] : array();
	
	$groups = $admin->getModuleGroups();
?>

<h1><span class="icon_developer_modules"></span><?=$mod["name"]?></h1>
<? include BigTree::path("admin/modules/developer/modules/_nav.php"); ?>
<div class="form_container">
	<form method="post" action="<?=$developer_root?>modules/update/<?=end($path)?>/" enctype="multipart/form-data" class="module left">
		<section>
			<div class="left">
				<fieldset>
					<label class="required">Name</label>
					<input name="name" type="text" value="<?=$mod["name"]?>" class="required" />
				</fieldset>
			</div>
			<br class="clear" /><br />
			<fieldset class="clear developer_module_group">
				<label>Group <small>(if a new group name is chosen, the select box is ignored)</small></label>
				<input name="group_new" type="text" placeholder="New Group" />
				<span>OR</span> 
				<select name="group_existing">
					<option value="0"></option>
					<? foreach ($groups as $group) { ?>
					<option value="<?=$group["id"]?>"<? if ($group["id"] == $mod["group"]) { ?> selected="selected"<? } ?>><?=htmlspecialchars($group["name"])?></option>
					<? } ?>
				</select>
			</fieldset>
			<div class="left">
				<fieldset>
					<label>Class Name <small>(only change this if you renamed your class manually)</small></label>
					<input name="class" type="text" value="<?=htmlspecialchars($mod["class"])?>" />
				</fieldset>
				<fieldset>
					<input type="checkbox" name="gbp[enabled]" id="gbp_on" <? if ($gbp["enabled"]) { ?>checked="checked" <? } ?>/><label class="for_checkbox">Enable Advanced Permissions</label>
				</fieldset>
			</div>
		</section>
		<section class="sub" id="gbp"<? if (!$gbp["enabled"]) { ?> style="display: none;"<? } ?>>
			<div class="left">
				<fieldset>
					<label>Grouping Name <small>(i.e. "Category")</small></label>
					<input type="text" name="gbp[name]" value="<?=htmlspecialchars($gbp["name"])?>" />
				</fieldset>
			</div>
			<br class="clear" />
			<article>
				<fieldset>
					<label>Main Table</label>
					<select name="gbp[table]" class="table_select">
						<option></option>
						<? BigTree::getTableSelectOptions($gbp["table"]) ?>
					</select>
				</fieldset>
				<fieldset name="gbp[group_field]">
					<label>Main Field</label>
					<div>
						<? if ($gbp["table"]) { ?>
						<select name="gbp[group_field]">
							<? BigTree::getFieldSelectOptions($gbp["table"],$gbp["group_field"]) ?>
						</select>
						<? } else { ?>
						&mdash;
						<? } ?>
					</div>
				</fieldset>
			</article>
			<article>
				<fieldset>
					<label>Other Table</label>
					<select name="gbp[other_table]" class="table_select">
						<option></option>
						<? BigTree::getTableSelectOptions($gbp["other_table"]) ?>
					</select>
				</fieldset>
				<fieldset name="gbp[title_field]">
					<label>Title Field</label>
					<div>
						<? if ($gbp["title_field"]) { ?>
						<select name="gbp[title_field]">
							<? BigTree::getFieldSelectOptions($gbp["other_table"],$gbp["title_field"]) ?>
						</select>
						<? } else { ?>
						&mdash;
						<? } ?>
					</div>
				</fieldset>
			</article>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Update" />	
		</footer>
	</form>
</div>

<div class="table">
	<summary>
		<a href="<?=$developer_root?>modules/views/add/<?=$mod["id"]?>/" class="add">Add</a>
		<h2>Module Views <small><?=$mod["name"]?></small></h2>
	</summary>
	<header>
		<span class="developer_view_name">View Name</span>
		<span class="view_action">Style</span>
		<span class="view_action">Edit</span>
		<span class="view_action">Delete</span>
	</header>
	<ul id="module_views">
		<? foreach ($views as $view) { ?>
		<li>
			<section class="developer_view_name">View <?=$view["title"]?></section>
			<section class="view_action">
				<? if ($view["type"] != "images" && $view["type"] != "images-grouped") { ?>
				<a href="<?=$developer_root?>modules/views/style/<?=$view["id"]?>/" class="icon_preview"></a>
				<? } else { ?>
				<span class="icon_preview disabled_icon"></span>
				<? } ?>
			</section>
			<section class="view_action"><a href="<?=$developer_root?>modules/views/edit/<?=$view["id"]?>/" class="icon_edit"></a></section>
			<section class="view_action"><a href="<?=$developer_root?>modules/views/delete/<?=$id?>/<?=$view["id"]?>/" class="icon_delete"></a></section>
		</li>
		<? } ?>
	</ul>
</div>

<div class="table">
	<summary>
		<a href="<?=$developer_root?>modules/forms/add/<?=$mod["id"]?>/" class="add">Add</a>
		<h2>Module Forms <small><?=$mod["name"]?></small></h2>
	</summary>
	<header>
		<span class="developer_templates_name">Form Name</span>
		<span class="view_action">Edit</span>
		<span class="view_action">Delete</span>
	</header>
	<ul id="module_forms">
		<? foreach ($forms as $form) { ?>
		<li>
			<section class="developer_templates_name">Add/Edit <?=$form["title"]?></section>
			<section class="view_action"><a href="<?=$developer_root?>modules/forms/edit/<?=$form["id"]?>/" class="icon_edit"></a></section>
			<section class="view_action"><a href="<?=$developer_root?>modules/forms/delete/<?=$id?>/<?=$form["id"]?>/" class="icon_delete"></a></section>
		</li>
		<? } ?>
	</ul>
</div>

<div class="table">
	<summary>
		<a href="<?=$developer_root?>modules/actions/add/<?=$mod["id"]?>/" class="add">Add</a>
		<h2>Module Actions <small><?=$mod["name"]?></small></h2>
	</summary>
	<header>
		<span class="developer_templates_name">Action</span>
		<span class="view_action">Edit</span>
		<span class="view_action">Delete</span>
	</header>
	<ul id="actions">
		<? foreach ($actions as $action) { ?>
		<li id="row_<?=$action["id"]?>">
			<section class="developer_templates_name"><span class="icon_sort"></span><?=$action["name"]?></section>
			<section class="view_action"><a href="<?=$developer_root?>modules/actions/edit/<?=$action["id"]?>/" class="icon_edit"></a></section>
			<section class="view_action"><a href="<?=$developer_root?>modules/actions/delete/<?=$id?>/<?=$action["id"]?>/" class="icon_delete"></a></section>
		</li>
		<? } ?>
	</ul>
</div>

<? include BigTree::path("admin/modules/developer/modules/_module-add-edit-js.php") ?>

<script type="text/javascript">
	$("#actions").sortable({ axis: "y", containment: "parent", handle: ".icon_sort", items: "li", placeholder: "ui-sortable-placeholder", tolerance: "pointer", update: function() {
		$.ajax("<?=$admin_root?>ajax/developer/order-module-actions/?sort=" + escape($("#actions").sortable("serialize"))); 
	}});

	$(".table .icon_delete").click(function() {
		new BigTreeDialog("Delete Item",'<p class="confirm">Are you sure you want to delete this?',$.proxy(function() {
			document.location.href = $(this).attr("href");
		},this),"delete",false,"OK");
		
		return false;
	});
</script>
<?
	$id = end($bigtree["path"]);	
	$module = $admin->getModule($id);
	
	$actions = $admin->getModuleActions($id);
	$views = array();
	$forms = array();
	$embeds = $admin->getModuleEmbedForms("title",$id);
	$reports = array();
	$actions_in_nav = array();
	$actions_not_in_nav = array();
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
		if ($action["report"]) {
			$report = BigTreeAutoModule::getReport($action["report"]);
			if (!in_array($report,$reports)) {
				$reports[] = $report;
			}
		}
		if ($action["in_nav"]) {
			$actions_in_nav[] = $action;
		} else {
			$actions_not_in_nav[] = $action;
		}
	}
	
	$gbp = is_array($module["gbp"]) ? $module["gbp"] : array("enabled" => false, "name" => "", "table" => "", "group_field" => "", "other_table" => "", "title_field" => "");
	
	$groups = $admin->getModuleGroups("name ASC");
?>
<div class="container">
	<form method="post" action="<?=DEVELOPER_ROOT?>modules/update/<?=end($bigtree["path"])?>/" enctype="multipart/form-data" class="module left">
		<section>
			<div class="left">
				<fieldset>
					<label class="required">Name</label>
					<input name="name" type="text" value="<?=$module["name"]?>" class="required" />
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
					<option value="<?=$group["id"]?>"<? if ($group["id"] == $module["group"]) { ?> selected="selected"<? } ?>><?=$group["name"]?></option>
					<? } ?>
				</select>
			</fieldset>
			<div class="left">
				<fieldset>
					<label>Class Name <small>(only change this if you renamed your class manually)</small></label>
					<input name="class" type="text" value="<?=htmlspecialchars($module["class"])?>" />
				</fieldset>
			</div>
			
			<br class="clear" />
			<fieldset>
		        <label class="required">Icon</label>
		        <input type="hidden" name="icon" id="selected_icon" value="<?=$module["icon"]?>" />
		        <ul class="developer_icon_list">
		        	<? foreach ($admin->IconClasses as $class) { ?>
		        	<li>
		        		<a href="#<?=$class?>"<? if ($class == $module["icon"]) { ?> class="active"<? } ?>><span class="icon_small icon_small_<?=$class?>"></span></a>
		        	</li>
		        	<? } ?>
		        </ul>
		    </fieldset>
			
			<fieldset>
				<input type="checkbox" name="gbp[enabled]" id="gbp_on" <? if (isset($gbp["enabled"]) && $gbp["enabled"]) { ?>checked="checked" <? } ?>/>
				<label class="for_checkbox">Enable Advanced Permissions</label>
			</fieldset>
		</section>
		<? include BigTree::path("admin/modules/developer/modules/_gbp.php") ?>
		<footer>
			<input type="submit" class="button blue" value="Update" />	
		</footer>
	</form>
</div>

<div class="table">
	<summary>
		<a href="<?=DEVELOPER_ROOT?>modules/views/add/?module=<?=$module["id"]?>" class="add"><span></span>Add</a>
		<h2><span class="views"></span>Views</h2>
	</summary>
	<header>
		<span class="developer_view_name">Title</span>
		<span class="view_action" style="width: 120px;">Actions</span>
	</header>
	<ul id="module_views">
		<? foreach ($views as $view) { ?>
		<li>
			<section class="developer_view_name">View <?=$view["title"]?></section>
			<section class="view_action">
				<? if ($view["type"] != "images" && $view["type"] != "images-grouped") { ?>
				<a href="<?=DEVELOPER_ROOT?>modules/views/style/<?=$view["id"]?>/" class="icon_preview"></a>
				<? } else { ?>
				<span class="icon_preview disabled_icon has_tooltip" data-tooltip="<p>Image-based views cannot be styled.</p>"></span>
				<? } ?>
			</section>
			<section class="view_action"><a href="<?=DEVELOPER_ROOT?>modules/views/edit/<?=$view["id"]?>/" class="icon_edit"></a></section>
			<section class="view_action"><a href="<?=DEVELOPER_ROOT?>modules/views/delete/<?=$view["id"]?>/?module=<?=$id?>" class="icon_delete"></a></section>
		</li>
		<? } ?>
	</ul>
</div>

<div class="table">
	<summary>
		<a href="<?=DEVELOPER_ROOT?>modules/forms/add/?module=<?=$module["id"]?>" class="add"><span></span>Add</a>
		<h2><span class="forms"></span>Forms</h2>
	</summary>
	<header>
		<span class="developer_templates_name">Title</span>
		<span class="view_action" style="width: 80px;">Actions</span>
	</header>
	<ul id="module_forms">
		<? foreach ($forms as $form) { ?>
		<li>
			<section class="developer_templates_name">Add/Edit <?=$form["title"]?></section>
			<section class="view_action"><a href="<?=DEVELOPER_ROOT?>modules/forms/edit/<?=$form["id"]?>/" class="icon_edit"></a></section>
			<section class="view_action"><a href="<?=DEVELOPER_ROOT?>modules/forms/delete/<?=$form["id"]?>/?module=<?=$id?>" class="icon_delete"></a></section>
		</li>
		<? } ?>
	</ul>
</div>

<div class="table">
	<summary>
		<a href="<?=DEVELOPER_ROOT?>modules/embeds/add/?module=<?=$module["id"]?>" class="add"><span></span>Add</a>
		<h2><span class="embeds"></span>Embeddable Forms</h2>
	</summary>
	<header>
		<span class="developer_templates_name">Title</span>
		<span class="view_action" style="width: 80px;">Actions</span>
	</header>
	<ul id="module_forms">
		<? foreach ($embeds as $form) { ?>
		<li>
			<section class="developer_templates_name"><?=$form["title"]?></section>
			<section class="view_action"><a href="<?=DEVELOPER_ROOT?>modules/embeds/edit/<?=$form["id"]?>/" class="icon_edit"></a></section>
			<section class="view_action"><a href="<?=DEVELOPER_ROOT?>modules/embeds/delete/<?=$form["id"]?>/?module=<?=$id?>" class="icon_delete"></a></section>
		</li>
		<? } ?>
	</ul>
</div>

<div class="table">
	<summary>
		<a href="<?=DEVELOPER_ROOT?>modules/reports/add/?module=<?=$module["id"]?>" class="add"><span></span>Add</a>
		<h2><span class="reports"></span>Reports</h2>
	</summary>
	<header>
		<span class="developer_templates_name">Title</span>
		<span class="view_action" style="width: 80px;">Actions</span>
	</header>
	<ul id="module_forms">
		<? foreach ($reports as $report) { ?>
		<li>
			<section class="developer_templates_name"><?=$report["title"]?></section>
			<section class="view_action"><a href="<?=DEVELOPER_ROOT?>modules/reports/edit/<?=$report["id"]?>/" class="icon_edit"></a></section>
			<section class="view_action"><a href="<?=DEVELOPER_ROOT?>modules/reports/delete/<?=$report["id"]?>/?module=<?=$id?>" class="icon_delete"></a></section>
		</li>
		<? } ?>
	</ul>
</div>

<div class="table">
	<summary>
		<a href="<?=DEVELOPER_ROOT?>modules/actions/add/<?=$module["id"]?>/" class="add"><span></span>Add</a>
		<h2><span class="actions"></span>Actions</h2>
	</summary>
	<header>
		<span class="developer_templates_name">Title</span>
		<span class="view_action" style="width: 80px;">Actions</span>
	</header>
	<?
		if (count($actions_in_nav)) {
	?>
	<ul id="actions">
		<? foreach ($actions_in_nav as $action) { ?>
		<li id="row_<?=$action["id"]?>">
			<section class="developer_templates_name"><span class="icon_sort"></span><?=$action["name"]?></section>
			<section class="view_action"><a href="<?=DEVELOPER_ROOT?>modules/actions/edit/<?=$action["id"]?>/" class="icon_edit"></a></section>
			<section class="view_action"><a href="<?=DEVELOPER_ROOT?>modules/actions/delete/<?=$action["id"]?>/?module=<?=$id?>" class="icon_delete"></a></section>
		</li>
		<? } ?>
	</ul>
	<?
		}
		if (count($actions_not_in_nav)) {
	?>
	<ul<? if (count($actions_in_nav)) { ?> class="secondary"<? } ?>>
		<? foreach ($actions_not_in_nav as $action) { ?>
		<li>
			<section class="developer_templates_name"><?=$action["name"]?></section>
			<section class="view_action"><a href="<?=DEVELOPER_ROOT?>modules/actions/edit/<?=$action["id"]?>/" class="icon_edit"></a></section>
			<section class="view_action"><a href="<?=DEVELOPER_ROOT?>modules/actions/delete/<?=$action["id"]?>/?module=<?=$id?>" class="icon_delete"></a></section>
		</li>
		<? } ?>
	</ul>
	<?
		}
	?>
</div>

<? include BigTree::path("admin/modules/developer/modules/_js.php") ?>

<script>
	$("#actions").sortable({ axis: "y", containment: "parent", handle: ".icon_sort", items: "li", placeholder: "ui-sortable-placeholder", tolerance: "pointer", update: function() {
		$.ajax("<?=ADMIN_ROOT?>ajax/developer/order-module-actions/", { type: "POST", data: { sort: $("#actions").sortable("serialize") } }); 
	}});

	$(".table .icon_delete").click(function() {
		new BigTreeDialog("Delete Item",'<p class="confirm">Are you sure you want to delete this?',$.proxy(function() {
			document.location.href = $(this).attr("href");
		},this),"delete",false,"OK");
		
		return false;
	});
</script>
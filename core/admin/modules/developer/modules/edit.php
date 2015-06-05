<?php
	$id = end($bigtree["path"]);	
	$module = $admin->getModule($id);
	$actions = $admin->getModuleActions($id);
	$groups = $admin->getModuleGroups("name ASC");
	$gbp = is_array($module["gbp"]) ? $module["gbp"] : array("enabled" => false, "name" => "", "table" => "", "group_field" => "", "other_table" => "", "title_field" => "");

	// Get a list of interfaces, this is separated out because actions form uses the same logic
	include BigTree::path("admin/modules/developer/modules/_interface-sort.php");

	// Sort actions into visible and non
	$actions_in_nav = $actions_not_in_nav = array();
	foreach ($actions as $action) {
		if ($action["in_nav"]) {
			$actions_in_nav[] = $action;
		} else {
			$actions_not_in_nav[] = $action;
		}
	}	
?>
<div class="container">
	<form method="post" action="<?=DEVELOPER_ROOT?>modules/update/<?=$module["id"]?>/" enctype="multipart/form-data" class="module left">
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
					<?php foreach ($groups as $group) { ?>
					<option value="<?=$group["id"]?>"<?php if ($group["id"] == $module["group"]) { ?> selected="selected"<?php } ?>><?=$group["name"]?></option>
					<?php } ?>
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
		        	<?php foreach (BigTreeAdmin::$IconClasses as $class) { ?>
		        	<li>
		        		<a href="#<?=$class?>"<?php if ($class == $module["icon"]) { ?> class="active"<?php } ?>><span class="icon_small icon_small_<?=$class?>"></span></a>
		        	</li>
		        	<?php } ?>
		        </ul>
		    </fieldset>
			
			<fieldset>
				<input type="checkbox" name="gbp[enabled]" id="gbp_on" <?php if (isset($gbp["enabled"]) && $gbp["enabled"]) { ?>checked="checked" <?php } ?>/>
				<label class="for_checkbox">Enable Advanced Permissions</label>
			</fieldset>
		</section>
		<?php include BigTree::path("admin/modules/developer/modules/_gbp.php") ?>
		<footer>
			<input type="submit" class="button blue" value="Update" />	
		</footer>
	</form>
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
	<?php
		if (count($actions_in_nav)) {
	?>
	<ul id="actions">
		<?php foreach ($actions_in_nav as $action) { ?>
		<li id="row_<?=$action["id"]?>">
			<section class="developer_templates_name"><span class="icon_sort"></span><?=$action["name"]?></section>
			<section class="view_action"><a href="<?=DEVELOPER_ROOT?>modules/actions/edit/<?=$action["id"]?>/" class="icon_edit"></a></section>
			<section class="view_action"><a href="<?=DEVELOPER_ROOT?>modules/actions/delete/<?=$action["id"]?>/?module=<?=$id?>" class="icon_delete"></a></section>
		</li>
		<?php } ?>
	</ul>
	<?php
		}
		if (count($actions_not_in_nav)) {
	?>
	<ul<?php if (count($actions_in_nav)) { ?> class="secondary"<?php } ?>>
		<?php foreach ($actions_not_in_nav as $action) { ?>
		<li>
			<section class="developer_templates_name"><?=$action["name"]?></section>
			<section class="view_action"><a href="<?=DEVELOPER_ROOT?>modules/actions/edit/<?=$action["id"]?>/" class="icon_edit"></a></section>
			<section class="view_action"><a href="<?=DEVELOPER_ROOT?>modules/actions/delete/<?=$action["id"]?>/?module=<?=$id?>" class="icon_delete"></a></section>
		</li>
		<?php } ?>
	</ul>
	<?php
		}
	?>
</div>

<div class="table">
	<summary>
		<a href="<?=DEVELOPER_ROOT?>modules/interfaces/add/?module=<?=$module["id"]?>" class="add"><span></span>Add</a>
		<h2><span class="embeds"></span>Interfaces</h2>
	</summary>
	<?php
		foreach ($interface_list as $key => $type) {
			if (count($type["items"])) {
	?>
	<header>
		<span class="developer_view_name"><?=BigTree::safeEncode($type["name"])?></span>
	</header>
	<ul>
		<?php foreach ($type["items"] as $interface) { ?>
		<li>
			<section class="developer_view_name"><?=$interface["title"]?></section>
			<section class="view_action">
				<?php if ($interface["show_style"]) { ?>
				<a href="<?=DEVELOPER_ROOT?>modules/views/style/<?=$interface["id"]?>/" class="icon_preview"></a>
				<?php } ?>
			</section>
			<section class="view_action"><a href="<?=DEVELOPER_ROOT?>modules/<?=$interface["edit_url"]?>" class="icon_edit"></a></section>
			<section class="view_action"><a href="<?=DEVELOPER_ROOT?>modules/interfaces/delete/<?=$interface["id"]?>/?module=<?=$id?>" class="icon_delete"></a></section>
		</li>
		<?php } ?>
	</ul>
	<?php
			}
		}
	?>
</div>

<?php include BigTree::path("admin/modules/developer/modules/_js.php") ?>

<script>
	$("#actions").sortable({ axis: "y", containment: "parent", handle: ".icon_sort", items: "li", placeholder: "ui-sortable-placeholder", tolerance: "pointer", update: function() {
		$.ajax("<?=ADMIN_ROOT?>ajax/developer/order-module-actions/", { type: "POST", data: { sort: $("#actions").sortable("serialize") } }); 
	}});

	$(".table .icon_delete").click(function() {
		BigTreeDialog({
			title: "Delete Item",
			content: '<p class="confirm">Are you sure you want to delete this?</p>',
			icon: "delete",
			alternateSaveText: "OK",
			callback: $.proxy(function() {
				document.location.href = $(this).attr("href");
			},this)
		});
		
		return false;
	});
</script>
<?
	$module_title = "Add Module Group";
	$breadcrumb[] = array("title" => "Add Group", "link" => "developer/modules/groups/add/");
?>
<h1><span class="modules"></span>Add Group</h1>
<? include BigTree::path("admin/modules/developer/modules/_nav.php"); ?>
<div class="form_container">
	<form method="post" action="<?=$developer_root?>modules/groups/create/" class="module">
		<header><h2>Group Details</h2></header>
		<section>
			<fieldset>
				<label class="required">Name</label>
				<input type="text" name="name" value="" class="required" />
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Create" />
		</footer>
	</form>
</div>
<script type="text/javascript">
	new BigTreeFormValidator("form.module");
</script>
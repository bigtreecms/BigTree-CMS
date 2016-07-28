<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 * @global Page $page
	 */
	
	// Make sure this is a live page.
	if (!is_numeric($page_id->ID)) {
		Auth::stop("Revisions do not function on unpublished pages.", Router::getIncludePath("admin/layouts/_error.php"));
	}

	// Make sure the user is a publisher.
	if ($bigtree["access_level"] != "p") {
		Auth::stop("You must be a publisher to manage revisions.", Router::getIncludePath("admin/layouts/_error.php"));
	}
	
	// Check for a page lock
	$force = isset($_GET["force"]) ? $_GET["force"] : false;
	$lock_id = Lock::enforce("bigtree_pages", $page_id->ID, "admin/modules/pages/_locked.php", $force);
	
	// See if there's a draft copy.
	$draft = $admin->getPageChanges($page_id->ID);
	
	// Get the current published copy.  We're going to just pull a few columns or I'd use getPage here.
	$current_author = $admin->getUser($page_id->LastEditedBy);
	
	// Get all revisions
	$revisions = $admin->getPageRevisions($page_id->ID);

	include Router::getIncludePath("admin/modules/pages/_properties.php");

	if ($draft) {
		$draft_author = $admin->getUser($draft["user"]);
?>
<div class="table">
	<summary><h2><span class="icon_medium_pages"></span><?=Text::translate("Current Draft")?></h2></summary>
	<header>
		<span class="pages_last_edited"><?=Text::translate("Last Edited")?></span>
		<span class="pages_draft_author"><?=Text::translate("Draft Author")?></span>
		<span class="pages_publish"><?=Text::translate("Publish")?></span>
		<span class="pages_edit"><?=Text::translate("Edit")?></span>
		<span class="pages_delete"><?=Text::translate("Delete")?></span>
	</header>
	<ul>
		<li>
			<section class="pages_last_edited"><?=date("F j, Y @ g:ia",strtotime($draft["date"]))?></section>
			<section class="pages_draft_author"><span class="gravatar"><img src="<?=Image::gravatar($draft_author["email"], 36)?>" alt="" /></span><?=$draft_author["name"]?></section>
			<section class="pages_publish"><a class="icon_publish" href="<?=ADMIN_ROOT?>pages/publish-draft/<?=$page_id->ID?>/?draft=<?=$draft["id"]?>"></a></section>
			<section class="pages_edit"><a class="icon_edit" href="<?=ADMIN_ROOT?>pages/edit/<?=$page_id->ID?>/"></a></section>
			<section class="pages_delete"><a class="icon_delete" href="<?=ADMIN_ROOT?>ajax/pages/delete-draft/?id=<?=$page_id->ID?>"></a></section>
		</li>

	</ul>
</div>
<?php
	}
?>
<div class="table">
	<summary><h2><span class="icon_medium_published"></span><?=Text::translate("Published Revisions")?></h2></summary>
	<header>
		<span class="pages_last_edited"><?=Text::translate("Published")?></span>
		<span class="pages_draft_author"><?=Text::translate("Author")?></span>
		<span class="pages_delete"><?=Text::translate("Save")?></span>
		<span class="pages_publish"><?=Text::translate("New Draft")?></span>
		<span class="pages_edit"><?=Text::translate("Delete")?></span>
	</header>
	<ul>
		<li class="active">
			<section class="pages_last_edited"><?=date("F j, Y @ g:ia",strtotime($page_id->UpdatedAt))?></section>
			<section class="pages_draft_author"><span class="gravatar"><img src="<?=Image::gravatar($current_author["email"], 36)?>" alt="" /></span><?=$current_author["name"]?><span class="active_draft"><?=Text::translate("Active")?></span></section>
			<section class="pages_delete"><a href="#" class="icon_save"></a></section>
			<section class="pages_publish"></section>
			<section class="pages_edit"></section>
		</li>
		<?php foreach ($revisions["unsaved"] as $r) { ?>
		<li>
			<section class="pages_last_edited"><?=date("F j, Y @ g:ia",strtotime($r["updated_at"]))?></section>
			<section class="pages_draft_author"><span class="gravatar"><img src="<?=Image::gravatar($r["email"], 36)?>" alt="" /></span><?=$r["name"]?></section>
			<section class="pages_delete"><a href="#<?=$r["id"]?>" class="icon_save"></a></section>
			<section class="pages_publish"><a href="#<?=$r["id"]?>" class="icon_draft"></a></section>
			<section class="pages_edit"><a href="#<?=$r["id"]?>" class="icon_delete"></a></section>
		</li>
		<?php } ?>
	</ul>
</div>
<div class="table">
	<summary><h2><span class="icon_medium_saved"></span><?=Text::translate("Saved Revisions")?></h2></summary>
	<header>
		<span class="pages_last_edited"><?=Text::translate("Saved")?></span>
		<span class="pages_draft_description"><?=Text::translate("Description")?></span>
		<span class="pages_publish"><?=Text::translate("New Draft")?></span>
		<span class="pages_edit"><?=Text::translate("Delete")?></span>
	</header>
	<ul>
		<?php foreach ($revisions["saved"] as $r) { ?>
		<li>
			<section class="pages_last_edited"><?=date("F j, Y @ g:ia",strtotime($r["updated_at"]))?></section>
			<section class="pages_draft_description"><?=$r["saved_description"]?></section>
			<section class="pages_publish"><a href="#<?=$r["id"]?>" class="icon_draft"></a></section>
			<section class="pages_edit"><a href="#<?=$r["id"]?>" class="icon_delete"></a></section>
		</li>
		<?php } ?>
	</ul>
</div>
<script>
	BigTree.localActiveDraft = <?php if ($draft) { ?>true<?php } else { ?>false<?php } ?>;
	BigTree.localLockTimer = setInterval("$.ajax('<?=ADMIN_ROOT?>ajax/refresh-lock/', { type: 'POST', data: { table: 'bigtree_pages', id: '<?=$lock_id?>' } });",60000);
	
	$(".icon_save").click(function() {
		BigTreeDialog({
			title: "<?=Text::translate("Save Revision")?>",
			content: '<fieldset class="last"><label><?=Text::translate("Short Description")?> <small>(<?=Text::translate("quick reminder of what\'s special about this revision")?>)</small></label><input type="text" name="description" /></fieldset>',
			callback: $.proxy(function(d) {
				// If there's no href it's because it's the currently published copy we're saving.
				if (BigTree.cleanHref($(this).attr("href"))) {
					var id = BigTree.cleanHref($(this).attr("href"));
				} else {
					var id = "c<?=$page_id->ID?>";
				}
				$.ajax("<?=ADMIN_ROOT?>ajax/pages/save-revision/", { type: "POST", data: { id: id, description: d.description }});
			},this)
		});
		
		return false;
	});
	
	$(".icon_delete").click(function() {
		var href = $(this).attr("href");
		if (href.substr(0,1) == "#") {
			BigTreeDialog({
				title: "<?=Text::translate("Delete Revision")?>",
				content: '<p class="confirm"><?=Text::translate("Are you sure you want to delete this revision?")?></p>',
				icon: "delete",
				alternateSaveText: "<?=Text::translate("OK")?>",
				callback: $.proxy(function() {
					$.ajax("<?=ADMIN_ROOT?>ajax/pages/delete-revision/?id=" + BigTree.cleanHref($(this).attr("href")));
					$(this).parents("li").remove();
					BigTree.growl("Pages","<?=Text::translate("Deleted Revision")?>");
				},this)
			});
		} else {
			BigTreeDialog({
				title: "<?=Text::translate("Delete Draft")?>",
				content: '<p class="confirm"><?=Text::translate("Are you sure you want to delete this draft?")?></p>',
				icon: "delete",
				alternateSaveText: "<?=Text::translate("OK")?>",
				callback: $.proxy(function() {
					$.ajax($(this).attr("href"));
					$(this).parents("li").remove();
					BigTree.growl("Pages","<?=Text::translate("Deleted Draft")?>");
				},this)
			});
		}
		
		return false;
	});
	
	$(".icon_draft").click(function() {
		if (BigTree.localActiveDraft) {
			BigTreeDialog({
				title: "<?=Text::translate("Use Revision")?>",
				content: '<p class="confirm"><?=Text::translate("Are you sure you want to overwrite your existing draft with this revision?")?></p>',
				alternateSaveText: "<?=Text::translate("Overwrite")?>",
				callback: $.proxy(function() {
					document.location.href = "<?=ADMIN_ROOT?>ajax/pages/use-draft/?id=" + BigTree.cleanHref($(this).attr("href"));
				},this)
			});
		} else {
			document.location.href = "<?=ADMIN_ROOT?>ajax/pages/use-draft/?id=" + BigTree.cleanHref($(this).attr("href"));
		}
		return false;
	});
</script>
<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 * @global Page $page
	 */
	
	// Make sure this is a live page.
	if (!is_numeric($page->ID)) {
		Auth::stop("Revisions do not function on unpublished pages.", Router::getIncludePath("admin/layouts/_error.php"));
	}

	// Make sure the user is a publisher.
	if ($bigtree["access_level"] != "p") {
		Auth::stop("You must be a publisher to manage revisions.", Router::getIncludePath("admin/layouts/_error.php"));
	}
	
	// Check for a page lock
	if (!empty($_GET["force"])) {
		CSRF::verify();
		$force = true;
	} else {
		$force = false;
	}
	
	$lock = Lock::enforce("bigtree_pages", $page->ID, "admin/modules/pages/_locked.php", $force);
	
	// See if there's a draft copy.
	$draft = $page->PendingChange;
	
	// Get the current published copy.  We're going to just pull a few columns or I'd use getPage here.
	$current_author = new User($page->LastEditedBy);
	
	// Get all revisions
	$revisions = PageRevision::listForPage($page->ID, "updated_at DESC");

	include Router::getIncludePath("admin/modules/pages/_properties.php");

	if ($draft) {
		$draft_author = new User($draft->User);
?>
<div class="table">
	<div class="table_summary"><h2><span class="icon_medium_pages"></span><?=Text::translate("Current Draft")?></h2></div>
	<header>
		<span class="pages_last_edited"><?=Text::translate("Last Edited")?></span>
		<span class="pages_draft_author"><?=Text::translate("Draft Author")?></span>
		<span class="pages_publish"><?=Text::translate("Publish")?></span>
		<span class="pages_edit"><?=Text::translate("Edit")?></span>
		<span class="pages_delete"><?=Text::translate("Delete")?></span>
	</header>
	<ul>
		<li>
			<section class="pages_last_edited"><?=date("F j, Y @ g:ia", strtotime($draft->Date))?></section>
			<section class="pages_draft_author"><span class="gravatar"><img src="<?=User::gravatar($draft_author->Email, 36)?>" alt="" /></span><?=$draft_author->Name?></section>
			<section class="pages_publish"><a class="icon_publish" href="<?=ADMIN_ROOT?>pages/publish-draft/<?=$page->ID?>/?draft=<?=$draft->ID?><?php CSRF::drawGETToken(); ?>"></a></section>
			<section class="pages_edit"><a class="icon_edit" href="<?=ADMIN_ROOT?>pages/edit/<?=$page->ID?>/"></a></section>
			<section class="pages_delete"><a class="icon_delete" href="<?=ADMIN_ROOT?>ajax/pages/delete-draft/?id=<?=$page->ID?><?php CSRF::drawGETToken(); ?>"></a></section>
		</li>

	</ul>
</div>
<?php
	}
?>
<div class="table">
	<div class="table_summary"><h2><span class="icon_medium_published"></span><?=Text::translate("Published Revisions")?></h2></div>
	<header>
		<span class="pages_last_edited"><?=Text::translate("Published")?></span>
		<span class="pages_draft_author"><?=Text::translate("Author")?></span>
		<span class="pages_delete"><?=Text::translate("Save")?></span>
		<span class="pages_publish"><?=Text::translate("New Draft")?></span>
		<span class="pages_edit"><?=Text::translate("Delete")?></span>
	</header>
	<ul>
		<li class="active">
			<section class="pages_last_edited"><?=date("F j, Y @ g:ia",strtotime($page->UpdatedAt))?></section>
			<section class="pages_draft_author"><span class="gravatar"><img src="<?=User::gravatar($current_author->Email, 36)?>" alt="" /></span><?=$current_author->Name?><span class="active_draft"><?=Text::translate("Active")?></span></section>
			<section class="pages_delete"><a href="#" class="icon_save"></a></section>
			<section class="pages_publish"></section>
			<section class="pages_edit"></section>
		</li>
		<?php foreach ($revisions["unsaved"] as $revision) { ?>
		<li>
			<section class="pages_last_edited"><?=date("F j, Y @ g:ia",strtotime($revision["updated_at"]))?></section>
			<section class="pages_draft_author"><span class="gravatar"><img src="<?=User::gravatar($revision["email"], 36)?>" alt="" /></span><?=$revision["name"]?></section>
			<section class="pages_delete"><a href="#<?=$revision["id"]?>" class="icon_save"></a></section>
			<section class="pages_publish"><a href="#<?=$revision["id"]?>" class="icon_draft"></a></section>
			<section class="pages_edit"><a href="#<?=$revision["id"]?>" class="icon_delete"></a></section>
		</li>
		<?php } ?>
	</ul>
</div>
<div class="table">
	<div class="container_summary"><h2><span class="icon_medium_saved"></span><?=Text::translate("Saved Revisions")?></h2></div>
	<header>
		<span class="pages_last_edited"><?=Text::translate("Saved")?></span>
		<span class="pages_draft_description"><?=Text::translate("Description")?></span>
		<span class="pages_publish"><?=Text::translate("New Draft")?></span>
		<span class="pages_edit"><?=Text::translate("Delete")?></span>
	</header>
	<ul>
		<?php foreach ($revisions["saved"] as $revision) { ?>
		<li>
			<section class="pages_last_edited"><?=date("F j, Y @ g:ia",strtotime($revision["updated_at"]))?></section>
			<section class="pages_draft_description"><?=$revision["saved_description"]?></section>
			<section class="pages_publish"><a href="#<?=$revision["id"]?>" class="icon_draft"></a></section>
			<section class="pages_edit"><a href="#<?=$revision["id"]?>" class="icon_delete"></a></section>
		</li>
		<?php } ?>
	</ul>
</div>
<script>
	(function() {
		var ActiveDraft = <?php if ($draft) { ?>true<?php } else { ?>false<?php } ?>;
		
		setInterval(function() {
			$.secureAjax("<?=ADMIN_ROOT?>ajax/refresh-lock/", {
				type: "POST",
				data: { table: "bigtree_pages", id: "<?=$lock->ID?>" }
			});
		}, 60000);
		
		$(".icon_save").click(function() {
			BigTreeDialog({
				title: "<?=Text::translate("Save Revision")?>",
				content: '<fieldset class="last"><label><?=Text::translate("Short Description")?> <small>(<?=Text::translate("quick reminder of what\'s special about this revision")?>)</small></label><input type="text" name="description" /></fieldset>',
				callback: $.proxy(function(d) {
					var id;
					
					// If there's no href it's because it's the currently published copy we're saving.
					if (BigTree.cleanHref($(this).attr("href"))) {
						id = BigTree.cleanHref($(this).attr("href"));
					} else {
						id = "c<?=$page->ID?>";
					}
					
					$.secureAjax("<?=ADMIN_ROOT?>ajax/pages/save-revision/", { type: "POST", data: { id: id, description: d.description }});
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
						$.secureAjax("<?=ADMIN_ROOT?>ajax/pages/delete-revision/?id=" + BigTree.cleanHref($(this).attr("href")));
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
						$.secureAjax($(this).attr("href"));
						$(this).parents("li").remove();
						BigTree.growl("Pages","<?=Text::translate("Deleted Draft")?>");
					},this)
				});
			}
			
			return false;
		});
		
		$(".icon_draft").click(function() {
			if (ActiveDraft) {
				BigTreeDialog({
					title: "<?=Text::translate("Use Revision")?>",
					content: '<p class="confirm"><?=Text::translate("Are you sure you want to overwrite your existing draft with this revision?")?></p>',
					alternateSaveText: "<?=Text::translate("Overwrite")?>",
					callback: $.proxy(function() {
						document.location.href = "<?=ADMIN_ROOT?>ajax/pages/use-draft/?id=" + BigTree.cleanHref($(this).attr("href") + "<?php CSRF::drawGETToken(); ?>");
					},this)
				});
			} else {
				document.location.href = "<?=ADMIN_ROOT?>ajax/pages/use-draft/?id=" + BigTree.cleanHref($(this).attr("href") + "<?php CSRF::drawGETToken(); ?>");
			}
			
			return false;
		});
	})();
</script>
<?php
	// Make sure this is a live page.
	if (!is_numeric($page['id'])) {
	    ?>
<div class="container">
	<section>
		<div class="alert">
			<span></span>
			<h3>Error</h3>
		</div>
		<p>Revisions do not function on unpublished pages.</p>
	</section>
</div>
<?php
		$admin->stop();
	}

	// Make sure the user is a publisher.
	if ($bigtree['access_level'] != 'p') {
	    ?>
<div class="container">
	<section>
		<div class="alert">
			<span></span>
			<h3>Error</h3>
		</div>
		<p>You must be a publisher to manage revisions.</p>
	</section>
</div>
<?php
		$admin->stop();
	}

	// Check for a page lock
	$force = isset($_GET['force']) ? $_GET['force'] : false;
	$lock_id = $admin->lockCheck('bigtree_pages', $page['id'], 'admin/modules/pages/_locked.php', $force);

	// See if there's a draft copy.
	$draft = $admin->getPageChanges($page['id']);

	// Get the current published copy.  We're going to just pull a few columns or I'd use getPage here.
	$current_author = $admin->getUser($page['last_edited_by']);

	// Get all revisions
	$revisions = $admin->getPageRevisions($page['id']);

	include BigTree::path('admin/modules/pages/_properties.php');

	if ($draft) {
	    $draft_author = $admin->getUser($draft['user']);
	    ?>
<div class="table">
	<summary><h2><span class="pages"></span>Current Draft</h2></summary>
	<header>
		<span class="pages_last_edited">Last Edited</span>
		<span class="pages_draft_author">Draft Author</span>
		<span class="pages_publish">Publish</span>
		<span class="pages_edit">Edit</span>
		<span class="pages_delete">Delete</span>
	</header>
	<ul>
		<li>
			<section class="pages_last_edited"><?=date('F j, Y @ g:ia', strtotime($draft['date']))?></section>
			<section class="pages_draft_author"><span class="gravatar"><img src="<?=BigTree::gravatar($draft_author['email'], 36)?>" alt="" /></span><?=$draft_author['name']?></section>
			<section class="pages_publish"><a class="icon_publish" href="<?=ADMIN_ROOT?>pages/publish-draft/<?=$page['id']?>/?draft=<?=$draft['id']?>"></a></section>
			<section class="pages_edit"><a class="icon_edit" href="<?=ADMIN_ROOT?>pages/edit/<?=$page['id']?>/"></a></section>
			<section class="pages_delete"><a class="icon_delete" href="<?=ADMIN_ROOT?>ajax/pages/delete-draft/?id=<?=$page['id']?>"></a></section>
		</li>

	</ul>
</div>
<?php

	}
?>
<div class="table">
	<summary><h2><span class="published"></span>Published Revisions</h2></summary>
	<header>
		<span class="pages_last_edited">Published</span>
		<span class="pages_draft_author">Author</span>
		<span class="pages_delete">Save</span>
		<span class="pages_publish">New Draft</span>
		<span class="pages_edit">Delete</span>
	</header>
	<ul>
		<li class="active">
			<section class="pages_last_edited"><?=date('F j, Y @ g:ia', strtotime($page['updated_at']))?></section>
			<section class="pages_draft_author"><span class="gravatar"><img src="<?=BigTree::gravatar($current_author['email'], 36)?>" alt="" /></span><?=$current_author['name']?><span class="active_draft">Active</span></section>
			<section class="pages_delete"><a href="#" class="icon_save"></a></section>
			<section class="pages_publish"></section>
			<section class="pages_edit"></section>
		</li>
		<?php foreach ($revisions['unsaved'] as $r) {
    ?>
		<li>
			<section class="pages_last_edited"><?=date('F j, Y @ g:ia', strtotime($r['updated_at']))?></section>
			<section class="pages_draft_author"><span class="gravatar"><img src="<?=BigTree::gravatar($r['email'], 36)?>" alt="" /></span><?=$r['name']?></section>
			<section class="pages_delete"><a href="#<?=$r['id']?>" class="icon_save"></a></section>
			<section class="pages_publish"><a href="#<?=$r['id']?>" class="icon_draft"></a></section>
			<section class="pages_edit"><a href="#<?=$r['id']?>" class="icon_delete"></a></section>
		</li>
		<?php 
} ?>
	</ul>
</div>
<div class="table">
	<summary><h2><span class="saved"></span>Saved Revisions</h2></summary>
	<header>
		<span class="pages_last_edited">Saved</span>
		<span class="pages_draft_description">Description</span>
		<span class="pages_publish">New Draft</span>
		<span class="pages_edit">Delete</span>
	</header>
	<ul>
		<?php foreach ($revisions['saved'] as $r) {
    ?>
		<li>
			<section class="pages_last_edited"><?=date('F j, Y @ g:ia', strtotime($r['updated_at']))?></section>
			<section class="pages_draft_description"><?=$r['saved_description']?></section>
			<section class="pages_publish"><a href="#<?=$r['id']?>" class="icon_draft"></a></section>
			<section class="pages_edit"><a href="#<?=$r['id']?>" class="icon_delete"></a></section>
		</li>
		<?php 
} ?>
	</ul>
</div>
<script>
	BigTree.localActiveDraft = <?php if ($draft) {
    ?>true<?php 
} else {
    ?>false<?php 
} ?>;
	BigTree.localLockTimer = setInterval("$.ajax('<?=ADMIN_ROOT?>ajax/refresh-lock/', { type: 'POST', data: { table: 'bigtree_pages', id: '<?=$lock_id?>' } });",60000);
	
	$(".icon_save").click(function() {
		BigTreeDialog({
			title: "Save Revision",
			content: '<fieldset class="last"><label>Short Description <small>(quick reminder of what\'s special about this revision)</small></label><input type="text" name="description" /></fieldset>',
			callback: $.proxy(function(d) {
				// If there's no href it's because it's the currently published copy we're saving.
				if (BigTree.cleanHref($(this).attr("href"))) {
					var id = BigTree.cleanHref($(this).attr("href"));
				} else {
					var id = "c<?=$page['id']?>";
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
				title: "Delete Revision",
				content: '<p class="confirm">Are you sure you want to delete this revision?</p>',
				icon: "delete",
				alternateSaveText: "OK",
				callback: $.proxy(function() {
					$.ajax("<?=ADMIN_ROOT?>ajax/pages/delete-revision/?id=" + BigTree.cleanHref($(this).attr("href")));
					$(this).parents("li").remove();
					BigTree.growl("Pages","Deleted Revision");
				},this)
			});
		} else {
			BigTreeDialog({
				title: "Delete Draft",
				content: '<p class="confirm">Are you sure you want to delete this draft?</p>',
				icon: "delete",
				alternateSaveText: "OK",
				callback: $.proxy(function() {
					$.ajax($(this).attr("href"));
					$(this).parents("li").remove();
					BigTree.growl("Pages","Deleted Draft");
				},this)
			});
		}
		
		return false;
	});
	
	$(".icon_draft").click(function() {
		if (BigTree.localActiveDraft) {
			BigTreeDialog({
				title: "Use Revision",
				content: '<p class="confirm">Are you sure you want to overwrite your existing draft with this revision?</p>',
				alternateSaveText: "Overwrite",
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
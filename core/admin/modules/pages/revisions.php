<?
	$page = end($path);
	$access = $admin->getPageAccessLevel($page);
	if ($access != "p") {
		$admin->stop("You must be a publisher to manage revisions.");
	}
	$pdata = $cms->getPage($page);
	
	if (!$pdata) {
?>
<h1><span class="error"></span>Error</h1>
<p class="error">The page you are trying to edit no longer exists.</p>
<?
		$admin->stop();
	}
?>
<h1><span class="refresh"></span><?=$pdata["nav_title"]?></h1>	
<?
	include BigTree::path("admin/modules/pages/_nav.php");
	
	// Check for a page lock
	$admin->lockCheck("bigtree_pages",$page,"admin/modules/pages/_locked.php",$_GET["force"]);
	
	// See if there's a draft copy.
	$draft = $admin->getPageChanges($pdata["id"]);
	
	// Get the current published copy.  We're going to just pull a few columns or I'd use getPage here.
	$current_author = $admin->getUser($pdata["last_edited_by"]);
	
	// Get all revisions
	$revisions = $admin->getPageRevisions($page);
?>
<div class="table">
	<summary><h2><span class="visible"></span>Unpublished Drafts</h2></summary>
	<header>
		<span class="pages_last_edited">Last Edited</span>
		<span class="pages_draft_author">Draft Author</span>
		<span class="pages_publish">Publish</span>
		<span class="pages_edit">Edit</span>
		<span class="pages_delete">Delete</span>
	</header>
	<ul>
		<?
			if ($draft) {
				$draft_author = $admin->getUser($draft["user"]);
		?>
		<li>
			<section class="pages_last_edited"><?=date("F j, Y @ g:ia",strtotime($draft["date"]))?></section>
			<section class="pages_draft_author"><?=$draft_author["name"]?></section>
			<section class="pages_publish"><a class="icon_publish" href="#"></a></section>
			<section class="pages_edit"><a class="icon_edit" href="<?=$admin_root?>pages/edit/<?=$pdata["id"]?>/"></a></section>
			<section class="pages_delete"><a class="icon_delete" href="<?=$admin_root?>ajax/pages/delete-draft/?id=<?=$pdata["id"]?>"></a></section>
		</li>
		<?
			}
		?>
	</ul>
</div>
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
			<section class="pages_last_edited"><?=date("F j, Y @ g:ia",strtotime($pdata["updated_at"]))?></section>
			<section class="pages_draft_author"><?=$current_author["name"]?><span class="active_draft">Active</span></section>
			<section class="pages_delete"><a href="#" class="icon_save"></a></section>
			<section class="pages_publish"><a href="#" class="icon_draft"></a></section>
			<section class="pages_edit"></span>
		</li>
		<? foreach ($revisions["unsaved"] as $r) { ?>
		<li>
			<section class="pages_last_edited"><?=date("F j, Y @ g:ia",strtotime($r["updated_at"]))?></section>
			<section class="pages_draft_author"><?=$r["name"]?></section>
			<section class="pages_delete"><a href="#<?=$r["id"]?>" class="icon_save"></a></section>
			<section class="pages_publish"><a href="#<?=$r["id"]?>" class="icon_draft"></a></section>
			<section class="pages_edit"><a href="#<?=$r["id"]?>" class="icon_delete"></a></span>
		</li>
		<? } ?>
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
		<? foreach ($revisions["saved"] as $r) { ?>
		<li>
			<section class="pages_last_edited"><?=date("F j, Y @ g:ia",strtotime($r["updated_at"]))?></section>
			<section class="pages_draft_description"><?=$r["saved_description"]?></section>
			<section class="pages_publish"><a href="#<?=$r["id"]?>" class="icon_draft"></a></section>
			<section class="pages_edit"><a href="#<?=$r["id"]?>" class="icon_delete"></a></span>
		</li>
		<? } ?>
	</ul>
</div>
<script type="text/javascript">
	var active_draft = <? if ($draft) { ?>true<? } else { ?>false<? } ?>;
	var page = "<?=$pdata["id"]?>";
	var page_updated_at = "<?=$pdata["updated_at"]?>";
	lockTimer = setInterval("$.ajax('<?=$admin_root?>ajax/pages/refresh-lock/', { type: 'POST', data: { id: '<?=$lockid?>' } });",60000);
	
	$(".icon_save").click(function() {
		new BigTreeDialog("Save Revision",'<fieldset><label>Short Description <small>(quick reminder of what\'s special about this revision)</small></label><input type="text" name="description" /></fieldset>',$.proxy(function(d) {
			// If there's no href it's because it's the currently published copy we're saving.
			if (BigTree.CleanHref($(this).attr("href"))) {
				id = BigTree.CleanHref($(this).attr("href"));
			} else {
				id = "c<?=$page?>";
			}
			$.ajax("<?=$admin_root?>ajax/pages/save-revision/", { type: "POST", data: { id: id, description: d.description }, complete: function() {
				//window.location.reload();
			}});
		},this));
		
		return false;
	});
	
	$(".icon_delete").click(function() {
		href = $(this).attr("href");
		if (href.substr(0,1) == "#") {
			new BigTreeDialog("Delete Revision",'<p class="confirm">Are you sure you want to delete this revision?</p>',$.proxy(function() {
				$.ajax("<?=$admin_root?>ajax/pages/delete-revision/?id=" + BigTree.CleanHref($(this).attr("href")));
				$(this).parents("li").remove();
				BigTree.growl("Pages","Deleted Revision");
			},this),"delete",false,"OK");
		} else {
			new BigTreeDialog("Delete Draft",'<p class="confirm">Are you sure you want to delete this draft?</p>',$.proxy(function() {
				$.ajax($(this).attr("href"));
				$(this).parents("li").remove();
				BigTree.growl("Pages","Deleted Draft");
			},this),"delete",false,"OK");
		}
		
		return false;
	});
	
	$(".icon_draft").click(function() {
		if (active_draft) {
			new BigTreeDialog("Use Revision",'<p class="confirm">Are you sure you want to overwrite your existing draft with this revision?</p>',$.proxy(function() {
				document.location.href = "<?=$admin_root?>ajax/pages/use-draft/?id=" + BigTree.CleanHref($(this).attr("href"));
			},this),"",false,"OK");
		} else {
			document.location.href = "<?=$admin_root?>ajax/pages/use-draft/?id=" + BigTree.CleanHref($(this).attr("href"));
		}
		return false;
	});
</script>
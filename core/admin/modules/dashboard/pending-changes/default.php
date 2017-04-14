<?php
	namespace BigTree;
	
	// Get pending changes awaiting this user's approval.
	$user = new User(Auth::user()->ID);
	$changes = PendingChange::allPublishableByUser($user);

	// Go through and get all the modules and pages, separate them out.
	$modules = array();
	$pages = array();
	
	foreach ($changes as $change) {
		if ($change->Table == "bigtree_pages") {
			$pages[] = $change;
		} else {
			$module_id = $change->Module->ID;

			if (isset($modules[$module_id])) {
				$modules[$module_id]->Changes[] = $change;
			} else {
				$modules[$module_id] = $change->Module;
				$modules[$module_id]->Changes = array($change);
			}
		}
	}

	if (!count($changes)) {
?>
<div class="container">
	<section>
		<p><?=Text::translate("You have no changes awaiting your approval.")?></p>
	</section>
</div>
<?php
	}

	// We don't want to repeatedly call for translations in the loop
	$change_translated = Text::translate("CHANGE");
	$new_translated = Text::translate("NEW");

	if (count($pages)) {
?>
<a name="0"></a>
<div class="table">
	<div class="table_summary">
		<h2 class="full">
			<span class="pages"></span>
			<?=Text::translate("Pages")?>
		</h2>
	</div>
	<header>
		<span class="changes_author"><?=Text::translate("Author")?></span>
		<span class="changes_page"><?=Text::translate("Page")?></span>
		<span class="changes_type"><?=Text::translate("Type")?></span>
		<span class="changes_time"><?=Text::translate("Updated")?></span>
		<span class="changes_action"><?=Text::translate("Preview")?></a></span>
		<span class="changes_action"><?=Text::translate("Edit")?></a></span>
		<span class="changes_action"><?=Text::translate("Approve")?></span>
		<span class="changes_action"><?=Text::translate("Deny")?></span>
	</header>
	<ul>
		<?php
			foreach ($pages as $change) {
				if (is_numeric($change->ItemID)) {
					$page = Page::getPageDraft($change->ItemID);
					$preview_link = WWW_ROOT."_preview/".$page->Path."/";
					$edit_link = ADMIN_ROOT."pages/edit/".$change->ItemID."/";

					if (!$change->ItemID) {
						$page->NavigationTitle = "Home";
					}
				} else {
					$page = Page::getPageDraft("p".$change->ID);
					$preview_link = WWW_ROOT."_preview-pending/p".$change->ID."/";
					$edit_link = ADMIN_ROOT."pages/edit/p".$change->ID."/";
				}
		?>
		<li>
			<section class="changes_author"><?=$change->User->Name?></section>
			<section class="changes_page"><?=$page->NavigationTitle?></section>
			<section class="changes_type"><?php if (is_numeric($change->ItemID)) { echo $change_translated; } else {  ?><span class="new"><?=$new_translated?></span><?php } ?></section>
			<section class="changes_time"><?=Date::relativeTime($change->Date)?></section>
			<section class="changes_action"><a href="<?=$preview_link?>" target="_preview" class="icon_preview"></a></section>
			<section class="changes_action"><a href="<?=$edit_link?>" class="icon_edit"></a></section>
			<section class="changes_action"><a href="#<?=$change->ID?>" data-module="Pages" class="icon_approve icon_approve_on"></a></section>
			<section class="changes_action"><a href="#<?=$change->ID?>" data-module="Pages" class="icon_deny"></a></section>
		</li>
		<?php
			}
		?>
	</ul>
</div>
<?php
	}
	
	foreach ($modules as $module_id => $module) {
		$view = ModuleView::getByTable($module->Table);
		$view_data = $view ? $view->getData() : false;
?>
<a name="<?=$module_id?>"></a>
<div class="table">
	<div class="table_summary">
		<h2 class="full">
			<span class="modules"></span>
			<?=$module->Name?>
		</h2>
	</div>
	<?php if ($view["type"] == "images" || $view["type"] == "images-grouped") { ?>
	<section>
		<ul class="image_list">
			<?php
				foreach ($module->Changes as $change) {
					if ($view_data) {
						if ($change->ItemID) {
							$item = $view_data[$change->ItemID];
						} else {
							$item = $view_data["p".$change->ID];
						}
					} else {
						$item = array("id" => $change->ItemID ? $change->ItemID : "p".$change->ID);
					}

					$image = str_replace(array("{staticroot}","{wwwroot}"),array(STATIC_ROOT,WWW_ROOT),$item["column1"]);

					if ($view->Settings["prefix"]) {
						$image = FileSystem::getPrefixedFile($image, $view->Settings["prefix"]);
					}
			?>
			<li class="non_draggable">
				<p><?=$change->User->Name?></p>
				<?php
					if ($view->EditURL) {
				?>
				<a class="image" href="<?=$view->EditURL.$item["id"]?>/"><img src="<?=$image?>" alt="" /></a>
				<?php
					} else {
				?>
				<figure class="image"><img src="<?=$image?>" alt="" /></figure>
				<?php
					}

					if ($view->PreviewURL) {
				?>
				<a href="<?=rtrim($view->PreviewURL,"/")."/".$item["id"]."/"?>" target="_preview" class="icon_preview"></a>
				<?php
					}
				?>
				<a href="#<?=$change->ID?>" data-module="<?=$module->Name?>" class="icon_approve icon_approve_on"></a>
				<a href="#<?=$change->ID?>" data-module="<?=$module->Name?>" class="icon_deny"></a>
			</li>
			<?php
				}
			?>
		</ul>
	</section>
	<?php } else { ?>
	<header>
		<span class="changes_author"><?=Text::translate("Author")?></span>
		<?php
			if (is_array($view->Fields)) {
				foreach ($view->Fields as $field) {
		?>
		<span class="view_column" style="width: <?=$field["width"]?>px;"><?=$field["title"]?></span>
		<?php
				}
			}
			
			if ($view["preview_url"]) {
		?>
		<span class="changes_action"><?=Text::translate("Preview")?></a></span>
		<?php
			}
		?>
		<span class="changes_action"><?=Text::translate("Edit")?></a></span>
		<span class="changes_action"><?=Text::translate("Approve")?></span>
		<span class="changes_action"><?=Text::translate("Deny")?></span>
	</header>
	<ul>
		<?php
			foreach ($module->Changes as $change) {
				if ($change->ItemID) {
					$item = $view_data[$change->ItemID];
				} else {
					$item = $view_data["p".$change->ID];
				}
		?>
		<li>
			<section class="changes_author"><?=$change->User->Name?></section>
			<?php
				if (is_array($view->Fields)) {
					$x = 0;
					foreach ($view->Fields as $field => $data) {
						$x++;
			?>
			<section class="view_column" style="width: <?=$data["width"]?>px;">
				<?=$item["column$x"]?>
			</section>
			<?php
					}
				}
					
				if ($view->PreviewURL) {
			?>
			<section class="changes_action"><a href="<?=rtrim($view->PreviewURL,"/")."/".$item["id"]."/"?>" target="_preview" class="icon_preview"></a></section>
			<?php
				}

				if ($view->EditURL) {
			?>
			<section class="changes_action"><a href="<?=$view->EditURL.$item["id"]?>/" class="icon_edit"></a></section>
			<?php
				} else {
			?>
			<section class="changes_action"><span class="icon_edit disabled_icon"></span></section>
			<?php
				}
			?>
			<section class="changes_action"><a href="#<?=$change->ID?>" data-module="<?=$module->Name?>" class="icon_approve icon_approve_on"></a></section>
			<section class="changes_action"><a href="#<?=$change->ID?>" data-module="<?=$module->Name?>" class="icon_deny"></a></section>
		</li>
		<?php
			}
		?>
	</ul>
	<?php } ?>
</div>
<?php
	}
?>

<script>
	$(".icon_approve").click(function() {
		$.secureAjax("<?=ADMIN_ROOT?>ajax/dashboard/approve-change/", { data: { id: $(this).attr("href").substr(1) }, type: "POST" });
		$(this).parents("li").remove();
		BigTree.growl($(this).attr("data-module"),"<?=Text::translate("Approved Change")?>");
		return false;
	});
	
	$(".icon_deny").click(function() {
		$.secureAjax("<?=ADMIN_ROOT?>ajax/dashboard/reject-change/", { data: { id: $(this).attr("href").substr(1) }, type: "POST" });
		$(this).parents("li").remove();
		BigTree.growl($(this).attr("data-module"),"<?=Text::translate("Rejected Change")?>");
		return false;
	});
</script>
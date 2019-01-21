<?php
	namespace BigTree;
	
	// Include pages.js
	$bigtree["js"][] = "pages.js";
?>
<div class="container">
	<header>
		<div class="sticky_controls">
			<div class="shadow">
				<nav class="left">
					<a href="#properties_tab"<?php if ($bigtree["form_action"] == "create") { ?> class="active"<?php } ?>><?=Text::translate("Properties")?></a>
					<a href="#content_tab"<?php if ($bigtree["form_action"] == "update") { ?> class="active"<?php } ?>><?=Text::translate("Content")?></a>
					<a href="#seo_tab"><?=Text::translate("SEO")?></a>
				</nav>
				<div id="link_finder_results" style="display: none;"></div>
				<input type="search" id="link_finder" class="form_search" autocomplete="off" placeholder="<?=Text::translate("Link Finder")?>" />
				<span class="form_search_icon link_finder_search_icon"></span>
			</div>
		</div>
	</header>
	<form method="post" class="module" action="<?=ADMIN_ROOT?>pages/<?=$bigtree["form_action"]?>/" enctype="multipart/form-data" id="page_form">
		<?php
			CSRF::drawPOSTToken();
			
			if (isset($_GET["return"]) && $_GET["return"] == "front") {
		?>
		<input type="hidden" name="return_to_front" value="true" />
		<?php
			}
			if (isset($_GET["return_to_self"])) {
		?>
		<input type="hidden" name="return_to_self" value="true" />
		<?php
			}
		?>
		<input type="hidden" name="MAX_FILE_SIZE" value="<?=Storage::getUploadMaxFileSize()?>" id="bigtree_max_file_size" />
		<input type="hidden" name="_bigtree_post_check" value="success" />
		<input type="hidden" name="<?php if ($bigtree["form_action"] == "create") { ?>parent<?php } else { ?>page<?php } ?>" value="<?=$bigtree["current_page"]["id"]?>" />
		
		<section id="properties_tab"<?php if ($bigtree["form_action"] == "update") { ?> style="display: none;"<?php } ?>>
			<?php include Router::getIncludePath("admin/modules/pages/tabs/properties.php") ?>
		</section>
		<section id="content_tab"<?php if ($bigtree["form_action"] == "create") { ?> style="display: none;"<?php } ?>>
			<?php include Router::getIncludePath("admin/modules/pages/tabs/content.php") ?>
		</section>
		<section id="seo_tab" style="display: none;">
			<?php include Router::getIncludePath("admin/modules/pages/tabs/seo.php") ?>
		</section>
		<footer>
			<a href="#" class="next button" tabindex="200"><?=Text::translate("Next Step", true)?> &raquo;</a>

			<?php
				if ($bigtree["form_action"] == "create") {
			?>
			<input type="submit" name="form_action" tabindex="201" value="<?=Text::translate("Create", true)?>" <?php if ($bigtree["access_level"] != "p") { ?>class="blue" <?php } ?>/>
			<?php
					if ($bigtree["access_level"] == "p") {
			?>
			<input type="submit" name="form_action" tabindex="202" value="<?=Text::translate("Create & Publish", true)?>" class="blue" />
			<?php
					}
				} else {
			?>
			<a href="#" class="button save_and_preview" tabindex="203" <?php if (!empty($bigtree["current_page"]["external"]) || $bigtree["current_page"]["template"] == "!") { ?> style="display: none;"<?php } ?>><span class="icon_small icon_small_computer"></span><?=Text::translate("Save & Preview", true)?></a>
			<input type="submit" name="form_action" tabindex="204" value="<?=Text::translate("Save", true)?>"<?php if ($bigtree["access_level"] != "p") { ?> class="blue"<?php } ?> />
			<?php
					if ($bigtree["access_level"] == "p") {
			?>
			<input type="submit" name="form_action" tabindex="205" value="<?=Text::translate("Save & Publish", true)?>" class="blue" />
			<?php
					}
				}
			?>
		</footer>
	</form>
</div>

<script>
	BigTreeFormNavBar.init();

	BigTree.currentPageTemplate = "<?=$bigtree["current_page"]["template"]?>";
	<?php if ($bigtree["form_action"] == "create") { ?>
	BigTree.currentPage = false;
	<?php } else { ?>
	BigTree.currentPage = "<?=$bigtree["current_page"]["id"]?>";
	BigTree.localLockTimer = setInterval("$.secureAjax('<?=ADMIN_ROOT?>ajax/refresh-lock/', { type: 'POST', data: { table: 'bigtree_pages', id: '<?=$bigtree["current_page"]["id"]?>' } });",60000);
	<?php } ?>
</script>
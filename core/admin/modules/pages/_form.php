<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */
	
	// Include pages.js
	$bigtree["js"][] = "pages.js";
?>
<div class="container">
	<?php
		if ($bigtree["form_action"] != "create") {
	?>
	<div class="developer_buttons">
		<?php
			if (Auth::user()->Level > 1) {
				if ($bigtree["current_page"]["template"] &&
					$bigtree["current_page"]["template"] != "!" &&
					$bigtree["form_action"] != "create"
				) {
		?>
		<a href="<?=ADMIN_ROOT?>developer/templates/edit/<?=$bigtree["current_page"]["template"]?>/?return=<?=$bigtree["current_page"]["id"]?>" title="<?=Text::translate("Edit Current Template in Developer", true)?>">
			<?=Text::translate("Edit Current Template in Developer")?>
			<span class="icon_small icon_small_edit_yellow"></span>
		</a>
		<?php
				}
		?>
		<a href="<?=ADMIN_ROOT?>developer/audit/search/?table=bigtree_pages&entry=<?=$bigtree["current_page"]["id"]?>&<?php CSRF::drawGETToken(); ?>" title="<?=Text::translate("View Page in Audit Trail", true)?>">
			<?=Text::translate("View Page in Audit Trail")?>
			<span class="icon_small icon_small_trail"></span>
		</a>
		<?php
			}

			if (Auth::user()->Level > 0) {
		?>
		<a href="<?=ADMIN_ROOT?>pages/access-levels/<?=$bigtree["current_page"]["id"]?>/" title="<?=Text::translate("View User Access Levels", true)?>">
			<?=Text::translate("View User Access Levels")?>
			<span class="icon_small icon_small_user"></span>
		</a>
		<?php
			}
		?>
	</div>
	<?php
		}
	?>

	<header>
		<div class="sticky_controls">
			<div class="shadow">
				<nav class="left">
					<a href="#properties_tab"<?php if ($bigtree["form_action"] == "create") { ?> class="active"<?php } ?>><?=Text::translate("Properties")?></a>
					<a href="#content_tab"<?php if ($bigtree["form_action"] == "update") { ?> class="active"<?php } ?>><?=Text::translate("Content")?></a>
					<a href="#seo_tab"><?=Text::translate("SEO")?></a>
					<a href="#sharing_tab"><?=Text::translate("Sharing")?></a>
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
		<section id="sharing_tab" style="display: none;">
			<?php
				$og_data = $bigtree["current_page"]["open_graph"];
				include Router::getIncludePath("admin/auto-modules/forms/_open-graph.php");
			?>
		</section>
		<footer class="js-pages-form-footer">
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

	BigTree.ReadyHooks.push(function() {
		BigTreePages.init({
			template: "<?=$bigtree["current_page"]["template"]?>",
			page: <?php if ($bigtree["form_action"] == "create") { echo "false"; } else { echo '"'.$bigtree["current_page"]["id"].'"'; } ?>,
		});
	});
</script>
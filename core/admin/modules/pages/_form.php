<?php
	// Include pages.js
	$bigtree["js"][] = "pages.js";
	
	// See if the user isn't allowed to use the currently in use template. If they can't, we hide the section altogether.
	$hide_template_section = false;

	if (is_array($template_data) && $template_data["level"] > $admin->Level) {
		$hide_template_section = true;
	}
?>
<div class="container">
	<?php
		if ($bigtree["form_action"] != "create") {
	?>
	<div class="developer_buttons">
		<?php
			if ($admin->Level > 1) {
		?>
		<a href="<?=ADMIN_ROOT?>developer/templates/edit/<?=$page["template"]?>/?return=<?=$page["id"]?>" title="Edit Current Template in Developer">
			Edit Current Template in Developer
			<span class="icon_small icon_small_edit_yellow"></span>
		</a>
		<a href="<?=ADMIN_ROOT?>developer/audit/search/?table=bigtree_pages&entry=<?=$page["id"]."&".$admin->CSRFTokenField."=".urlencode($admin->CSRFToken)?>" title="View Page in Audit Trail">
			View Page in Audit Trail
			<span class="icon_small icon_small_trail"></span>
		</a>
		<?php
			}

			if ($admin->Level > 0) {
		?>
		<a href="<?=ADMIN_ROOT?>pages/access-levels/<?=$page["id"]?>/" title="View User Access Levels">
			View User Access Levels
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
					<a href="#properties_tab"<?php if ($bigtree["form_action"] == "create") { ?> class="active"<?php } ?>>Properties</a>
					<a href="#content_tab"<?php if ($bigtree["form_action"] == "update") { ?> class="active"<?php } ?>>Content</a>
					<a href="#seo_tab">SEO</a>
					<a href="#sharing_tab">Sharing</a>
				</nav>
				<div id="link_finder_results" style="display: none;"></div>
				<input type="search" id="link_finder" class="form_search" autocomplete="off" placeholder="Link Finder" />
				<span class="form_search_icon link_finder_search_icon"></span>
			</div>
		</div>
	</header>
	<form method="post" class="module" action="<?=ADMIN_ROOT?>pages/<?=$bigtree["form_action"]?>/" enctype="multipart/form-data" id="page_form">
		<?php
			$admin->drawCSRFToken();
			
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
		<input type="hidden" name="MAX_FILE_SIZE" value="<?=BigTree::uploadMaxFileSize()?>" id="bigtree_max_file_size" />
		<input type="hidden" name="<?php if ($bigtree["form_action"] == "create") { ?>parent<?php } else { ?>page<?php } ?>" value="<?=$bigtree["current_page"]["id"]?>" />
		
		<section id="properties_tab"<?php if ($bigtree["form_action"] == "update") { ?> style="display: none;"<?php } ?>>
			<?php include BigTree::path("admin/modules/pages/tabs/properties.php"); ?>
		</section>
		<section id="content_tab"<?php if ($bigtree["form_action"] == "create") { ?> style="display: none;"<?php } ?>>
			<?php include BigTree::path("admin/modules/pages/tabs/content.php"); ?>
		</section>
		<section id="seo_tab" style="display: none;">
			<?php include BigTree::path("admin/modules/pages/tabs/seo.php"); ?>
		</section>
		<section id="sharing_tab" style="display: none;">
			<?php
				$og_data = $bigtree["current_page"]["open_graph"];
				include BigTree::path("admin/auto-modules/forms/_open-graph.php");
			?>
		</section>
		<footer class="js-pages-form-footer">
			<a href="#" class="next button" tabindex="200">Next Step &raquo;</a>

			<?php
				if ($bigtree["form_action"] == "create") {
			?>
			<input type="submit" name="ptype" value="Create" tabindex="201" <?php if ($bigtree["access_level"] != "p") { ?>class="blue" <?php } ?>/>
			<?php
					if ($bigtree["access_level"] == "p") {
			?>
			<input type="submit" name="ptype" value="Create &amp; Publish" tabindex="202" class="blue" />
			<?php
					}
				} else {
			?>
			<a href="#" class="button save_and_preview" tabindex="203"><span class="icon_small icon_small_computer"></span>Save &amp; Preview</a>
			<input type="submit" name="ptype" value="Save" tabindex="204" <?php if ($bigtree["access_level"] != "p") { ?> class="blue"<?php } ?> />
			<?php
					if ($bigtree["access_level"] == "p") {
			?>
			<input type="submit" name="ptype" value="Save &amp; Publish" tabindex="205" class="blue" />
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
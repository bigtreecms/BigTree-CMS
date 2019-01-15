<div class="container">
	<?php
		if ($bigtree["form"]["open_graph"] && !$bigtree["form"]["embedded"]) {
	?>
	<header>
		<div class="sticky_controls">
			<div class="shadow">
				<nav class="left">
					<a href="#content_tab" class="active">Content</a>
					<a href="#sharing_tab">Sharing</a>
				</nav>
			</div>
		</div>
	</header>
	<?php
		}
	?>
	<form method="post" action="<?=$bigtree["form_root"]?>process/<?php if ($bigtree["form"]["embedded"]) { ?>?hash=<?=$bigtree["form"]["hash"]?><?php } ?>" enctype="multipart/form-data" class="module" id="auto_module_form">
		<?php
			if ($bigtree["form"]["embedded"]) {
		?>
		<fieldset>
			<label>This is a field that shouldn't be filled out.</label>
			<input type="text" name="_bigtree_email" />
			<input type="text" name="_bigtree_hashcash" id="bigtree_hashcash_field" />
		</fieldset>
		<?php
			} else {
				if ($admin->Level > 1) {
		?>
		<div class="developer_buttons">
			<a href="<?=ADMIN_ROOT?>developer/modules/forms/edit/<?=$bigtree["form"]["id"]?>/?return=front" title="Edit Form in Developer">
				Edit Form in Developer
				<span class="icon_small icon_small_edit_yellow"></span>
			</a>
			<?php if ($bigtree["edit_id"]) { ?>
			<a href="<?=ADMIN_ROOT?>developer/audit/search/?table=<?=$bigtree["form"]["table"]?>&entry=<?=$bigtree["edit_id"]."&".$admin->CSRFTokenField."=".urlencode($admin->CSRFToken)?>" title="View Entry Audit Trail">
				View Entry Audit Trail
				<span class="icon_small icon_small_trail"></span>
			</a>
			<?php } ?>
		</div>
		<?php
				}

				$admin->drawCSRFToken();
			}
		?>
		<input type="hidden" id="preview_field" name="_bigtree_preview" />
		<input type="hidden" name="MAX_FILE_SIZE" value="<?=BigTree::uploadMaxFileSize()?>" id="bigtree_max_file_size" />
		<?php
			if (isset($bigtree["entry"])) {
		?>
		<input type="hidden" name="id" value="<?=htmlspecialchars($bigtree["edit_id"])?>" />
		<?php
			}

			if (isset($_GET["view_data"])) {
		?>
		<input type="hidden" name="_bigtree_return_view_data" value="<?=htmlspecialchars($_GET["view_data"])?>" />
		<?php
			}

			if (isset($_GET["return_link"])) {
		?>
		<input type="hidden" name="_bigtree_return_link" value="<?=htmlspecialchars($_GET["return_link"])?>" />
		<?php
			}
		?>
		<section id="content_tab">
			<p class="error_message" style="display: none;">Errors found! Please fix the highlighted fields before submitting.</p>
			<?php
				if (!$admin->drawPOSTErrorMessage() && $_SESSION["bigtree_admin"]["post_hash_failed"]) {
					unset($_SESSION["bigtree_admin"]["post_hash_failed"]);
			?>
			<p class="warning_message">The form submission failed to pass our automated submission test. If you have JavaScript turned off, please turn it on.</p>
			<?php
				}
			?>
			<div class="form_fields">
				<?php
					$bigtree["html_fields"] = array();
					$bigtree["simple_html_fields"] = array();
					$bigtree["tabindex"] = 1;
					$bigtree["field_namespace"] = uniqid("form_field_");
					$bigtree["field_counter"] = 0;
	
					$cached_types = $admin->getCachedFieldTypes();
					$bigtree["field_types"] = $cached_types["modules"];

					$bigtree["form"]["fields"] = $admin->runHooks("fields", "form", $bigtree["form"]["fields"], [
						"form" => $bigtree["form"],
						"step" => "draw"
					]);
	
					foreach ($bigtree["form"]["fields"] as $resource) {
						if (is_array($resource)) {
							$field = array(
								"type" => $resource["type"],
								"title" => $resource["title"],
								"subtitle" => $resource["subtitle"],
								"key" => $resource["column"],
								"has_value" => isset($bigtree["entry"][$resource["column"]]),
								"value" => isset($bigtree["entry"][$resource["column"]]) ? $bigtree["entry"][$resource["column"]] : "",
								"tabindex" => $bigtree["tabindex"],
								"settings" => $resource["settings"] ?: $resource["options"] // Pre-4.3
							);
	
							// Give many to many its information
							if ($resource["type"] == "many-to-many") {
								$field["value"] = isset($bigtree["many-to-many"][$resource["column"]]) ? $bigtree["many-to-many"][$resource["column"]]["data"] : false;
							}
	
							BigTreeAdmin::drawField($field);
						}
					}
				?>
			</div>
			<?php
				if ($bigtree["form"]["tagging"]) {
			?>
			<div class="tags" id="bigtree_tag_browser">
				<?php
					if ($admin->Level > 0) {
				?>
				<a href="<?=ADMIN_ROOT?>tags/" class="bigtree_tag_browser_manager">Manage All Tags</a>
				<?php
					}
				?>
				<fieldset class="tag_browser_entry">
					<label>Tags<span></span></label>
					<div class="tag_browser_input_wrapper">
						<input type="text" name="tag_entry" id="tag_entry" placeholder="Search for or add new tags..." />
						<ul id="tag_results" style="display: none;"></ul>
					</div>
					<ul id="tag_list">
						<?php
							if (is_array($bigtree["tags"])) {
								foreach ($bigtree["tags"] as $tag) {
						?>
						<li><input type="hidden" name="_tags[]" value="<?=$tag["id"]?>" /><a href="#"><?=$tag["tag"]?></a></li>
						<?php
								}
							}
						?>
					</ul>
				</fieldset>
			</div>
			<script>
				BigTreeTagAdder.init();
			</script>
			<?php
				}
			?>
		</section>
		<?php
			if ($bigtree["form"]["open_graph"]) {
		?>
		<section id="sharing_tab" style="display: none;">
			<?php
				$og_data = $pending_entry["open_graph"];
				include BigTree::path("admin/auto-modules/forms/_open-graph.php");
			?>
		</section>
		<?php
			}
		?>
		<footer class="js-auto-modules-footer">
			<?php
				if ($bigtree["form"]["embedded"]) {
			?>
			<input type="submit" class="button" tabindex="<?=$bigtree["tabindex"]?>" value="Submit" />
			<?php
				} else {
					if (isset($bigtree["related_view"]) && $bigtree["related_view"]["preview_url"]) {
			?>
			<a class="button save_and_preview" href="#">
				<span class="icon_small icon_small_computer"></span>
				Save &amp; Preview
			</a>
			<?php
					}
			?>
			<input type="submit" class="button<?php if ($bigtree["access_level"] != "p") { ?> blue<?php } ?>" tabindex="<?=$bigtree["tabindex"]?>" value="Save" name="save" />
			<input type="submit" class="button blue" tabindex="<?=($bigtree["tabindex"] + 1)?>" value="Save & Publish" name="save_and_publish" <?php if ($bigtree["access_level"] != "p") { ?>style="display: none;" <?php } ?>/>
			<?php
				}
			?>
		</footer>
	</form>
</div>
<?php include BigTree::path("admin/layouts/_html-field-loader.php"); ?>
<script>
	(function() {
		BigTreeFormValidator("#auto_module_form",false<?php if ($bigtree["form"]["embedded"]) { ?>,true<?php } ?>);
		BigTreeFormNavBar.init();
		
		$(".save_and_preview").click(function() {
			submit();

			$("#preview_field").val("true");
			$(this).parents("form").submit();
	
			return false;
		});

		$(".js-auto-modules-footer input").click(submit);

		<?php if ($bigtree["access_level"] == "p" || !$bigtree["edit_id"]) { ?>
		$(".gbp_select").change(function() {
			var access_level = $(this).find("option").eq($(this).get(0).selectedIndex).attr("data-access-level");
			if (access_level == "p") {
				$("input[name=save]").removeClass("blue");
				$("input[name=save_and_publish]").show();
			} else {
				$("input[name=save]").addClass("blue");
				$("input[name=save_and_publish]").hide();
			}
		});
		$(".gbp_select").trigger("change");
		<?php } ?>

		function submit() {
			var footer = $(".js-auto-modules-footer");
			
			footer.find("input, .button").addClass("disabled");
			footer.append('<span class="button_loader"></span>');
		}
	})();
</script>
<?
	$current_revision = $cms->getSetting("bigtree-internal-revision");
	// If we recently upgraded...
	if ($current_revision < BIGTREE_REVISION) {
		// Start the upgrade process if we've already said OK.
		if (count($_POST)) {
			while ($current_revision < BIGTREE_REVISION) {
				$current_revision++;
				if (function_exists("_local_bigtree_update_".$current_revision)) {
					eval("_local_bigtree_update_$current_revision();");
				}
			}

			$admin->updateSettingValue("bigtree-internal-revision",BIGTREE_REVISION);
?>
<div class="container">
	<form method="post" action="">
		<section>
			<p>Your update is complete.</p>
		</section>
		<footer>
			<a href="<?=ADMIN_ROOT?>dashboard/" class="button blue">Return to Dashboard</a>
		</footer>
	</form>
</div>
<?
		// See if there are db/fs updates available to run and confirm with them they've backed up their DB for continuing.
		} else {
			$updates_exist = false;
			while ($current_revision < BIGTREE_REVISION) {
				$current_revision++;
				if (function_exists("_local_bigtree_update_".$current_revision)) {
					$updates_exist = true;
				}
			}

			// If we don't have anything to run, just update the revision number and return to the dashboard.
			if (!$updates_exist) {
				$admin->updateSettingValue("bigtree-internal-revision",BIGTREE_REVISION);
				BigTree::redirect(ADMIN_ROOT."dashboard/");
			}
?>
<div class="container">
	<form method="post" action="">
		<section>
			<p>BigTree has been updated to <?=BIGTREE_VERSION?>.</p>
			<p>
				Your database and/or filesystem needs to be upgraded to be compatible with this version.<br />
				It is recommended that you <strong>backup your old database</strong> before continuing.
			</p>
		</section>
		<footer>
			<input type="submit" class="button blue" name="upgrade" value="Upgrade Database" />
		</footer>
	</form>
</div>
<?
		}
	} else {
?>
<div class="container">
	<section>
		<p>BigTree is up to date.</p>
	</section>
	<footer>
		<a href="<?=ADMIN_ROOT?>dashboard/" class="button blue">Return to Dashboard</a>
	</footer>
</div>
<?
	}

	// BigTree 4.0b5 update -- REVISION 1
	function _local_bigtree_update_1() {
		global $cms,$admin;

		// Update settings to make the value LONGTEXT
		sqlquery("ALTER TABLE `bigtree_settings` CHANGE `value` `value` LONGTEXT");

		// Drop the css/javascript columns from bigtree_module_forms and add preprocess
		sqlquery("ALTER TABLE `bigtree_module_forms` ADD COLUMN `preprocess` varchar(255) NOT NULL AFTER `title`, DROP COLUMN `javascript`, DROP COLUMN `css`");

		// Add the "trunk" column to bigtree_pages
		sqlquery("ALTER TABLE `bigtree_pages` ADD COLUMN `trunk` char(2) NOT NULL AFTER `id`");
		sqlquery("UPDATE `bigtree_pages` SET `trunk` = 'on' WHERE id = '0'");

		// Move Google Analytics information into a single setting
		$ga_email = $cms->getSetting("bigtree-internal-google-analytics-email");
		$ga_password = $cms->getSetting("bigtree-internal-google-analytics-password");
		$ga_profile = $cms->getSetting("bigtree-internal-google-analytics-profile");

		$admin->createSetting(array(
			"id" => "bigtree-internal-google-analytics",
			"system" => "on",
			"encrypted" => "on"
		));
		$admin->updateSettingValue("bigtree-internal-google-analytics",array(
			"email" => $ga_email,
			"password" => $ga_password,
			"profile" => $ga_profile
		));


		// Update the upload service setting to be encrypted.
		$admin->updateSetting("bigtree-internal-upload-service",array(
			"id" => "bigtree-internal-upload-service",
			"system" => "on",
			"encrypted" => "on"
		));
		$us = $cms->getSetting("bigtree-internal-upload-service");

		// Move Rackspace into the main upload service
		$rs_containers = $cms->getSetting("bigtree-internal-rackspace-containers");
		$rs_keys = $cms->getSetting("bigtree-internal-rackspace-containers");

		$us["rackspace"] = array(
			"containers" => $rs_containers,
			"keys" => $rs_keys
		);

		// Move Amazon S3 into the main upload service
		$s3_buckets = $cms->getSetting("bigtree-internal-s3-buckets");
		$s3_keys = $cms->getSetting("bigtree-internal-s3-keys");

		$us["s3"] = array(
			"buckets" => $s3_buckets,
			"keys" => $s3_keys
		);

		// Update the upload service value.
		$admin->updateSettingValue("bigtree-internal-upload-service",$us);

		// Create the revision counter
		$admin->createSetting(array(
			"id" => "bigtree-internal-revision",
			"system" => "on"
		));

		// Delete all the old settings.
		sqlquery("DELETE FROM bigtree_settings WHERE id = 'bigtree-internal-google-analytics-email' OR id = 'bigtree-internal-google-analytics-password' OR id = 'bigtree-internal-google-analytics-profile' OR id = 'bigtree-internal-rackspace-keys' OR id = 'bigtree-internal-rackspace-containers' OR id = 'bigtree-internal-s3-buckets' OR id = 'bigtree-internal-s3-keys'");
	}

	// BigTree 4.0b7 update -- REVISION 5
	function _local_bigtree_update_5() {
		// Fixes AES_ENCRYPT not encoding things properly.
		sqlquery("ALTER TABLE `bigtree_settings` CHANGE `value` `value` longblob NOT NULL");

		// Adds the ability to make a field type available for Settings.
		sqlquery("ALTER TABLE `bigtree_field_types` ADD COLUMN `settings` char(2) NOT NULL AFTER `callouts`");

		// Remove uncached.
		sqlquery("ALTER TABLE `bigtree_module_views` DROP COLUMN `uncached`");

		// Adds the ability to set options on a setting.
		sqlquery("ALTER TABLE `bigtree_settings` ADD COLUMN `options` text NOT NULL AFTER `type`");

		// Alter the module view cache table so that it can be used for custom view caching
		sqlquery("ALTER TABLE `bigtree_module_view_cache` CHANGE `view` `view` varchar(255) NOT NULL");
	}

	// BigTree 4.0b7 update -- REVISION 6
	function _local_bigtree_update_6() {
		// Allows null values for module groups and resource folders.
		sqlquery("ALTER TABLE `bigtree_modules` CHANGE `group` `group` int(11) UNSIGNED DEFAULT NULL");
		sqlquery("ALTER TABLE `bigtree_resources` CHANGE `folder` `folder` int(11) UNSIGNED DEFAULT NULL");
	}

	// BigTree 4.0RC1 update -- REVISION 7
	function _local_bigtree_update_7() {
		// Allow forms to set their return view manually.
		sqlquery("ALTER TABLE `bigtree_module_forms` ADD COLUMN `return_view` INT(11) UNSIGNED AFTER `default_position`");
	}
	
	// BigTree 4.0RC2 update -- REVISION 8
	function _local_bigtree_update_8() {
		// Remove image an description columns from modules.
		sqlquery("ALTER TABLE `bigtree_modules` DROP COLUMN `image`");
		sqlquery("ALTER TABLE `bigtree_modules` DROP COLUMN `description`");
		/// Remove locked column from pages.
		sqlquery("ALTER TABLE `bigtree_pages` DROP COLUMN `locked`");
	}
	
	// BigTree 4.0RC2 update -- REVISION 9
	function _local_bigtree_update_9() {
		sqlquery("ALTER TABLE `bigtree_tags_rel` ADD COLUMN `table` VARCHAR(255) NOT NULL AFTER `module`");
		// Figure out the table for all the modules and change the tags to be related to the table instead of the module.
		$q = sqlquery("SELECT * FROM bigtree_modules");
		while ($f = sqlfetch($q)) {
			if (class_exists($f["class"])) {
				@eval('$test = new '.$f["class"].';');
				$table = sqlescape($test->Table);
				sqlquery("UPDATE `bigtree_tags_rel` SET `table` = '$table' WHERE module = '".$f["id"]."'");
			}
		}
		sqlquery("UPDATE `bigtree_tags_rel` SET `table` = 'bigtree_pages' WHERE module = 0");
		// And drop the module column.
		sqlquery("ALTER TABLE `bigtree_tags_rel` DROP COLUMN `module`");
	}
	
	// BigTree 4.0RC2 update -- REVISION 10
	function _local_bigtree_update_10() {
		sqlquery("ALTER TABLE `bigtree_modules` ADD COLUMN `icon` VARCHAR(255) NOT NULL AFTER `class`");
	}
	
	// BigTree 4.0RC2 update -- REVISION 11
	function _local_bigtree_update_11() {
		// Got rid of the dropdown for Modules.
		sqlquery("ALTER TABLE `bigtree_module_groups` DROP COLUMN `in_nav`");
		// New Analytics stuff requires that we redo everything.
		sqlquery("UPDATE `bigtree_settings` SET value = '' WHERE id = 'bigtree-internal-google-analytics'");
	}

	// BigTree 4.0RC2 update -- REVISION 12
	function _local_bigtree_update_12() {
		// Add the return_url column to bigtree_module_forms.
		sqlquery("ALTER TABLE `bigtree_module_forms` ADD COLUMN `return_url` VARCHAR(255) NOT NULL AFTER `return_view`");
	}

	// BigTree 4.0RC2 update -- REVISION 13
	function _local_bigtree_update_13() {
		// Delete the "package" column from templates.
		sqlquery("ALTER TABLE `bigtree_templates` DROP COLUMN `package`");
	}

	// BigTree 4.0RC2 update -- REVISION 14
	function _local_bigtree_update_14() {
		// Allow NULL as an option for the item_id in bigtree_pending_changes
		sqlquery("ALTER TABLE `bigtree_pending_changes` CHANGE `item_id` `item_id` INT(11) UNSIGNED DEFAULT NULL");
		// Fix anything that had a 0 before as the item_id and wasn't pages.
		sqlquery("UPDATE `bigtree_pending_changes` SET item_id = NULL WHERE item_id = 0 AND `table` != 'bigtree_pages'");
	}

	// BigTree 4.0RC2 update -- REVISION 15
	function _local_bigtree_update_15() {
		// Adds the setting to disable tagging in pages
		global $admin;
		$admin->createSetting(array("id" => "bigtree-internal-disable-page-tagging", "type" => "checkbox", "name" => "Disable Tags in Pages"));
		// Adds a column to module forms to disable tagging.
		sqlquery("ALTER TABLE `bigtree_module_forms` ADD COLUMN `tagging` CHAR(2) NOT NULL AFTER `return_url`");
		// Default to tagging being on since it wasn't an option to turn it off previously.
		sqlquery("UPDATE `bigtree_module_forms` SET `tagging` = 'on'");
	}

	// BigTree 4.0RC2 update -- REVISION 16
	function _local_bigtree_update_16() {
		// Adds a sort column to the view cache
		sqlquery("ALTER TABLE `bigtree_module_view_cache` ADD COLUMN `sort_field` VARCHAR(255) NOT NULL AFTER `group_field`");
		// Force all the views to update their cache.
		sqlquery("TRUNCATE TABLE `bigtree_module_view_cache`");
	}

	// BigTree 4.0RC2 update -- REVISION 18
	function _local_bigtree_update_18() {
		// Adds a sort column to the view cache
		sqlquery("ALTER TABLE `bigtree_module_view_cache` ADD COLUMN `published_gbp_field` TEXT NOT NULL AFTER `gbp_field`");
		// Force all the views to update their cache.
		sqlquery("TRUNCATE TABLE `bigtree_module_view_cache`");
	}
?>
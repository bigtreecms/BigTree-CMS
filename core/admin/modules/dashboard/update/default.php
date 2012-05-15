<?
	$breadcrumb[] = array("title" => "System Update", "link" => "#");
	
	$current_revision = $cms->getSetting("bigtree-internal-revision");
	if ($current_revision < BIGTREE_REVISION) {
		while ($current_revision < BIGTREE_REVISION) {
			$current_revision++;
			if (function_exists("_local_bigtree_update_".$current_revision)) {
				eval("_local_bigtree_update_$current_revision();");
			}
		}
		
		$admin->updateSettingValue("bigtree-internal-revision",BIGTREE_REVISION);
	}
?>
<h1><span class="developer"></span>System Update</h1>
<div class="form_container">
	<section>
		<p>BigTree has been updated to <?=BIGTREE_VERSION?>.</p>
		<p>
			Your database has been upgraded to be compatible with this version.<br />
			Your <strong>old database</strong> has been backed up to <strong><?=$server_root?>cache/backup.sql</strong>
		</p>
	</section>
	<footer>
		<a href="<?=$admin_root?>dashboard/" class="button blue">Return to Dashboard</a>
	</footer>
</div>
<?
	// BigTree 4.0b5 update -- REVISION 1
	function _local_bigtree_update_1() {
		global $cms,$admin;
		
		// Update settings to make the value LONGTEXT
		sqlquery("ALTER TABLE `bigtree_settings` CHANGE `value` `value` LONGTEXT");
		
		// Drop the css/javascript columns from bigtree_module_forms and add preprocess
		sqlquery("ALTER TABLE `bigtree_module_forms` ADD COLUMN `preprocess` varchar(255) NOT NULL AFTER `title`, DROP COLUMN `javascript`, DROP COLUMN `css`");
		
		// Move Google Analytics information into a single setting
		$ga_cache = $cms->getSetting("bigtree-internal-google-analytics-cache");
		$ga_email = $cms->getSetting("bigtree-internal-google-analytics-email");
		$ga_password = $cms->getSetting("bigtree-internal-google-analytics-password");
		$ga_profile = $cms->getSetting("bigtree-internal-google-analytics-profile");
		
		$admin->createSetting(array(
			"id" => "bigtree-internal-google-analytics",
			"system" => "on",
			"encrypted" => "on"
		));
		$admin->updateSettingValue("bigtree-internal-google-analytics",array(
			"cache" => $ga_cache,
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
		sqlquery("DELETE FROM bigtree_settings WHERE id = 'bigtree-internal-google-analytics-cache' OR id = 'bigtree-internal-google-analytics-email' OR id = 'bigtree-internal-google-analytics-password' OR id = 'bigtree-internal-google-analytics-profile' OR id = 'bigtree-internal-rackspace-keys' OR id = 'bigtree-internal-rackspace-containers' OR id = 'bigtree-internal-s3-buckets' OR id = 'bigtree-internal-s3-keys'");
	}
?>
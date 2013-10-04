<?
	$ups = $cms->getSetting("bigtree-internal-storage");

	// Check if we have optipng installed.
	if (file_exists("/usr/bin/optipng")) {
		$ups["optipng"] = "/usr/bin/optipng";
	} elseif (file_exists("/usr/local/bin/optipng")) {
		$ups["optipng"] = "/usr/local/bin/optipng";
	}

	// Check if we have jpegtran installed.
	if (file_exists("/usr/bin/jpegtran")) {
		$ups["jpegtran"] = "/usr/bin/jpegtran";
	} elseif (file_exists("/usr/local/bin/jpegtran")) {
		$ups["jpegtran"] = "/usr/local/bin/jpegtran";
	}
	
	if ($_POST["access_key_id"] && $_POST["secret_access_key"]) {
		// We're going to test the S3 credentials.
		$s3 = new S3($_POST["access_key_id"],$_POST["secret_access_key"],true);
		$buckets = $s3->listBuckets();
		if ($buckets === false) {
			$admin->growl("Developer","Amazon S3 Login Failed","error");
			BigTree::redirect($developer_root."cloud-storage/amazon/");
			die();
		}
		$ups["service"] = "s3";
		$ups["s3"]["keys"] = array("access_key_id" => $_POST["access_key_id"], "secret_access_key" => $_POST["secret_access_key"]);
		// If they've chosen a bucket, make sure it exists.
		if ($_POST["bucket"]) {
			if ($s3->getBucket($_POST["bucket"]) === false) {
				$admin->updateSettingValue("bigtree-internal-storage",$ups);
				$admin->growl("Developer","Bucket Name Doesn't Exist","error");
				BigTree::redirect($developer_root."cloud-storage/amazon/");
				die();	
			}
			$ups["s3"]["bucket"] = $_POST["bucket"];
		// If we didn't choose a bucket, make one for them.
		} else {
			$x = 0;
			$bucket_created = false;
			while ($x < 5 && !$bucket_created) {
				$bucket = "bigtree-".uniqid();
				$bucket_created = $s3->putBucket($bucket);
				$x++;
			}
			if ($bucket_created) {
				$ups["s3"]["bucket"] = $bucket;
			} else {
				$admin->updateSettingValue("bigtree-internal-storage",$ups);
				$admin->growl("Developer","Couldn't Auto Generate Bucket","error");
				BigTree::redirect($developer_root."cloud-storage/amazon/");
				die();
			}
		}
	} else {
		$ups["service"] = "";
	}

	$admin->updateSettingValue("bigtree-internal-storage",$ups);	

	$admin->growl("Developer","Amazon S3 Enabled");
	BigTree::redirect($developer_root);
?>
<?
	$salesforce = new BigTreeSalesforceAPI;
	if ($salesforce->OAuthClient->Process()) {
		if ($salesforce->OAuthClient->access_token) {
			$salesforce->Connected = true;
			
			// Save token information and some user info for displaying connection info in the admin.
			$admin->updateSettingValue("bigtree-internal-salesforce-api",array(
				"key" => $salesforce->Settings["key"],
				"secret" => $salesforce->Settings["secret"],
				"token" => $salesforce->OAuthClient->access_token,
				"refresh_token" => $salesforce->OAuthClient->refresh_token,
				"issued" => $salesforce->OAuthClient->issued_at,
				"instance" => $salesforce->OAuthClient->instance_url,
				"signature" => $salesforce->OAuthClient->signature,
				"scope" => $salesforce->OAuthClient->scope
			));

			$admin->growl("Salesforce API","Connected");
			BigTree::redirect(DEVELOPER_ROOT."services/salesforce/");
		}
	}
	
	$admin->growl("Salesforce API","Unknown Error");
	BigTree::redirect(DEVELOPER_ROOT."services/salesforce/");
?>
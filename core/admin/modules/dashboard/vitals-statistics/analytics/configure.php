<?
	if ($admin->Level < 1) {
?>
<div class="container">
	<section>
		<p>You are not authorized to view this section.</p>
	</section>
</div>
<?
	} else {
?>
<div class="container">
	<? if ($token) { ?>
	<form method="post" action="<?=$mroot?>set-profile/" class="module">
		<section>
			<?
				$accounts = $analytics->API->management_accounts->listManagementAccounts();
			?>
			<fieldset>
				<label>Choose A Profile From The List Below</label>
				<?
					$property_lookup = array();
					foreach ($accounts->items as $account) {
						$properties = $analytics->API->management_webproperties->listManagementWebproperties($account->id);
						foreach ($properties->items as $property) {
							$property_lookup[] = array("account" => $account->id, "account_name" => $account->name, "property" => $property->id);
						}
					}
					
					if (count($property_lookup)) {
				?>
				<div id="analytics_profiles_loading"><img src="<?=ADMIN_ROOT?>images/spinner.gif" alt="" /> Loading Profiles: <span id="current_property">0</span> of <?=count($property_lookup)?> complete</div>
				<script>
					BigTree.localProperties = <?=json_encode($property_lookup)?>;
					BigTree.localCurrentProperty = 0;
					BigTree.localProfiles = [];
					BigTree.localGetProfile = function() {
						$.ajax("<?=ADMIN_ROOT?>ajax/dashboard/analytics/get-management-profiles/", { type: "POST", data: { account: BigTree.localProperties[BigTree.localCurrentProperty].account, property: BigTree.localProperties[BigTree.localCurrentProperty].property }, success: function(response) {
							
							for (i in response) {
								BigTree.localProfiles[BigTree.localProfiles.length] = { account: BigTree.localProperties[BigTree.localCurrentProperty].account_name, name: response[i].name, id: response[i].id };
							}
							
							BigTree.localCurrentProperty++;
							$("#current_property").html(BigTree.localCurrentProperty);
							if (BigTree.localCurrentProperty < BigTree.localProperties.length) {
								BigTree.localGetProfile();
							} else {
								html = '<select name="profile">';
								for (i in BigTree.localProfiles) {
									p = BigTree.localProfiles[i];
									html += '<option value="' + p.id + '">' + htmlspecialchars(p.account) + ' &mdash; ' + htmlspecialchars(p.name) + '</option>';
								}
								html += '</select>';
								$("#analytics_profiles_loading").html(html);
								BigTreeCustomControls();
								$("#set_button").show();
							}
						}});
					};
					
					BigTree.localGetProfile();
				</script>
				<?
					} else {
				?>
				<p class="error_message">No profiles were found in your Google Analytics account.</p>
				<?  	
					}
				?>
			</fieldset>
		</section>
		<footer>
			<input type="submit" value="Set Profile" class="blue" id="set_button" style="display: none;" />
			<a href="#" class="button" id="ga_disconnect">Disconnect</a>
		</footer>
	</form>
	
	<? } else { ?>
	<form method="get" action="<?=$mroot?>set-token/" class="module">	
		<section>
			<p>To connect Google Analytics you will need to login to your Google Analytics account by clicking the Authenticate button below. Once you have logged in you will be taken to a screen with a code in a box. Copy that code into the field that appears below to allow BigTree to access your Google Analytics information.</p>
			<fieldset>
				<input type="text" name="code" placeholder="Enter Code Here" />
			</fieldset>
		</section>
		<footer>
			<a href="<?=$analytics->Client->createAuthUrl()?>" class="button" id="google_button" target="_blank">Authenticate</a>
			<input type="submit" class="button blue" id="profile_button" value="Save Code" style="display: none;" />
		</footer>
	</form>
	<? } ?>		
</div>
<script>
	$("#google_button").click(function() {
		$(this).hide();
		$("#profile_button").show();
	});
	
	$("#ga_disconnect").click(function() {
		new BigTreeDialog("Disconnect Google Analytics","<p>Are you sure you want to disconnect your Google Analytics account? <br/ >This will remove all analytics data and can not be undone.</p>",function() {
			window.location.href = "<?=$mroot?>disconnect/";
		},"delete",false,"Disconnect");
		return false;
	});
</script>
<?
	}
?>
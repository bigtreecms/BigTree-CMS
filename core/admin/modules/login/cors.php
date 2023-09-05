<?php
	$cache_data = BigTreeCMS::cacheGet("org.bigtreecms.login-session", $_GET["key"]);
	$domains = array();
	
	foreach ($cache_data["remaining_sites"] as $site_key => $www_root) {
		$domains[] = $www_root;
	}
?>
<div id="login">
	<form method="post" action="" class="module">
		<h2>Logging In...</h2>
		<fieldset class="clear">
			<p>You are being logged into all available domains and will be redirected when the process is complete.</p>
			<p>Logging into <span id="login-domain"><?=$domains[0]?></span>...</p>
			
			<div class="js-multi-login-failed" style="display: none;">
				<p class="error_message" style="margin: 10px 0">
					We seem to be having trouble logging into alternate domains. You can continue onto the BigTree admin but may not see the BigTree bar or have preview functionality on your alternate domains.
				</p>
				
				<a class="button" href="<?=ADMIN_ROOT?>dashboard/">Continue</a>
			</div>
		</fieldset>
		<br />
	</form>
</div>
<script>
	(function() {
		var Domains = <?=json_encode($domains)?>;
		var Completed = 0;
		var Failures = 0;
		var Total = <?=count($domains)?>;

		function multiSiteLogin(index) {
			$("#login-domain").html(Domains[index]);
			
			$.ajax({
				url: Domains[index] + "?<?php if (!BigTree::getIsSSL()) { ?>no_ssl&<?php } ?>bigtree_login_redirect_session_key=" + encodeURIComponent("<?=$_GET["key"]?>"),
				xhrFields: { withCredentials: true }
			}).done(function() {
				Completed++;

				if (Completed === Total) {
					document.location.href = "<?=ADMIN_ROOT?>login/cors-complete/?key=" + encodeURIComponent("<?=$_GET["key"]?>");
				} else {
					multiSiteLogin(Completed);
				}
			}).fail((function() {
				// Try again
				multiSiteLogin(Completed);
				Failures++;
				
				if (Failures === 10) {
					$(".js-multi-login-failed").show();
				}
			}));
		}

		multiSiteLogin(0);
	})();
</script>
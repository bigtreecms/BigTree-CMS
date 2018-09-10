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
		</fieldset>
		<br />
	</form>
</div>
<script>
	(function() {
		var Domains = <?=json_encode($domains)?>;
		var Completed = 0;
		var Total = <?=count($domains)?>;

		for (var i = 0; i < Total; i++) {
			$.ajax({
				url: Domains[i] + "?<?php if (!BigTree::getIsSSL()) { ?>no_ssl&<?php } ?>bigtree_login_redirect_session_key=" + escape("<?=$_GET["key"]?>"),
				xhrFields: { withCredentials: true }
			}).done(function() {
				Completed++;

				if (Completed == Total) {
					document.location.href = "<?=ADMIN_ROOT?>login/cors-complete/?key=" + escape("<?=$_GET["key"]?>");
				}
			});
		}
	})();
</script>
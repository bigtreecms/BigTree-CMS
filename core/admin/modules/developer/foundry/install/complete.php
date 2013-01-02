<?
	if (isset($_SESSION["bigtree_admin"]["package_instructions"])) {
		$instructions = $_SESSION["bigtree_admin"]["package_instructions"];
		unset($_SESSION["bigtree_admin"]["package_instructions"]);
	} else {
		$instructions = false;
	}
	
	if (isset($_SESSION["bigtree_admin"]["package_error"])) {
		$code = $_SESSION["bigtree_admin"]["package_code"];
		$error = $_SESSION["bigtree_admin"]["package_error"];
		unset($_SESSION["bigtree_admin"]["package_error"]);
		unset($_SESSION["bigtree_admin"]["package_code"]);
	} else {
		$error = false;
	}
?>
<div class="container">
	<section>
		<? if ($instructions) { ?>
		<h3>Instructions</h3>
		<p><?=nl2br(htmlspecialchars(base64_decode($instructions)))?></p>
		<? } ?>
		
		<? if ($error) { ?>
		<h3>Warning</h3>
		<p>The following warning occurred:</p>
		<pre><code><?=$error?></code></pre>
		<p>While running:</p>
		<pre><code class="language-php"><?=htmlspecialchars($code)?></code></pre>
		<? } ?>
		
		<? if (!$error && !$instructions) { ?>
		<p>Your package has been successfully installed.</p>
		<? } ?>
	</section>
</div>
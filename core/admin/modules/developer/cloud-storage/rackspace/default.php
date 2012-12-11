<?
	$keys = $cms->getSetting("bigtree-internal-rackspace-keys");
	BigTree::globalizeArray($keys,array("htmlspecialchars"));
?>
<div class="container">
	<form method="post" action="<?=ADMIN_ROOT?>developer/cloud-storage/rackspace/update/" class="module">
		<section>
			<div class="alert">
				<p>To enable usage of Rackspace Cloud Files for all BigTree uploads enter your access keys below.<br />Please note that this change is not retroactive -- only future uploads will be stored on Rackspace Cloud Files.</p>
			</div>	
			<fieldset>
				<label>API Key</label>
				<input type="text" name="api_key" value="<?=$api_key?>" />
			</fieldset>
			<fieldset>
				<label>Username</label>
				<input type="text" name="username" value="<?=$username?>" />
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Update" />
		</footer>
	</form>
</div>
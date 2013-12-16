<div class="container">
	<form method="post" action="<?=DEVELOPER_ROOT?>cloud-storage/google/activate/" class="module">	
		<section>
			<p>To activate Google Cloud Storage you must follow these steps:</p>
			<hr />
			<ol>
				<li>Login to the Google Cloud Console and create a project.</li>
				<li>Click into the project and enter the "API &amp; auth" section. Enable access to the Google Cloud Storage JSON API.</li>
				<li>Click into the "Registered apps" section and create an application using the "Web Application" type.</li>
				<li>Expaned the "Oauth 2.0 Client" block and enter the Client ID and Client Secret from that block below.</li>
				<li>Enter <?=DEVELOPER_ROOT?>cloud-storage/google/return/ as the "Redirect URI" in the OAuth pane and click the button below to save it.</li>
				<li>Follow the OAuth process of allowing BigTree/your application access to your Google Cloud Storage account.</li>
			</ol>
			<hr />
			<fieldset>
				<label>Project ID</label>
				<input type="text" name="project" value="<?=htmlspecialchars($api->Settings["project"])?>" />
			</fieldset>
			<fieldset>
				<label>Client ID</label>
				<input type="text" name="key" value="<?=htmlspecialchars($api->Settings["key"])?>" />
			</fieldset>
			<fieldset>
				<label>Client Secret</label>
				<input type="text" name="secret" value="<?=htmlspecialchars($api->Settings["secret"])?>" />
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Activate Google Cloud Storage" />
		</footer>
	</form>
</div>
<div class="container">
	<summary><h2>Upgrade BigTree</h2></summary>
	<section>
		<div class="alert">
			<span></span>
			<p>
				<strong>FTP Not Available</strong>
			</p>
		</div>
		<p>Unfortunately, your server does not support FTP. BigTree does not currently support SFTP upgrades. If you wish to upgrade BigTree manually, these are the recommended steps to limit any potential downtime:</p>
		<hr />
		<ol>
			<li>Backup your existing database.</li>
			<li>Download the version of BigTree you wish to upgrade to from the <a href="http://www.bigtreecms.org/" target="_blank">official BigTree website</a>.</li>
			<li>Extract the zip file locally and rename the "core" directory to "core-new".</li>
			<li>Upload the "core-new" directory to the root of your website via SFTP or any other method.</li>
			<li>Rename your existing "core" directory to "core-old".</li>
			<li>Rename the new "core-new" directory to "core".</li>
			<li>Immediately login to your admin as a developer and proceed with the update script if you are prompted.</li>
		</ol>
		<hr />
		<p>If needed, you can reverse the process using your "core-old" and SQL dump to downgrade. You are free to delete the "core-old" directory once you verify your upgrade went smoothly.</p>
	</section>
</div>
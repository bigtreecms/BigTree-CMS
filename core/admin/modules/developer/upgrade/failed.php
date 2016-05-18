<?php
	namespace BigTree;
?>
<div class="container">
	<summary><h2><?=Text::translate("Upgrade BigTree")?></h2></summary>
	<section>
		<p><?=Text::translate("Unfortunately, BigTree was unable to modify files locally and was unable to connect via FTP or SFTP. If you wish to upgrade BigTree manually, these are the recommended steps to limit any potential downtime:")?></p>
		<hr />
		<ol>
			<li><?=Text::translate("Backup your existing database.")?></li>
			<li><?=Text::translate('Download the version of BigTree you wish to upgrade to from the <a href=":website:" target="_blank">official BigTree website</a>.', false, array(":website:" => "http://www.bigtreecms.org/"))?></li>
			<li><?=Text::translate('Extract the zip file locally and rename the "core" directory to "core-new".')?></li>
			<li><?=Text::translate('Upload the "core-new" directory to the root of your website via SFTP or any other method.')?></li>
			<li><?=Text::translate('Rename your existing "core" directory to "core-old".')?></li>
			<li><?=Text::translate('Rename the new "core-new" directory to "core".')?></li>
			<li><?=Text::translate("Immediately login to your admin as a developer and proceed with the update script if you are prompted.")?></li>
		</ol>
		<hr />
		<p><?=Text::translate('If needed, you can reverse the process using your "core-old" and SQL dump to downgrade. You are free to delete the "core-old" directory once you verify your upgrade went smoothly.')?></p>
	</section>
</div>
<?php
	namespace BigTree;
	
	$failure_text = Text::translate(
		'BigTree attempted and failed to install the extension via FTP, SFTP, or via local file permissions.<br><br>' .
		'You will need to manually download the zip file from the <a href=":repository_link:" target="_blank">BigTree extensions repository</a>' .
		' and upgrade via replacing the /extensions/:extension_id:/ folder with the one from the new zip.',
		false,
		array(
			":repository_link:" => "http://www.bigtreecms.org/extensions/",
			":extension_id:" => htmlspecialchars($_GET["id"])
		)
	);
?>
<div class="container">
	<div class="container_summary"><h2><?=Text::translate("Extension Upgrade Failed")?></h2></div>
	<section>
		<p><?=$failure_text?></p>
	</section>
</div>
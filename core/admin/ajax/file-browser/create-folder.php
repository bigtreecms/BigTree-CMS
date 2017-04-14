<?php
	namespace BigTree;
	
	CSRF::verify();
	
	$parent_folder = new ResourceFolder($_POST["folder"]);
	
	if ($parent_folder->UserAccessLevel == "p") {
		ResourceFolder::create($_POST["folder"], $_POST["name"]);
		$message = "Successfully Created Folder";
	} else {
		$message = "Access Denied";
	}
?>
<html>
	<head>
		<link rel="stylesheet" href="<?=ADMIN_ROOT?>css/main.css" />
	</head>
	<body style="background: transparent;">
		<p class="file_browser_response"><?=Text::translate($message)?></p>
		<script>
			parent.BigTreeFileManager.finishedUpload();
		</script>
	</body>
</html>
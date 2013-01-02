<?
	$admin->createResourceFolder($_POST["folder"],$_POST["name"]);
?>
<html>
	<head>
		<link rel="stylesheet" href="<?=ADMIN_ROOT?>css/main.css" />
	</head>
	<body style="background: transparent;">
		<p class="file_browser_response">Successfully Created Folder</p>
		<script>
			parent.BigTreeFileManager.finishedUpload();
		</script>
	</body>
</html>
<?
	$admin->createResourceFolder($_POST["folder"],$_POST["name"]);
?>
<html>
	<head>
		<link rel="stylesheet" href="<?=$admin_root?>css/main.css" />
	</head>
	<body style="background: transparent;">
		<p class="file_browser_response">Successfully Created Folder</p>
		<script type="text/javascript">
			parent.BigTreeFileManager.finishedUpload();
		</script>
	</body>
</html>
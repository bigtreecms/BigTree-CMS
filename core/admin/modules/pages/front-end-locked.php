<?
	$user = $admin->getUser($f["user"]);
?>
<html>
	<head>
		<link rel="stylesheet" href="<?=ADMIN_ROOT?>css/main.css" type="text/css" media="screen" charset="utf-8" />
		<script type="text/javascript" src="<?=ADMIN_ROOT?>js/lib.js"></script>
		<script type="text/javascript" src="<?=ADMIN_ROOT?>js/main.js"></script>
		<script type="text/javascript" src="<?=ADMIN_ROOT?>js/pages.js"></script>
	</head>
	<body>
		<div class="bigtree_dialog_window front_end_editor">
			<h2><strong>Warning:</strong> This page is currently locked.</h2>
			<form class="bigtree_dialog_form" method="post" action="<?=ADMIN_ROOT?>pages/front-end-update/<?=$page["id"]?>/" enctype="multipart/form-data">
				<div class="overflow">
					<p>
						<strong><?=$user["name"]?></strong> currently has this page locked for editing.  It was last accessed by <strong><?=$user["name"]?></strong> on <strong><?=date("F j, Y @ g:ia",strtotime($f["last_accessed"]))?></strong>.<br />
					If you would like to edit this page anyway, please click "Unlock" below.  Otherwise, click "Cancel".
					</p>			
				</div>
				<footer>
					<a class="button cancel" href="#">Cancel</a>
					<a class="button blue" href="?force=true">Unlock</a>
				</footer>
			</form>
		</div>
		<script type="text/javascript">
			$("footer .cancel").click(function() {
				parent.bigtree_bar_cancel();
				
				return false;
			});
		</script>
	</body>
</html>
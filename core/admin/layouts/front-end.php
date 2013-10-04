<html>
	<head>
		<link rel="stylesheet" href="<?=ADMIN_ROOT?>css/main.css" type="text/css" media="screen" charset="utf-8" />
		<?
			if (isset($bigtree["css"]) && is_array($bigtree["css"])) {
				foreach ($bigtree["css"] as $style) {
		?>
		<link rel="stylesheet" href="<?=ADMIN_ROOT?>css/<?=$style?>" type="text/css" media="screen" />
		<?
				}
			}
		?>
		<script src="<?=ADMIN_ROOT?>js/lib.js"></script>
		<script src="<?=ADMIN_ROOT?>js/main.js"></script>
		<script src="<?=ADMIN_ROOT?>js/pages.js"></script>
		<script src="<?=ADMIN_ROOT?>js/tiny_mce/tiny_mce.js"></script>
		<?
			if (isset($bigtree["js"]) && is_array($bigtree["js"])) {
				foreach ($bigtree["js"] as $script) {
		?>
		<script src="<?=ADMIN_ROOT?>js/<?=$script?>"></script>
		<?
				}
			}
		?>
		<style type="text/css">
			#mceModalBlocker { display: none !important; }
		</style>
	</head>
	<body>
		<div class="bigtree_dialog_window front_end_editor">
			<?=$bigtree["content"]?>
		</div>
	</body>
</html>
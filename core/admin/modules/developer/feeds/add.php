<div class="container">
	<form method="post" action="<?=$section_root?>create/" class="module">
		<? include BigTree::path("admin/modules/developer/feeds/_form-content.php") ?>
		<footer>
			<input type="submit" class="button blue" value="Create" />
		</footer>
	</form>
</div>

<? include BigTree::path("admin/modules/developer/feeds/_common-js.php") ?>
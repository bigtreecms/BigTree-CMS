<?
	BigTree::globalizeArray($_SESSION["bigtree_admin"]["form_data"]);

	// If we have crops, let's con
	if (count($crops)) {
		if ($page) {
			$return_link = $bigtree["form_root"]."crop/$page/";
		} else {
			$return_link = $bigtree["form_root"]."crop/";
		}
	} 
	// Override the default H1
	$bigtree["page_override"] = array("title" => "Errors Occurred","icon" => "page_404");
?>
<div class="container">
	<section>
		<div class="alert">
			<span></span>
			<p>Your submission had <?=count($fails)?> error<? if (count($fails) != 1) { ?>s<? } ?>.</p>
		</div>
		<div class="table error_table">
			<header>
				<span class="view_column field">Field</span>
				<span class="view_column error">Error</span>
			</header>
			<ul>
				<? foreach ($fails as $fail) { ?>
				<li>
					<section class="view_column field"><?=$fail["field"]?></section>
					<section class="view_column error"><?=$fail["error"]?></section>
				</li>
				<? } ?>
			</ul>
		</div>
	</section>
	<footer>
		<a href="<?=$return_link?>" class="button blue">Continue</a> &nbsp; 
		<a href="<?=$edit_link?>" class="button">Edit</a> &nbsp; 
		<? if (!$page) { ?>
		<a href="#" class="delete button red">Delete</a>
		<? } ?>
	</footer>
</div>

<script>
	$(".delete").click(function() {
		$.ajax("<?=ADMIN_ROOT?>ajax/auto-modules/views/delete/?view=<?=$view["id"]?>&id=<?=$id?>", {
			complete: function() {
				document.location = '<?=$return_link?>';
			}
		});

		return false;
	});
</script>
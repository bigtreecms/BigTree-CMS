<?php
	namespace BigTree;

	\BigTree::globalizeArray($_SESSION["bigtree_admin"]["form_data"]);
?>
<div class="container">
	<section>
		<div class="alert">
			<span></span>
			<p><?=Text::translate("Your submission had ")?> <?=count($errors)?> <?=Text::translate((count($errors) != 1) ? "errors" : "error")?>.</p>
		</div>
		<div class="table error_table">
			<header>
				<span class="view_column field"><?=Text::translate("Field")?></span>
				<span class="view_column error"><?=Text::translate("Error")?></span>
			</header>
			<ul>
				<?php foreach ($errors as $error) { ?>
				<li>
					<section class="view_column field"><?=$error["field"]?></section>
					<section class="view_column error"><?=$error["error"]?></section>
				</li>
				<?php } ?>
			</ul>
		</div>
	</section>
	<footer>
		<a href="<?=$bigtree["form_root"]."?id=".$bigtree["form"]["id"]."&hash=".$bigtree["form"]["hash"]?>" class="button"><?=Text::translate("Return & Edit", true)?></a>
	</footer>
</div>
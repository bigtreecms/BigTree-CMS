<?php
	namespace BigTree;

	Globalize::arrayObject($_SESSION["bigtree_admin"]["form_data"]);

	// Override the default H1
	$bigtree["page_override"] = array("title" => Text::translate("Errors Occurred"),"icon" => "page_404");
?>
<div class="container">
	<section>
		<div class="alert">
			<span></span>
			<p><?=Text::translate("Your submission had")?> <?=count($errors)?> <?=Text::translate((count($errors) != 1) ? "errors" : "error")?>.</p>
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
		<a href="<?=$return_link?>" class="button blue"><?=Text::translate("Continue", true)?></a> &nbsp; 
		<a href="<?=$edit_link?>" class="button"><?=Text::translate("Return & Edit", true)?></a> &nbsp; 
	</footer>
</div>
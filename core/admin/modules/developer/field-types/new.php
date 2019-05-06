<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */

	$id = htmlspecialchars(end(Router::$Path));
?>
<div class="container">
	<section>
		<p><?=Text::translate("Your new field type is setup and ready to use.")?></p>
		<ul>
			<li><?=SERVER_ROOT?>custom/admin/field-types/<?=$id?>/draw.php &mdash; <?=Text::translate("Your drawing file.")?></li>
			<li><?=SERVER_ROOT?>custom/admin/field-types/<?=$id?>/process.php &mdash; <?=Text::translate("Your processing file.")?></li>
			<li><?=SERVER_ROOT?>custom/admin/field-types/<?=$id?>/settings.php &mdash; <?=Text::translate("Your field options file.")?></li>
		</ul>
	</section>
</div>


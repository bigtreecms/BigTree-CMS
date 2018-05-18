<?php
	$id = end($bigtree["path"]);
?>
<div class="container">
	<section>
		<p>Your new field type is setup and ready to use.</p>
		<ul>
			<li><?=SERVER_ROOT?>custom/admin/field-types/<?=$id?>/draw.php &mdash; Your drawing file.</li>
			<li><?=SERVER_ROOT?>custom/admin/field-types/<?=$id?>/process.php &mdash; Your processing file.</li>
			<li><?=SERVER_ROOT?>custom/admin/field-types/<?=$id?>/settings.php &mdash; Your field settings file.</li>
		</ul>
	</section>
</div>
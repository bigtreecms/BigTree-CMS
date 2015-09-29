<?php
	BigTree::globalizeArray($_SESSION['bigtree_admin']['form_data']);
?>
<div class="container">
	<section>
		<div class="alert">
			<span></span>
			<p>Your submission had <?=count($errors)?> error<?php if (count($errors) != 1) {
    ?>s<?php 
} ?>.</p>
		</div>
		<div class="table error_table">
			<header>
				<span class="view_column field">Field</span>
				<span class="view_column error">Error</span>
			</header>
			<ul>
				<?php foreach ($errors as $error) {
    ?>
				<li>
					<section class="view_column field"><?=$error['field']?></section>
					<section class="view_column error"><?=$error['error']?></section>
				</li>
				<?php 
} ?>
			</ul>
		</div>
	</section>
	<footer>
		<a href="<?=$bigtree['form_root'].'?id='.$bigtree['form']['id'].'&hash='.$bigtree['form']['hash']?>" class="button">Return &amp; Edit</a>
	</footer>
</div>
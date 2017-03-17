<?
	$admin->verifyCSRFToken();
	
	BigTree::globalizePOSTVars();

	$module = end($bigtree["path"]);

	$default_position = isset($default_position) ? $default_position : "";
	$embed = $admin->createModuleEmbedForm($module,$title,$table,$fields,$hooks,$default_position,$default_pending,$css,$redirect_url,$thank_you_message);

	$admin->growl("Developer","Created Embeddable Form");
?>
<div class="container">
	<section>
		<h3><?=$title?></h3>
		<p>Your embeddable form has been created. You can copy and paste the code below to embed this form.</p>
		<textarea><?=$embed?></textarea>
	</section>
	<footer>
		<a href="<?=DEVELOPER_ROOT?>modules/edit/<?=$module?>/" class="button blue">Return to Module</a>
	</footer>
</div>
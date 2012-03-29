<?
	$breadcrumb[] = array("link" => $mroot . "client-id/", "title" => "Client ID");
	
	if (end($commands) == "save" && isset($_POST["instagram_client_id"])) {
		if ($btxInstagramAPI->setClientID($_POST["instagram_client_id"])) {
			BigTree::redirect($mroot);
		} else {
			$userError = true;
		}
	}
	
	$view["title"] = "Client ID";
		
	include "_heading.php";
	include BigTree::path("admin/auto-modules/_nav.php"); 
?>
<div class="form_container" id="instagram_api">
	<form method="post" action="<?=$mroot?>client-id/save/" class="module">
		<section>
			<p>Enter an Instagram Client ID.</p>
			<br />
			<? if ($userError) { ?>
			<p class="error_message">The Client ID you entered was invalid.</p>
			<? } ?>
			<div class="left">
				<fieldset>
					<label>Client ID</label>
					<input type="text" name="instagram_client_id" value="<?=$_POST["instagram_client_id"]?>" />
				</fieldset>
			</div>
		</section>
		<footer>
			<input type="submit" value="Save" class="blue" />
		</footer>
	</form>
</div>
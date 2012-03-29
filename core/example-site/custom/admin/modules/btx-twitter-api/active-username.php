<?
	$breadcrumb[] = array("link" => $mroot . "active-username/", "title" => "Active Username");
	
	if (end($commands) == "save" && isset($_POST["twitter_api_active_username"])) {
		if ($btxTwitterAPI->setActiveUsername($_POST["twitter_api_active_username"])) {
			BigTree::redirect($mroot);
		} else {
			$userError = true;
		}
	}
	
	$view["title"] = "Active Username";
		
	include "_heading.php";
	include BigTree::path("admin/auto-modules/_nav.php"); 
?>
<div class="form_container" id="twitter_api">
	<form method="post" action="<?=$mroot?>active-username/save/" class="module">
		<section>
			<p>Enter the active username.</p>
			<br />
			<? if ($userError) { ?>
			<p class="error_message">Please enter a username</p>
			<? } ?>
			<div class="left">
				<fieldset>
					<label>Username</label>
					<input type="text" name="twitter_api_active_username" value="<?=$_POST["twitter_api_active_username"]?>" />
				</fieldset>
			</div>
		</section>
		<footer>
			<input type="submit" value="Save" class="blue" />
		</footer>
	</form>
</div>
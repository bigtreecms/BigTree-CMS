<?
	$users = $admin->getUsers();
	
	$send_to = array();
	$subject = "";
	$message = "";
	$error = false;
	
	if (isset($_SESSION["saved_message"])) {
		$send_to = $_SESSION["saved_message"]["send_to"];
		$subject = htmlspecialchars($_SESSION["saved_message"]["subject"]);
		$message = htmlspecialchars($_SESSION["saved_message"]["message"]);
		$error = true;
		unset($_SESSION["saved_message"]);
	}
?>
<div class="container">
	<form method="post" action="../create/" id="message_form">
		<? if (count($users) > 1) { ?>
		<section>
			<p<? if (!$error) { ?> style="display: none;"<? } ?> class="error_message">Errors found! Please fix the highlighted fields before submitting.</p>
			<fieldset id="send_to"<? if ($error && !count($send_to)) { ?> class="form_error"<? } ?>>
				<label class="required">Send To<? if ($error && !count($send_to)) { ?><span class="form_error_reason">Required</span><? } ?></label>
				<div class="multi_widget many_to_many">
					<section>
						<p>No users selected. Click "Add User" to add a user to the list.</p>
					</section>
					<ul>
						<?
							$x = 0;
							if (is_array($send_to)) {
								foreach ($send_to as $id) {
						?>
						<li>
							<input type="hidden" name="send_to[<?=$x?>]" value="<?=htmlspecialchars($id)?>" />
							<p><?=htmlspecialchars($users[$id]["name"])?></p>
							<a href="#" class="icon_delete"></a>
						</li>
						<?
									$x++;
								}
							}
						?>
					</ul>
					<footer>
						<select>
							<?
								foreach ($users as $item) {
									if ($item["id"] != $admin->ID) {
							?>
							<option value="<?=$item["id"]?>"><?=htmlspecialchars($item["name"])?></option>
							<?
									}
								}
							?>
						</select>
						<a href="#" class="add button"><span class="icon_small icon_small_add"></span>Add User</a>
					</footer>
				</div>
			</fieldset>
			<fieldset<? if ($error && !$subject) { ?> class="form_error"<? } ?>>
				<label class="required">Subject<? if ($error && !$subject) { ?><span class="form_error_reason">Required</span><? } ?></label>
				<input type="text" name="subject"  class="required" value="<?=$subject?>" />
			</fieldset>
			<fieldset<? if ($error && !$message) { ?> class="form_error"<? } ?>>
				<label class="required">Message<? if ($error && !$message) { ?><span class="form_error_reason">Required</span><? } ?></label>
				<textarea name="message" id="message" class="required"><?=$message?></textarea>
			</fieldset>
		</section>
		<footer>
			<a href="../" class="button">Discard</a>
			<input type="submit" class="button blue" value="Send Message" />
		</footer>
		<? } else { ?>
		<section>
			<p>There must be more then one active user to send messages.</p>
		</section>
		<? } ?>
	</form>
</div>
<?
	$bigtree["html_fields"] = array("message");
	include BigTree::path("admin/layouts/_html-field-loader.php");
?>
<script>
	new BigTreeManyToMany("send_to",<?=$x?>,"send_to",false);
	new BigTreeFormValidator("#message_form");
</script>
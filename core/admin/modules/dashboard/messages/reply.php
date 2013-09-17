<?
	// Make sure the user has the right to see this message
	$parent = $admin->getMessage(end($bigtree["path"]));

	// If the original message doesn't exist or you don't have access to it.
	if (!$parent) {
?>
<div class="container">
	<section>
		<h3>Error</h3>
		<p>This message either does not exist or you do not have permission to view it.</p>
	</section>
</div>
<?
		$admin->stop();
	}

	$users = $admin->getUsers();
		
	if (isset($_SESSION["saved_message"])) {
		$send_to = $_SESSION["saved_message"]["send_to"];
		$subject = htmlspecialchars($_SESSION["saved_message"]["subject"]);
		$message = htmlspecialchars($_SESSION["saved_message"]["message"]);
		$error = true;
		unset($_SESSION["saved_message"]);
	} else {
		$subject = "RE: ".$parent["subject"];
		$message = "";
		$error = false;
		
		// Generate the recipient names from the parent if we're replying to all, otherwise, just use the sender.
		$send_to = array();
		if (isset($reply_all) || $parent["sender"] == $admin->ID) {
			$p_recipients = explode("|",trim($parent["recipients"],"|"));
			$p_recipients[] = $parent["sender"];
			foreach ($p_recipients as $r) {
				if ($r != $admin->ID) {
					$send_to[] = $r;
				}
			}
		} else {
			$send_to[] = $parent["sender"];
		}
	}
?>
<div class="container">
	<form method="post" action="<?=ADMIN_ROOT?>dashboard/messages/create-reply/" id="message_form">
		<input type="hidden" name="response_to" value="<?=htmlspecialchars(end($bigtree["path"]))?>" />
		<section>
			<p<? if (!$error) { ?> style="display: none;"<? } ?> class="error_message">Errors found! Please fix the highlighted fields before submitting.</p>
			<fieldset id="send_to"<? if ($error && !count($send_to)) { ?> class="form_error"<? } ?>>
				<label class="required">Send To<? if ($error && !count($send_to)) { ?><span class="form_error_reason">Required</span><? } ?></label>
				<div class="multi_widget many_to_many">
					<section style="display: none;">
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
								foreach ($users as $id => $u) {
									if ($item["id"] != $admin->ID) {
							?>
							<option value="<?=$id?>"><?=htmlspecialchars($u["name"])?></option>
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
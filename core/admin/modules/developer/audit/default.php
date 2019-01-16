<?php
	namespace BigTree;

	$users = User::all("name ASC", true);
	$deleted_users = Setting::value("bigtree-internal-deleted-users");
	$deleted_translation = Text::translate("DELETED");
?>
<div class="container">
	<form method="get" action="<?=DEVELOPER_ROOT?>audit/search/">
		<?php CSRF::drawPOSTToken(); ?>
		<section>
			<fieldset>
				<label for="audit_field_user"><?=Text::translate("User")?></label>
				<select id="audit_field_user" name="user">
					<option></option>
					<?php
						foreach ($users as $user) {
					?>
					<option value="<?=$user["id"]?>"><?=$user["name"]?></option>
					<?php
						}
						
						foreach ($deleted_users as $id => $user) {
					?>
					<option value="<?=$id?>"><?=$user["name"]?> (<?=$deleted_translation?>)</option>
					<?php
						}
					?>
				</select>
			</fieldset>
			<fieldset>
				<label for="audit_field_table"><?=Text::translate("Table")?></label>
				<select id="audit_field_table" name="table">
					<option></option>
					<optgroup label="Core">
						<option value="bigtree_pages"><?=Text::translate("Pages")?></option>
						<option value="bigtree_users"><?=Text::translate("Users")?></option>
						<option value="bigtree_settings"><?=Text::translate("Settings")?></option>
					</optgroup>
					<optgroup label="Modules">
						<?php SQL::drawTableSelectOptions() ?>
					</optgroup>
				</select>
			</fieldset>
			<fieldset>
				<label for="audit_field_start"><?=Text::translate("Start Date")?></label>
				<input id="audit_field_start" type="text" name="start" autocomplete="off" class="date_time_picker" />
				<span class="icon_small icon_small_calendar date_picker_icon"></span>
			</fieldset>
			<fieldset>
				<label for="audit_field_end"><?=Text::translate("End Date")?></label>
				<input id="audit_field_end" type="text" name="end" autocomplete="off" class="date_time_picker" />
				<span class="icon_small icon_small_calendar date_picker_icon"></span>
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="<?=Text::translate("Search", true)?>" />
		</footer>
	</form>
</div>
<?php
	namespace BigTree;
?>
<form method="post" action="<?=ADMIN_ROOT?>dashboard/vitals-statistics/404/create-301/" id="create_301_form">
	<?php CSRF::drawPOSTToken(); ?>
	<div class="container">
		<section>
			<?php
				if (is_array(Router::$Config["sites"]) && count(Router::$Config["sites"]) > 1) {
			?>
			<fieldset>
				<label for="redirect_field_site_key">Site <small>(if you enter a full URL into "From" this will be automatically infered based on the URL)</small></label>
				<select id="redirect_field_site_key" name="site_key">
					<?php
						foreach (Router::$Config["sites"] as $site_key => $site) {
							$domain = parse_url($site["domain"],  PHP_URL_HOST);
					?>
					<option value="<?=Text::htmlEncode($site_key)?>"><?=$domain?></option>
					<?php
						}
					?>
				</select>
			</fieldset>
			<?php
				}
			?>
			<fieldset>
				<label for="redirect_field_from"><?=Text::translate("From")?> <small>(<?=Text::translate("can be a full URL or just the piece after your domain")?>)</small></label>
				<input id="redirect_field_from" type="text" name="from" class="required" />
			</fieldset>
			<fieldset>
				<label for="redirect_field_to"><?=Text::translate("To")?> <small>(<?=Text::translate("a full URL — include http://")?>)</small></label>
				<input id="redirect_field_to" type="text" name="to" class="required" />
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="blue" value="<?=Text::translate("Create", true)?>" />
		</footer>
	</div>
</form>

<script>
	BigTreeFormValidator("#create_301_form",false);
</script>
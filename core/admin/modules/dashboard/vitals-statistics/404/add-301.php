<form method="post" action="<?=ADMIN_ROOT?>dashboard/vitals-statistics/404/create-301/" id="create_301_form">
	<?php $admin->drawCSRFToken(); ?>
	<div class="container">
		<section>
			<?php
				if (is_array($bigtree["config"]["sites"]) && count($bigtree["config"]["sites"]) > 1) {
			?>
			<fieldset>
				<label>Site <small>(if you enter a full URL into "From" this will be automatically infered based on the URL)</small></label>
				<select name="site_key">
					<?php
						foreach ($bigtree["config"]["sites"] as $site_key => $site) {
							$domain = parse_url($site["domain"],  PHP_URL_HOST);
					?>
					<option value="<?=BigTree::safeEncode($site_key)?>"><?=$domain?></option>
					<?php
						}
					?>
				</select>
			</fieldset>
			<?php
				}
			?>
			<fieldset>
				<label>From <small>(can be a full URL or just the piece after your domain)</small></label>
				<input type="text" name="from" class="required" />
			</fieldset>
			<fieldset>
				<label>To <small>(a full URL include http://)</small></label>
				<input type="text" name="to" class="required" />
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="blue" value="Create" />
		</footer>
	</div>
</form>
<script>
	BigTreeFormValidator("#create_301_form",false);
</script>
<?php
	namespace BigTree;
?>
<div class="container">
	<form method="post" action="<?=ADMIN_ROOT.$bigtree["module"]["route"]."/".$bigtree["module_action"]["route"]."/".$bigtree["report"]["type"]?>/">
		<section>
			<?php
				foreach ($bigtree["report"]["filters"] as $id => $filter) {
			?>
			<fieldset>
				<label><?=$filter["title"]?></label>
				<?php include Router::getIncludePath("admin/auto-modules/reports/filters/".$filter["type"].".php") ?>	
			</fieldset>
			<?php
				}
			?>
			<div class="sub_section last">
				<fieldset class="float_margin">
					<label><?=Text::translate("Sort By")?></label>
					<select name="*sort[field]">
						<?php
							if ($bigtree["report"]["type"] == "csv") {
								foreach ($bigtree["report"]["fields"] as $key => $title) {
						?>
						<option value="<?=htmlspecialchars($key)?>"><?=htmlspecialchars($title)?></option>
						<?php
								}
							} else {
								foreach ($bigtree["view"]["fields"] as $key => $field) {
						?>
						<option value="<?=htmlspecialchars($key)?>"><?=$field["title"]?></option>
						<?php
								}
							}
						?>
					</select>
				</fieldset>
				<fieldset>
					<label><?=Text::translate("Sort Order")?></label>
					<select name="*sort[order]">
						<option value="ASC"><?=Text::translate("Ascending")?></option>
						<option value="DESC"><?=Text::translate("Descending")?></option>
					</select>
				</fieldset>
			</div>
		</section>
		<footer>
			<input type="submit" class="button blue" value="<?=Text::translate("Submit", true)?>" />
		</footer>
	</form>
</div>
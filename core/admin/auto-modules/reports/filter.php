<div class="container">
	<form method="post" action="<?=ADMIN_ROOT.$bigtree["module"]["route"]."/".$bigtree["module_action"]["route"]."/".$bigtree["report"]["type"]?>/">
		<section>
			<?
				$bigtree["datepickers"] = array();
				foreach ($bigtree["report"]["filters"] as $id => $filter) {
			?>
			<fieldset>
				<label><?=$filter["title"]?></label>
				<? include BigTree::path("admin/auto-modules/reports/filters/".$filter["type"].".php") ?>	
			</fieldset>
			<?
				}
			?>
			<div class="sub_section last">
				<fieldset class="float_margin">
					<label>Sort By</label>
					<select name="*sort[field]">
						<?
							if ($bigtree["report"]["type"] == "csv") {
								foreach ($bigtree["report"]["fields"] as $key => $title) {
						?>
						<option value="<?=htmlspecialchars($key)?>"><?=htmlspecialchars($title)?></option>
						<?
								}
							} else {
								foreach ($bigtree["view"]["fields"] as $key => $field) {
						?>
						<option value="<?=htmlspecialchars($key)?>"><?=$field["title"]?></option>
						<?
								}
							}
						?>
					</select>
				</fieldset>
				<fieldset>
					<label>Sort Order</label>
					<select name="*sort[order]">
						<option value="ASC">Ascending</option>
						<option value="DESC">Descending</option>
					</select>
				</fieldset>
			</div>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Submit" />
		</footer>
	</form>
</div>
<script>
	<? foreach ($bigtree["datepickers"] as $id) { ?>
	$("#<?=$id?>").datetimepicker({ duration: 200, showAnim: "slideDown", ampm: true, hourGrid: 6, minuteGrid: 10 });
	<? } ?>
</script>
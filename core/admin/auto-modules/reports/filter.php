<div class="container">
	<form method="post" action="<?=ADMIN_ROOT.$bigtree["module"]["route"]."/".$bigtree["module_action"]["route"]."/".$bigtree["report"]["type"]?>/">
		<?php
			if ($admin->Level > 1) {
		?>
		<div class="developer_buttons">
			<a href="<?=ADMIN_ROOT?>developer/modules/reports/edit/<?=$bigtree["report"]["id"]?>/?return=front" title="Edit Report in Developer">
				Edit Report in Developer
				<span class="icon_small icon_small_edit_yellow"></span>
			</a>
		</div>
		<?php
			}
		?>
		<section>
			<?php
				foreach ($bigtree["report"]["filters"] as $id => $filter) {
			?>
			<fieldset>
				<label><?=$filter["title"]?></label>
				<?php include BigTree::path("admin/auto-modules/reports/filters/".$filter["type"].".php"); ?>	
			</fieldset>
			<?php
				}
			?>
			<div class="sub_section last">
				<fieldset class="float_margin">
					<label>Sort By</label>
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
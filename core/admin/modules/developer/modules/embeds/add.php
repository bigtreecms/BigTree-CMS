<?
	$module = $admin->getModule($_GET["module"]);
?>
<div class="container">
	<form method="post" action="<?=$section_root?>create/<?=$module["id"]?>/" class="module">
		<section>
			<div class="left last">
				<fieldset>
					<label class="required">Title <small>(for reference only, not shown in the embed)</small></label>
					<input type="text" class="required" name="title" />
				</fieldset>

				<fieldset>
					<label class="required">Data Table</label>
					<select name="table" id="form_table" class="required">
						<option></option>
						<? BigTree::getTableSelectOptions($table); ?>
					</select>
				</fieldset>

				<fieldset>
					<input type="checkbox" name="default_pending" />
					<label class="for_checkbox">Default Submissions to Pending</label>
				</fieldset>
			</div>

			<div class="right last">
				<fieldset>
					<label>Preprocessing Function <small>(passes in post data, returns keyed array of adds/edits)</small></label>
					<input type="text" name="preprocess" />
				</fieldset>

				<fieldset>
					<label>Function Callback <small>(passes in ID and parsed post data, and publish state)</small></label>
					<input type="text" name="callback" />
				</fieldset>

				<fieldset>
					<label>Custom CSS File <small>(full URL)</small></label>
					<input type="text" name="css" />
				</fieldset>
			</div>
		</section>
		<section class="sub" id="field_area">
			<p>Please choose a table to populate this area.</p>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Create" />
		</footer>
	</form>
</div>

<? include BigTree::path("admin/modules/developer/modules/forms/_js.php") ?>
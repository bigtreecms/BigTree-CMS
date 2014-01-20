<div class="container">
	<form method="post" action="<?=DEVELOPER_ROOT?>callouts/groups/create/" class="module">
		<section>
			<fieldset>
				<label class="required">Name</label>
				<input type="text" name="name" value="" class="required" />
			</fieldset>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Create" />
		</footer>
	</form>
</div>
<script>
	new BigTreeFormValidator("form.module");
</script>
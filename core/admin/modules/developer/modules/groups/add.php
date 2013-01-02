<div class="container">
	<form method="post" action="<?=$developer_root?>modules/groups/create/" class="module">
		<header><h2>Group Details</h2></header>
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
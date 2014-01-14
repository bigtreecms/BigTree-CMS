<div class="container">
	<section>
		<p><img src="<?=ADMIN_ROOT?>images/spinner.gif" alt="" /> &nbsp; Please wait while we retrieve your Google Analytics information.</p>
	</section>
</div>
<script>
	$.ajax("<?=ADMIN_ROOT?>ajax/dashboard/analytics/cache/", { success: function(response) {
		if (response) {
			document.location.href = "<?=MODULE_ROOT?>";
		} else {
			BigTree.Growl("Analytics","Caching Failed",5000,"error");
			$(".container section p").html('Caching failed. Please return to the configuration screen by <a href="../configure/">clicking here</a>.');
		}
	}});
</script>
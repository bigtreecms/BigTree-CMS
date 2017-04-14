<?php
	namespace BigTree;
?>
<div class="container">
	<section>
		<p><img src="<?=ADMIN_ROOT?>images/spinner.gif" alt="" /> &nbsp; <?=Text::translate("Please wait while we retrieve your Google Analytics information.")?></p>
	</section>
</div>
<script>
	$.secureAjax("<?=ADMIN_ROOT?>ajax/dashboard/analytics/cache/", { success: function(response) {
		if (response) {
			document.location.href = "<?=MODULE_ROOT?>";
		} else {
			BigTree.growl("Analytics","<?=Text::translate("Caching Failed")?>",5000,"error");
			$(".container section p").html('<?=Text::translate("Caching failed. Please return to the configuration screen by <a href=\"../configure/\">clicking here</a>.")?>');
		}
	}});
</script>
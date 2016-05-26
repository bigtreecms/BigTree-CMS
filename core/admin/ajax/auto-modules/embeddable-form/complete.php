<?php
	if ($form->RedirectURL) {
?>
<script>window.parent.BigTreeEmbeddableForm<?=$form->ID?>.redirect("<?=$form->RedirectURL?>");</script>
<?php
	} else {
		echo $form->ThankYouMessage;
	}
?>
<script>window.parent.BigTreeEmbeddableForm<?=$form->ID?>.scrollToTop();</script>
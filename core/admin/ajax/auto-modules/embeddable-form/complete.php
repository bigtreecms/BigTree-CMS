<?php
	if ($bigtree["form"]["redirect_url"]) {
?>
<script>window.parent.BigTreeEmbeddableForm<?=str_replace("embeddable-forms-", "", $bigtree["form"]["id"])?>.redirect("<?=$bigtree["form"]["redirect_url"]?>");</script>
<?php
	} else {
		echo $bigtree["form"]["thank_you_message"];
	}
?>
<script>window.parent.BigTreeEmbeddableForm<?=str_replace("embeddable-forms-", "", $bigtree["form"]["id"])?>.scrollToTop();</script>
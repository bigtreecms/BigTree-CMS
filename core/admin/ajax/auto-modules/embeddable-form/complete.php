<?
	if ($bigtree["form"]["redirect_url"]) {
?>
<script>window.parent.BigTreeEmbeddableForm<?=$bigtree["form"]["id"]?>.redirect("<?=$bigtree["form"]["redirect_url"]?>");</script>
<?
	} else {
		echo $bigtree["form"]["thank_you_message"];
	}
?>
<script>window.parent.BigTreeEmbeddableForm<?=$bigtree["form"]["id"]?>.scrollToTop();</script>
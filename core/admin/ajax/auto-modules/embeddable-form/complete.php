<?
	if ($bigtree["form"]["redirect_url"]) {
?>
<script>window.parent.BigTreeFormRedirect("<?=$bigtree["form"]["redirect_url"]?>");</script>
<?
	} else {
		echo $bigtree["form"]["thank_you_message"];
	}
?>
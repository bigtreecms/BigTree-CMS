<?
	$service = new BigTreeUploadService;
	
	if ($service->Service == "s3") {
		$currently = "Amazon S3";
	} elseif ($service->Service == "rackspace") {
		$currently = "Rackspace Cloud Files";
	} else {
		$currently = "Local Storage";
	}
?>
<div class="table">
	<summary><h2>Currently Using<small><?=$currently?></small></h2></summary>
	<section>
		<a class="box_select<? if ($currently == "Local Storage") { ?> active<? } ?>" href="local/">
			<span class="local_storage"></span>
			<p>Local Storage</p>
		</a>
		<a class="box_select<? if ($currently == "Amazon S3") { ?> active<? } ?>" href="amazon/">
			<span class="amazon"></span>
			<p>Amazon S3</p>
		</a>
		<a class="box_select<? if ($currently == "Rackspace Cloud Files") { ?> active<? } ?>" href="rackspace/">
			<span class="rackspace"></span>
			<p>Rackspace Cloud Files</p>
		</a>
	</section>
</div>
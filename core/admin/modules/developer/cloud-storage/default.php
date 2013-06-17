<?
	$storage = new BigTreeStorage;
?>
<div class="table">
	<section>
		<a class="box_select<? if ($storage->Service == "local") { ?> connected<? } ?>" href="local/">
			<span class="local_storage"></span>
			<p>Local Storage</p>
		</a>
		<a class="box_select<? if ($storage->Service == "s3") { ?> connected<? } ?>" href="amazon/">
			<span class="amazon"></span>
			<p>Amazon S3</p>
		</a>
		<a class="box_select<? if ($storage->Service == "rackspace") { ?> connected<? } ?>" href="rackspace/">
			<span class="rackspace"></span>
			<p>Rackspace Cloud Files</p>
		</a>
	</section>
</div>
<?php
	$crops = $cms->cacheGet("org.bigtreecms.crops", $_POST["crop_key"]);
	$count = count($crops);
?>
<div class="container">
	<header>
		<h2 class="cropper"><span>Processing Crops</span> <span class="count current">1</span> <span>of</span> <span class="count total"><?=$count?></span></h2>
	</header>
	
	<section>
		<p>Please wait while all of your image crops are completed.</p>
	</section>
</div>

<script>
	(function() {
		var Counter = $(".container .current");
		var Current = 1;
		var POST = <?=json_encode($_POST)?>;
		var Total = <?=$count?>;

		function process() {
			var index = Current - 1;

			Counter.html(Current);

			$.secureAjax("<?=ADMIN_ROOT?>ajax/auto-modules/process-crop/", {
				method: "POST",
				data: {
					crop_key: "<?=$_POST["crop_key"]?>",
					index: index,
					x: POST.x[index],
					y: POST.y[index],
					width: POST.width[index],
					height: POST.height[index],
				}
			}).done(function() {
				Current++;

				if (Current > Total) {
					window.onbeforeunload = null;
					document.location.href = "<?=$bigtree["form_root"]?>finish-crops/";
				} else {
					process();
				}
			});
		}

		window.onbeforeunload = function(ev) {
			BigTree.growl("Cropping Images", "Please wait until all your images are finished cropping before leaving this page.", 5000, "error");
			return false;
		};

		process();
	})();
</script>
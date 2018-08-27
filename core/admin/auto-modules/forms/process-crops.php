<?php
	if (!$_SESSION["bigtree_admin"]["form_data"]) {
		BigTree::redirect($_SESSION["bigtree_admin"]["cropper_previous_page"]);
	}
	
	if ($_POST["front_end_editor"]) {
		$bigtree["layout"] = "front-end";
	}
	
	$crops = $cms->cacheGet("org.bigtreecms.crops", $_POST["crop_key"]);
	$count = count($crops);
	
	$return_link = $_POST["return_page"] ?: $bigtree["form_root"]."finish-crops/";
?>
<div class="container">
	<header>
		<h2 class="cropper"><span>Processing Crops</span> <span class="count current">1</span> <span>of</span> <span class="count total"><?=$count?></span><div class="button_loader"></div></h2>
	</header>
	
	<section>
		<p>Please wait while all of your image crops are completed.</p>
	</section>
</div>

<script>
	(function() {
		var AllowedThreads = 3;
		var Counter = $(".container .current");
		var Completed = 0;
		var POST = <?=json_encode($_POST)?>;
		var Requested = 0;
		var Total = <?=$count?>;

		for (var index = 0; index < AllowedThreads; index++) {
			if (index < Total) {
				makeCropRequest(index);
			}
		}

		function makeCropRequest() {
			$.secureAjax("<?=ADMIN_ROOT?>ajax/auto-modules/process-crop/", {
				method: "POST",
				data: {
					crop_key: "<?=$_POST["crop_key"]?>",
					index: Requested,
					x: POST.x[Requested],
					y: POST.y[Requested],
					width: POST.width[Requested],
					height: POST.height[Requested],
				}
			}).done(function() {
				Completed++;
				Counter.html(Completed);

				if (Completed == Total) {
					window.onbeforeunload = null;
					document.location.href = "<?=$return_link?>";
				} else if (Requested < Total) {
					makeCropRequest(Requested - 1);
				}
			});

			Requested++;
		}

		window.onbeforeunload = function(ev) {
			BigTree.growl("Cropping Images", "Please wait until all your images are finished cropping before leaving this page.", 5000, "error");
			return false;
		};
	})();
</script>
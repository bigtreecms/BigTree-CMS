		<script>
			(function() {
				var Height;

				function embedResize() {
					var height = $("body").height();
					if (height != Height) {
						try {
							Height = window.parent.BigTreeEmbeddableForm<?=$form->ID?>.resize(height);
						} catch (e) {}
					}
				}

				$(document).ready(function() {
					setInterval(embedResize,250);
				});
			})();
		</script>
	</body>
</html>
		<script>
			$(document).ready(function() {
				setInterval('try { window.parent.BigTreeEmbeddableForm<?=$bigtree["form"]["id"]?>.resize($("body").height()); } catch (e) {}',250);
			});
		</script>
	</body>
</html>
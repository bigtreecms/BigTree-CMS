			</div>
		</div><!--End Wrapper-->
		<footer class="main">
			<section>
				<article class="bigtree">
					<a href="http://www.bigtreecms.com/" target="_blank" class="logo"></a>				
				</article>
				<article class="fastspot">
					<p>
						Version <?=BIGTREE_VERSION?>&nbsp;&nbsp;&middot;&nbsp;&nbsp;&copy; <?=date("Y")?> Fastspot
					</p>
					<a href="<?=ADMIN_ROOT?>credits/">Credits &amp; Licenses</a>&nbsp;&nbsp;&middot;&nbsp;&nbsp;
					<a href="http://www.bigtreecms.org/" target="_blank">Support</a>&nbsp;&nbsp;&middot;&nbsp;&nbsp;
					<a href="http://www.fastspot.com/agency/contact/" target="_blank">Contact Us</a>
				</article>
			</section>
		</footer>
		<?
			if (isset($_SESSION["bigtree_admin"]["growl"])) {
		?>
		<script>BigTree.Growl("<?=BigTree::safeEncode($_SESSION["bigtree_admin"]["growl"]["title"])?>","<?=BigTree::safeEncode($_SESSION["bigtree_admin"]["growl"]["message"])?>",5000,"<?=htmlspecialchars($_SESSION["bigtree_admin"]["growl"]["type"])?>");</script>
		<?
				unset($_SESSION["bigtree_admin"]["growl"]);
			}
		?>
 	</body>
</html>
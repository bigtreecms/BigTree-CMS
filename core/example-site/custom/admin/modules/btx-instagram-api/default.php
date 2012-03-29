<? 
	include "_heading.php";
	include BigTree::path("admin/auto-modules/_nav.php"); 
?>
<div class="form_container" id="instagram_api">
	<section>
			<? if (!$btxInstagramAPI->client_id) { ?>
			<h2>
				You still need to set a Client ID. <a href="<?=$mroot?>client-id/" class="button" style="float: right; margin-top: -6px;">Set Client ID</a> 
			</h2>
			<? } else { ?>
			<h2>
				Client ID: <strong><?=$btxInstagramAPI->client_id?></strong> 
				
				<a href="<?=$mroot?>clear-client-id/" class="button" style="float: right; margin-top: -6px;">Clear Client ID</a>
				<a href="<?=$mroot?>client-id/" class="button" style="float: right; margin-top: -6px; margin-right: 5px;">Set Client ID</a>
			</h2>
			<hr />
			<h2>
				Module Usage
			</h2>
			<br class="clear" />
			<p>The Instagram API module provides a simple way to interact with the <a href="http://instagram.com/developer/" target="_blank">Instagram API</a>.</p>
			<p>Create a new instance of the Instagram Class:</p>
			<pre>$btxInstagramAPI = new BTXInstagramAPI;</pre>
			
			<p>Search for something by tag:</p>
			<pre>$btxInstagramAPI->search("tag");</pre>
			<? } ?>
	</section>
</div>
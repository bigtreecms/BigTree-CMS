<? 
	include "_heading.php";
	include BigTree::path("admin/auto-modules/_nav.php"); 
?>
<div class="form_container" id="youtube_api">
	<section>
		<h2>Module Usage</h2>
		<p>The YouTube API module provides a simple way to interact with the <a href="https://developers.google.com/youtube/2.0/reference" target="_blank">YouTube Data API</a>.</p>
		<p>Create a new instance of the YouTube Class:</p>
		<pre>$btxYouTubeAPI = new BTXYouTubeAPI;</pre>
		
		<p>Search for videos:</p>
		<pre>$btxYouTubeAPI->search("Search Query");</pre>
	</section>
</div>
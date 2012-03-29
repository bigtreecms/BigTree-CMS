<?
	if ($btxTwitterAPI->active_username) {
		$user = $btxTwitterAPI->user();
	}
		
	include "_heading.php";
	include BigTree::path("admin/auto-modules/_nav.php"); 
?>
<div class="form_container" id="twitter_api">
	<section>
		<? if (!$btxTwitterAPI->active_username) { ?>
		<h2>
		    You still need to set a active username. <a href="<?=$mroot?>active-username/" class="button" style="float: right; margin-top: -6px;">Set Username</a> 
		</h2>
		<? } else { ?>
		<h2>
		    Active username: <strong><?=$btxTwitterAPI->active_username?></strong> 
		    <a href="<?=$mroot?>clear-username/" class="button" style="float: right; margin-top: -6px;">Clear Username</a>
		    <a href="<?=$mroot?>active-username/" class="button" style="float: right; margin-top: -6px; margin-right: 5px;">Set Username</a>
		</h2>
		<img src="<?=$user["profile_image_url"]?>" alt="<?=$user["screen_name"]?>" style="float: left; display: block; margin: 0 10px 10px 0;" />
		<p style="overflow: hidden;">
		    Tweets: <strong><?=$user["statuses_count"]?></strong>
		    <br />
		    Following: <strong><?=$user["friends_count"]?></strong>
		    <br />
		    Followers: <strong><?=$user["followers_count"]?></strong>
		</p>
		<hr />
		<h2>
		    Module Usage
		</h2>
		<p>The Twitter API module provides a simple way to fetch and parse json formatted Twitter feeds. </p>
		<p>Create a new instance of the Twitter Class:</p>
		<pre>$btxTwitterAPI = new BTXTwitterAPI;</pre>
		
		<p>Fetch a user; defaults to your active username:</p>
		<pre>$btxTwitterAPI->user("username");</pre>
		
		<p>Fetch a parsed timeline; defaults to your active username:</p>
		<pre>$btxTwitterAPI->timeline("username");</pre>
		
		<p>Search for a phrase; returns parsed results:</p>
		<pre>$btxTwitterAPI->search("Search Query");</pre>
		
		<p>Parse a timeline (if you've manually fetched the json yourself):</p>
		<pre>$json = file_get_contents("https://api.twitter.com/...");
$btxTwitterAPI->parseTimeline($json);</pre>
		
		<p>Returned timelines include a parsed version of the tweet, as well as the original:</p>
		<pre>[tweet] => Array (
	[id] => "*Tweet ID*",
	[text] => "*Tweet Content*",
	[created] => "*Relative Time*",
	[source] => "*Linked Source*",
	[original] => Array (
		*Everything Twitter Includes*
	),
)</pre>
		<? } ?>
	</section>
</div>
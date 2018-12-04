<?php

/*

	Facebook App Access Token API
	https://developers.facebook.com/docs/facebook-login/access-tokens/

	View App Tokens: https://developers.facebook.com/tools/accesstoken/

*/

/*

	Class: BigTreeFacebookAppAPI
		Main accessor class. Example:
			$FbAppApi = new BigTreeFacebookAppAPI($bigtree);
			$album = $FbAppApi->getAlbum($album_id);
			echo $album->Name;

	Parameters:
		$cfg["config"]["facebook-app"]["cache"]     - (true) Cache API responses.
		$cfg["config"]["facebook-app"]["cache-id"]  - (org.bigtreecms.api.facebook-api) BigTree cache id
		$cfg["config"]["facebook-app"]["cache-ttl"] - (604800 (week)) Seconds to cache response.
		$cfg["config"]["facebook-app"]["app_id"]    - Facebook App access token application id
		$cfg["config"]["facebook-app"]["secret"]    - Facebook App access token secret

*/

class BigTreeFacebookAppAPI implements FbAppApi
{

	const ENDPOINT = "https://graph.facebook.com/v3.2/";

	/*

		Constructor:
			$cfg - Sets up the configuration.

	*/

	function __construct($cfg)
	{
		! empty($cfg["config"]["facebook-app"]["cache"]) || $cfg["config"]["facebook-app"]["cache"] = true;
		! empty($cfg["config"]["facebook-app"]["cache-id"]) || $cfg["config"]["facebook-app"]["cache-id"] = "org.bigtreecms.api.facebook-api";
		! empty($cfg["config"]["facebook-app"]["cache-ttl"]) || $cfg["config"]["facebook-app"]["cache-ttl"] = 604800; // week
		! empty($cfg["config"]["facebook-app"]["app_id"]) || $cfg["config"]["facebook-app"]["app_id"] = "config.facebook-app.app_id missing";
		! empty($cfg["config"]["facebook-app"]["secret"]) || $cfg["config"]["facebook-app"]["secret"] = "config.facebook-app.secret missing";

		if ($cfg["config"]["facebook-app"]["cache"]) {
			$this->service = new BigTreeFacebookAppAPICachable($this, $cfg);
		} else {
			$this->service = new BigTreeFacebookAppAPIImpl($this, $cfg);
		}
	}

	/*

		Function: getAlbum

		Parameters:
			$album_id - Facebook album id.

		Returns:
			BigTreeFacebookAppAPIAlbum or false.

	*/

	public function getAlbum($album_id)
	{
		return $this->service->getAlbum($album_id);
	}

	/*

		Function: getAlbumPhotos

		Parameters:
			$album_id - Facebook album id.

		Returns:
			List of BigTreeFacebookAppAPIPhoto or false

	*/

	public function getAlbumPhotos($album_id)
	{
		return $this->service->getAlbumPhotos($album_id);
	}

	/*

		Function: getPhoto
			Get extra information about a photo.

		Parameters:
			$photo_id - Facebook photo id.

		Returns:
			BigTreeFacebookAppAPIPhoto or false.

	*/

	public function getPhoto($photo_id)
	{
		return $this->service->getPhoto($photo_id);
	}

	/*

		Function: getAlbumPhotosFromList
			Convert Facebook photo ids to BigTreeFacebookAppAPIPhotos.

		Parameters:
			$photo_id - List of Facebook photo ids.

		Returns:
			List of BigTreeFacebookAppAPIPhoto or false.

	*/

	public function getAlbumPhotosFromList($photo_ids)
	{
		return $this->service->getAlbumPhotosFromList($photo_ids);
	}
}

/*

	Interface: FbAppApi
		Used to provide a common interface for the caching subsystem.

*/
interface FbAppApi
{

	function getAlbum($album_id);

	function getAlbumPhotos($album_id);

	function getPhoto($photo_id);

	function getAlbumPhotosFromList($photo_ids);
}

/*

	Class: FbAppApi
		Concrete class that provides caching retrieval of Facebook requests.

*/
class BigTreeFacebookAppAPICachable implements FbAppApi
{

	/*

		Constructor:
			Sets up the caching options.

		Parameters:
			$api - Implementation of API to decorate.
			$cfg - BigTree configuration.

	*/

	function __construct($api, $cfg)
	{
		global $cms;
		$this->service = new BigTreeFacebookAppAPIImpl($api, $cfg);
		$this->cms = $cms;
		$this->cacheId = $cfg["config"]["facebook-app"]["cache-id"];
		$this->cacheTimeout = $cfg["config"]["facebook-app"]["cache-ttl"];
	}

	/*

		See: BigTreeFacebookAppAPI->getAlbumPhotos

	*/

	public function getAlbumPhotos($album_id)
	{
		if (! $album_id) {
			return false;
		}
		$cacheKey = "ap:" . $album_id;
		$stuff = $this->cms->cacheGet($this->cacheId, $cacheKey, $this->cacheTimeout);
		if ($stuff) {
			$stuff = unserialize($stuff);
			return $stuff;
		}
		$obj = $this->service->getAlbumPhotos($album_id);
		$stuff = serialize($obj);
		$this->cms->cachePut($this->cacheId, $cacheKey, $stuff);
		return $obj;
	}

	/*

		See: BigTreeFacebookAppAPI->getPhoto

	*/

	public function getPhoto($photo_id)
	{
		if (! $photo_id) {
			return false;
		}
		$cacheKey = "p:" . $photo_id;
		$stuff = $this->cms->cacheGet($this->cacheId, $cacheKey, $this->cacheTimeout);
		if ($stuff) {
			$stuff = unserialize($stuff);
			return $stuff;
		}
		$obj = $this->service->getPhoto($photo_id);
		$stuff = serialize($obj);
		$this->cms->cachePut($this->cacheId, $cacheKey, $stuff);
		return $obj;
	}

	/*

		See: BigTreeFacebookAppAPI->getAlbum

	*/

	public function getAlbum($album_id)
	{
		if (! $album_id) {
			return false;
		}
		$cacheKey = "a:" . $album_id;
		$stuff = $this->cms->cacheGet($this->cacheId, $cacheKey, $this->cacheTimeout);
		if ($stuff) {
			$stuff = unserialize($stuff);
			return $stuff;
		}
		$obj = $this->service->getAlbum($album_id);
		$stuff = serialize($obj);
		$this->cms->cachePut($this->cacheId, $cacheKey, $stuff);
		return $obj;
	}

	/*

		See: BigTreeFacebookAppAPI->getAlbumPhotosFromList

	*/

	public function getAlbumPhotosFromList($photo_ids)
	{
		if (count($photo_ids) == 0) {
			return [];
		}

		$cacheKey = "apl:" . md5(json_encode($photo_ids));
		$stuff = $this->cms->cacheGet($this->cacheId, $cacheKey, $this->cacheTimeout);
		if ($stuff) {
			$stuff = unserialize($stuff);
			return $stuff;
		}
		$obj = $this->service->getAlbumPhotosFromList($photo_ids);
		$stuff = serialize($obj);
		$this->cms->cachePut($this->cacheId, $cacheKey, $stuff);
		return $obj;
	}
}

/*

	Class: BigTreeFacebookAppAPIImpl
		Base implementation

*/

class BigTreeFacebookAppAPIImpl implements FbAppApi
{

	/*
		Fields associated with  Album and Photo nodes.
	 */

	const ALBUM_FIELDS = "id,name,description,link,cover_photo,count,place,type,created_time,photos";

	const PHOTO_FIELDS = "id,source,created_time,images";

	/*

		Constructor:
			Sets up transport abstraction.

		Parameters:
			$api - Implementation to decorate.
			$cfg - BitTree configuration.

	*/

	function __construct($api, $cfg)
	{
		$this->api = $api;
		$this->service = new BigTreeFacebookAppAPITransport($cfg["config"]["facebook-app"]["app_id"], $cfg["config"]["facebook-app"]["secret"]);
	}

	/*

		See: BigTreeFacebookAppAPI->getAlbum

	*/

	function getAlbum($album_id)
	{
		$response = $this->service->getNode($album_id, [
			"fields" => self::ALBUM_FIELDS
		]);

		if (! $response->id) {
			return false;
		}

		return new BigTreeFacebookAppAPIAlbum($response, $this->api);
	}

	/*

		See: BigTreeFacebookAppAPI->getAlbumPhotos

	*/

	function getAlbumPhotos($album_id)
	{
		$response = $this->service->getEdge($album_id, "photos", [
			"fields" => self::PHOTO_FIELDS
		]);

		if (! $response->id) {
			return false;
		}

		$all = [];
		foreach ($response->data as $photo) {
			$p = $this->api->getPhoto($photo->id);
			$all[] = $p;
		}
		return $all;
	}

	/*

		See: BigTreeFacebookAppAPI->getPhoto

	*/

	public function getPhoto($photo_id)
	{
		$response = $this->service->getNode($photo_id, [
			"fields" => self::PHOTO_FIELDS
		]);

		if (! $response->id) {
			return false;
		}

		return new BigTreeFacebookAppAPIPhoto($response, $this->api);
	}

	/*

		See: BigTreeFacebookAppAPI->getAlbumPhotosFromList

	*/

	public function getAlbumPhotosFromList($photo_ids)
	{
		$all = [];
		foreach ($photo_ids as $id) {
			$p = $this->api->getPhoto($id);
			$all[] = $p;
		}
		return $all;
	}
}

/*

	Class: BigTreeFacebookAppAPITransport
		Provides lowest level transport. This class is used internally.

*/

class BigTreeFacebookAppAPITransport
{

	/*

		Constructor:
			$app_id - Facebook app access token application id.
			$secret - Facebook app access token secret.

	*/

	function __construct($app_id, $secret)
	{
		$this->accessToken = "access_token=" . $app_id . "|" . $secret;
	}

	/*

		Function: getNode
			Get Facebook object.

		Parameters:
			$obj - id of node.
			$parms - (optional) list of extra options.

	*/

	public function getNode($obj, $parms = [])
	{
		return $this->getEdge($obj, "", $parms);
	}

	/*

		Function: getEdge
			Get collection of Facebook of objects.

		Parameters:
			$obj - id of node.
			$edge - Edge.
			$parms - (optional) list of extra options.

	*/

	public function getEdge($obj, $edge, $parms = [])
	{
		$parms_ar = [];
		foreach ($parms as $key => $value) {
			$parms_ar[] = $key . "=" . $value;
		}
		$parms_url = implode("&", $parms_ar);

		$url = BigTreeFacebookAppAPI::ENDPOINT . $obj;
		if ($edge != "") {
			$url .= "/" . $edge;
		}
		$url .= "?" . $this->accessToken;
		if ($parms_url != "") {
			$url .= "&" . $parms_url;
		}
		$response = $this->callGet($url);
		return json_decode($response);
	}

	/*

		Function: callGet
			Performs a GET request.

		Parameters:
			$url - Url of request.
			$headers - array of headers.

		Returns:
			Results of request.
	*/

	private function callGet($url, $headers = [])
	{
		return $this->curl($url, "GET", [], $headers);
	}

	/*

		Function: call
			Performs  cUrl request.

		Parameters:
			$url - Url of request.
			$method - HTTP method.
			$data - (optional) Body object.
			$headers - (optional) Headers array.

		Returns:
			Results of request.

	*/

	private function curl($url, $method, $data = [], $headers = [])
	{
		return BigTree::cURL($url, $data, array(
			CURLOPT_CUSTOMREQUEST => $method,
			CURLOPT_HTTPHEADER => $headers
		));
	}
}

/*

	Class: BigTreeFacebookAppAPIAlbum
		Facebook album model.
*/

class BigTreeFacebookAppAPIAlbum
{

	protected $API;

	/*

		Constructor:
			$album - Response object from Facebook.
			$api - FbAppApi implementation.

	*/

	function __construct($album, &$api)
	{
		$this->API = $api;

		$this->CoverPhoto = $this->API->getPhoto($album->cover_photo->id);
		$this->CreatedTime = $album->created_time;
		$this->Description = $album->description;
		$this->ID = $album->id;
		$this->Link = $album->link;
		$this->Name = $album->name;
		// $this->PhotoCount = $album->count;
		// $this->Place = new BigTreeFacebookLocation($album->place, $api);
		$this->Type = $album->type;
		$this->PhotoIds = [];
		foreach ($album->photos->data as $p) {
			$this->PhotoIds[] = $p->id;
		}
	}

	/*

		Function: getPhotos

		Returns:
			List of BigTreeFacebookAppAPIPhoto or false.

	*/

	function getPhotos()
	{
		return $this->API->getAlbumPhotosFromList($this->PhotoIds);
	}

	/*

		Function: getCoverPhoto

		Returns:
			BigTreeFacebookAppAPIPhoto or false.

	*/

	function getCoverPhoto()
	{
		return $this->CoverPhoto;
	}
}

/*

	Class: BigTreeFacebookAppAPIPhoto
		Facebook photo model.

*/

class BigTreeFacebookAppAPIPhoto
{

	protected $API;

	/*

		Constructor:
			$photo - Response object from Facebook.
			$api - FbAppApi implementation.

	*/

	function __construct($photo, &$api)
	{
		$this->API = $api;

		$this->CreatedTime = $photo->created_time;
		$this->ID = $photo->id;
		$this->Images = array();
		$this->Images["default"] = $photo->source;

		foreach ($photo->images as $image) {
			$this->Images[$image->width . "x" . $image->height] = $image->source;
		}
	}

	/*

		Function: preferredSize

		Parameters:
			$dimensions:
				"" returns default.
				"WxH" - one of getDimensions().

	*/

	function preferredSize($dimensions = "")
	{
		if (isset($this->Images[$dimensions])) {
			return $this->Images[$dimensions];
		}

		return $this->Images["default"];
	}

	/*

		Function: getDimensions
			Images dimensions in this set.

	*/

	function getDimensions()
	{
		return array_keys($this->Images);
	}

}

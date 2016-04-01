<?php
	/*
		Class: BigTree\YouTube\Playlist
			A YouTube object that contains information about and methods you can perform on a playlist.
	*/

	namespace BigTree\YouTube;

	use stdClass;

	class Playlist {

		/** @var \BigTree\YouTube\API */
		protected $API;

		function __construct($playlist,&$api) {
			$this->API = $api;
			isset($playlist->snippet->channelId) ? $this->ChannelID = $playlist->snippet->channelId : false;
			isset($playlist->snippet->channelTitle) ? $this->ChannelTitle = $playlist->snippet->channelTitle : false;
			isset($playlist->snippet->description) ? $this->Description = $playlist->snippet->description : false;
			$this->ID = $playlist->id;
			if (isset($playlist->snippet->thumbnails)) {
				$this->Images = new stdClass;
				foreach ($playlist->snippet->thumbnails as $key => $val) {
					$key = ucwords($key);
					$this->Images->$key = $val->url;
				}
			}
			isset($playlist->status->privacyStatus) ? $this->Privacy = $playlist->status->privacyStatus : false;
			isset($playlist->snippet->tags) ? $this->Tags = $playlist->snippet->tags : false;
			isset($playlist->snippet->publishedAt) ? $this->Timestamp = date("Y-m-d H:i:s",strtotime($playlist->snippet->publishedAt)) : false;
			isset($playlist->snippet->title) ? $this->Title = $playlist->snippet->title : false;
		}

		/*
			Function: save
				Saves the changes made to this playlist (Title, Description, Privacy, Tags)
				Playlist must be owned by the authenticated user.

			Returns:
				true if successful.
		*/

		function save() {
			return $this->API->updatePlaylist($this->ID,$this->Title,$this->Description,$this->Privacy,$this->Tags);
		}

		/*
			Function: delete
				Deletes the playlist.
				Playlist must be owned by the authenticated user.
		*/

		function delete() {
			return $this->API->deletePlaylist($this->ID);
		}

	}

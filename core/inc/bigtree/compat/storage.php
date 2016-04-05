<?php
	// Backwards compatibility class.
	class BigTreeStorage extends BigTree\Storage {}

	// Backwards compatibility class.
	class BigTreeUploadService extends BigTreeStorage {

		function upload($local_file,$file_name,$relative_path,$remove_original = true) {
			return $this->store($local_file,$file_name,$relative_path,$remove_original);
		}

	}
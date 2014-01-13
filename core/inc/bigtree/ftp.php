<?
	/*
		Class: BigTreeFTP
			An FTP class heavily based on PemFTP by Alexey Dotsenko.
			http://www.phpclasses.org/browse/package/1743.html
	*/

	class BigTreeFTP {
		var $LocalEcho,$Verbose,$OS_local,$OS_remote,$_lastaction,$_errors,$_type,$_umask,$_timeout,$_passive,$_host,$_fullhost,$_port,$_datahost,$_dataport,$_ftp_control_sock,$_ftp_data_sock,$_ftp_temp_sock,$_ftp_buff_size,$_login,$_password,$_connected,$_ready,$_code,$_message,$_can_restore,$_port_available,$_curtype,$_features,$_error_array,$AuthorizedTransferMode,$OS_FullName,$_eol_code,$AutoAsciiExt;

		function __construct() {
			$this->_lastaction = null;
			$this->_error_array = array();
			$this->_eol_code = array("u" => "\n", "m" => "\r", "w" => "\r\n");
			$this->AuthorizedTransferMode = array(-1, 0, 1);
			$this->OS_FullName = array("u" => 'UNIX', "w" => 'WINDOWS', "m" => 'MACOS');
			$this->AutoAsciiExt = array("ASP","BAT","C","CPP","CSS","CSV","JS","H","HTM","HTML","SHTML","INI","LOG","PHP3","PHTML","PL","PERL","SH","SQL","TXT");
			$this->_connected = false;
			$this->_ready = false;
			$this->_can_restore = false;
			$this->_code = 0;
			$this->_message = "";
			$this->_ftp_buff_size = 4096;
			$this->_curtype = null;
			$this->_type = -1;
			$this->_timeout = 30;
			$this->_login = "anonymous";
			$this->_password= " anon@ftp.com";
			$this->_features = array();
			$this->OS_local = "u";
			$this->OS_remote = "u";
			$this->features = array();
			if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
				$this->OS_local = "w";
			} elseif (strtoupper(substr(PHP_OS, 0, 3)) === 'MAC') {
				$this->OS_local = "m";
			}
		}

		/*
			Function: changeToParentDirectory
				Changes the current working directory to its parent.

			Returns:
				true if successful
		*/

		function changeToParentDirectory() {
			if (!$this->_exec("CDUP") || !$this->_checkCode()) {
				return false;
			}
			return true;
		}

		/*
			Function: changeDirectory
				Changes the current working directory to given path.
			
			Parameters:
				path - Full directory path to change to.
			Returns:
				true if successful
		*/

		function changeDirectory($path) {
			if (!$this->_exec("CWD $path") || !$this->_checkCode()) {
				return false;
			}
			return true;
		}

		/*
			Function: connect
				Connects to a server.

			Parameters:
				host - The hostname of the server
				port - The port to connect to (defaults to 21)

			Returns:
				true if successful
		*/

		function connect($host,$port = 21) {
			// Setup server parameters
			if (!is_long($port)) {
		 		return false;
			} else {
				$ip = @gethostbyname($host);
		 		$dns = @gethostbyaddr($host);
		 		if (!$ip) {
		 			$ip = $host;
		 		}
		 		if (!$dns) {
		 			$dns = $host;
		 		}
				if (ip2long($ip) === -1) {
					return false;
				}
		 		$this->_host = $ip;
		 		$this->_fullhost = $dns;
		 		$this->_port = $port;
		 		$this->_dataport = $port - 1;
			}
			// Already connected
			if ($this->_ready) {
				return true;
			}
			// Attempt to connect
			if (!($this->_ftp_control_sock = $this->_connect($this->_host, $this->_port))) {
				return false;
			}
			// Wait for a welcome message
			do {
				if (!$this->_readmsg() || !$this->_checkCode()) {
					return false;
				}
				$this->_lastaction = time();
			} while ($this->_code < 200);

			// Get remote system type
			$syst = $this->getSystemType();
			if ($syst) {
				if (preg_match("/win|dos|novell/i", $syst[0])) {
					$this->OS_remote = "w";
				} elseif (preg_match("/os/i", $syst[0])) {
					$this->OS_remote="m";
				} elseif (preg_match("/(li|u)nix/i", $syst[0])) {
					$this->OS_remote="u";
				} else {
					$this->OS_remote="m";
				}
			}

			$this->_ready = true;
			return true;
		}

		/*
			Function: createDirectory
				Creates a directory.

			Parameters:
				path - Full directory path to create or a path relative to the current directory.

			Returns:
				true if successful.
		*/

		function createDirectory($path) {
			if (!$this->_exec("MKD $path") || !$this->_checkCode()) {
				return false;
			}
			return true;
		}

		/*
			Function: deleteDirectory
				Deletes a given directory (it must be empty to be deleted).

			Parameters:
				path - The full directory path or relative to the current directory.

			Returns:
				true if successful
		*/

		function deleteDirectory($path) {
			if (!$this->_exec("RMD $path") || !$this->_checkCode()) {
				return false;
			}
			return true;
		}

		/*
			Function: deleteFile
				Deletes a given directory (it must be empty to be deleted).

			Parameters:
				path - The full file path or path relative to the current directory.

			Returns:
				true if successful
		*/

		function deleteFile($path) {
			if (!$this->_exec("DELE ".$path) || !$this->_checkCode()) {
				return false;
			}
			return true;
		}

		/*
			Function: disconnect
				Closes the FTP connection.
		*/

		function disconnect() {
			if ($this->_ready) {
				$this->_exec("QUIT");
			}
			@fclose($this->_ftp_control_sock);
			$this->_connected=false;
			$this->_ready = false;
		}

		/*
			Function: downloadFile
				Downloads a file from the FTP server.

			Parameters:
				remote - The full path to the file to download (or the path relative to the current directory).
				local - The local path to store the downloaded file.

			Returns:
				true if successful.
		*/

		function downloadFile($remote,$local) {
			$fp = @fopen($local, "w");
			if (!$fp) {
				return false;
			}
			$pi = pathinfo($remote);
			if ($this->_type == 0 || ($this->_type == -1 && in_array(strtoupper($pi["extension"]), $this->AutoAsciiExt))) {
				$mode = 0;
			} else {
				$mode = 1;
			}
			if (!$this->_data_prepare($mode)) {
				fclose($fp);
				return false;
			}
			if (!$this->_exec("RETR ".$remote) || !$this->_checkCode()) {
				$this->_data_close();
				fclose($fp);
				return false;
			}
			$out = $this->_data_read($mode, $fp);
			fclose($fp);
			$this->_data_close();
			if(!$this->_readmsg() || !$this->_checkCode()) {
				return false;
			}
			return true;
		}

		/*
			Function: getCurrentDirectory
				Returns the name of the current working directory.

			Returns:
				The current working directory or false if the call failed.
		*/

		function getCurrentDirectory() {
			if (!$this->_exec("PWD") || !$this->_checkCode()) {
				return false;
			}
			return preg_replace('/^[0-9]{3} "(.+)" .+'."\r\n/", "\\1", $this->_message);
		}

		/*
			Function: getDirectoryContents
				Returns parsed directory information.
			
			Parameters:
				path - Optional directory to search, otherwises uses the current directory.

			Returns:
				An array of parsed information.
		*/

		function getDirectoryContents($path = "") {
			$list = $this->_list(" ".$path,"LIST","getRawDirectoryContents");
			if (is_array($list)) {
				foreach ($list as &$line) {
					$line = $this->parseListing($line);
				}
			}
			return $list;
		}

		/*
			Function: getRawDirectoryContents
				Calls a direct LIST command to the FTP server and returns the results.
			
			Parameters:
				path - Optional directory to search, otherwises uses the current directory.

			Returns:
				An array of information from the FTP LIST command.
		*/

		function getRawDirectoryContents($path = "") {
			return $this->_list(" ".$path,"LIST","getRawDirectoryContents");
		}

		/*
			Function: getSystemType
				Returns the system type of the FTP server.

			Returns:
				An array of system information.
		*/

		function getSystemType() {
			if (!$this->_exec("SYST") || !$this->_checkCode()) {
				return false;
			}
			$data = explode(" ", $this->_message);
			return array($data[1], $data[3]);
		}

		/*
			Function: login
				Login to the FTP host.
			
			Parameters:
				user - FTP username (or null to login anonymously).
				pass - FTP password (or null to login anonymously).
			
			Returns:
				true if successful
		*/

		function login($user = null,$pass = null) {
			if (!$user) {
				$this->_login = "anonymous";
				$this->_password = "anon@anon.com";
			} else {
				$this->_login = $user;
				$this->_password = $pass;
			}
			
			if (!$this->_exec("USER ".$this->_login) || !$this->_checkCode()) {
				return false;
			}
			if ($this->_code != 230) {
				if (!$this->_exec((($this->_code == 331) ? "PASS " : "ACCT ").$this->_password) || !$this->_checkCode()) {
					return false;
				}
			}
			return true;
		}

		/*
			Function: parseListing
				Parses a line from the FTP LIST response into an array of information.

			Parameters:
				list - A line from an FTP listing.

			Returns:
				An array of information or false if the line was corrupt.
		*/

		function parseListing($list) {
			if (preg_match("/^([-ld])([rwxst-]+)\s+(\d+)\s+([^\s]+)\s+([^\s]+)\s+(\d+)\s+(\w{3})\s+(\d+)\s+([\:\d]+)\s+(.+)$/i", $list, $ret)) {
				$v = array(
					"type"	=> ($ret[1] == "-" ? "f" : $ret[1]),
					"perms"	=> 0,
					"inode"	=> $ret[3],
					"owner"	=> $ret[4],
					"group"	=> $ret[5],
					"size"	=> $ret[6],
					"date"	=> $ret[7]." ".$ret[8]." ".$ret[9],
					"name"	=> $ret[10]
				);
				$bad = array("(?)");
				if (in_array($v["owner"], $bad)) {
					$v["owner"] = null;
				}
				if (in_array($v["group"], $bad)) {
					$v["group"] = null;
				}
				$v["perms"] += 00400 * (int)($ret[2]{0} == "r");
				$v["perms"] += 00200 * (int)($ret[2]{1} == "w");
				$v["perms"] += 00100 * (int)in_array($ret[2]{2}, array("x","s"));
				$v["perms"] += 00040 * (int)($ret[2]{3} == "r");
				$v["perms"] += 00020 * (int)($ret[2]{4} == "w");
				$v["perms"] += 00010 * (int)in_array($ret[2]{5}, array("x","s"));
				$v["perms"] += 00004 * (int)($ret[2]{6} == "r");
				$v["perms"] += 00002 * (int)($ret[2]{7} == "w");
				$v["perms"] += 00001 * (int)in_array($ret[2]{8}, array("x","t"));
				$v["perms"] += 04000 * (int)in_array($ret[2]{2}, array("S","s"));
				$v["perms"] += 02000 * (int)in_array($ret[2]{5}, array("S","s"));
				$v["perms"] += 01000 * (int)in_array($ret[2]{8}, array("T","t"));
				return $v;
			}
			return false;
		}

		/*
			Function: rename
				Renames a file or directory.

			Parameters:
				from - The current file/directory name (either absolute path or relative to current directory).
				to - The new file/directory name (either absolute path or relative to current directory).

			Returns:
				true if successful
		*/

		function rename($from, $to) {
			if (!$this->_exec("RNFR ".$from) || !$this->_checkCode() || $this->_code != 350) {
				return false;
			}
			if (!$this->_exec("RNTO ".$to) || !$this->_checkCode()) {
				return false;
			}
			return true;
		}

		/*
			Function: setTransferType
				Sets the transfer type.

			Parameters:
				type - AUTOASCII (-1), ASCII (0), BINARY (1)
		*/

		function setTransferType($mode) {
			if (!in_array($mode, $this->AuthorizedTransferMode)) {
				return false;
			}
			$this->_type = $mode;
			return true;
		}

		/*
			Function: uploadFile
				Uploads a file to the FTP server.

			Parameters:
				local - The full path to the file to upload.
				remote - The full path to store the file at (or relative path to the current directory).

			Returns:
				true if successful
		*/

		function uploadFile($local,$remote) {
			if (!@file_exists($local)) {
				return false;
			}
			$fp = @fopen($local, "r");
			if (!$fp) {
				return false;
			}
			$pi = pathinfo($local);
			if ($this->_type == 0 || ($this->_type==-1 && in_array(strtoupper($pi["extension"]), $this->AutoAsciiExt))) {
				$mode = 0;
			} else {
				$mode = 1;
			}
			if (!$this->_data_prepare($mode)) {
				fclose($fp);
				return false;
			}
			if (!$this->_exec("STOR ".$remote) || !$this->_checkCode()) {
				$this->_data_close();
				fclose($fp);
				return false;
			}
			$ret = $this->_data_write($mode, $fp);
			fclose($fp);
			$this->_data_close();
			if (!$this->_readmsg() || !$this->_checkCode()) {
				return false;
			}
			return true;
		}

		/* Private Functions*/

		private function _checkCode() {
			return ($this->_code < 400 && $this->_code > 0);
		}

		private function _connect($host, $port) {
			$sock = @fsockopen($host, $port, $errno, $errstr, $this->_timeout);
			if (!$sock) {
				return false;
			}
			$this->_connected = true;
			return $sock;
		}

		private function _data_close() {
			@fclose($this->_ftp_data_sock);
			return true;
		}

		private function _data_prepare($mode = 0) {
			if (!$this->_settype($mode)) {
				return false;
			}
			if (!$this->_exec("PASV") || !$this->_checkCode()) {
				$this->_data_close();
				return false;
			}
			$ip_port = explode(",", preg_replace("/^.+ \\(?([0-9]{1,3},[0-9]{1,3},[0-9]{1,3},[0-9]{1,3},[0-9]+,[0-9]+)\\)?.*"."\r\n"."$/", "\\1", $this->_message));
			$this->_datahost = $ip_port[0].".".$ip_port[1].".".$ip_port[2].".".$ip_port[3];
			$this->_dataport = (((int)$ip_port[4])<<8) + ((int)$ip_port[5]);
			$this->_ftp_data_sock = @fsockopen($this->_datahost, $this->_dataport, $errno, $errstr, $this->_timeout);
			if (!$this->_ftp_data_sock) {
				$this->_data_close();
				return false;
			} else {
				$this->_ftp_data_sock;
			}
			
			return true;
		}
	
		private function _data_read($mode = 0, $fp = null) {
			if (is_resource($fp)) {
				$out = 0;
			} else {
				$out = "";
			}
			while (!feof($this->_ftp_data_sock)) {
				$block = fread($this->_ftp_data_sock, $this->_ftp_buff_size);
				if ($mode != 1) {
					$block = preg_replace("/\r\n|\r|\n/", $this->_eol_code[$this->OS_local], $block);
				}
				if (is_resource($fp)) {
					$out += fwrite($fp, $block, strlen($block));
				} else {
					$out .= $block;
				}
			}
			return $out;
		}
	
		private function _data_write($mode = 0, $fp = null) {
			if (is_resource($fp)) {
				while (!feof($fp)) {
					$block = fread($fp, $this->_ftp_buff_size);
					if (!$this->_data_write_block($mode, $block)) {
						return false;
					}
				}
			} elseif (!$this->_data_write_block($mode, $fp)) {
				return false;
			}
			return true;
		}
	
		private function _data_write_block($mode, $block) {
			if ($mode != 1) {
				$block = preg_replace("/\r\n|\r|\n/", $this->_eol_code[$this->OS_remote], $block);
			}
			do {
				if (($t = @fwrite($this->_ftp_data_sock, $block)) === false) {
					return false;
				}
				$block = substr($block, $t);
			} while (!empty($block));
			return true;
		}

		private function _exec($cmd) {
			if (!$this->_ready) {
				return false;
			}
			$status = @fputs($this->_ftp_control_sock, $cmd."\r\n");
			if ($status === false) {
				return false;
			}
			$this->_lastaction = time();
			if (!$this->_readmsg()) {
				return false;
			}
			return true;
		}

		private function _list($arg = "", $cmd = "LIST") {
			if (!$this->_data_prepare()) {
				return false;
			}
			if (!$this->_exec($cmd.$arg) || !$this->_checkCode()) {
				$this->_data_close();
				return false;
			}
			$out = "";
			if ($this->_code < 200) {
				$out = $this->_data_read();
				$this->_data_close();
				if (!$this->_readmsg() || !$this->_checkCode()) {
					return false;
				}
				if ($out === "m") {
					return false;
				}
				$out = preg_split("/["."\r\n"."]+/", $out, -1, PREG_SPLIT_NO_EMPTY);
			}
			return $out;
		}

		private function _readmsg() {
			if (!$this->_connected) {
				return false;
			}
			$result = true;
			$this->_message = "";
			$this->_code = 0;
			$go = true;
			do {
				$tmp = @fgets($this->_ftp_control_sock, 512);
				if ($tmp === false) {
					$go = $result = false;
				} else {
					$this->_message .= $tmp;
					if (preg_match("/^([0-9]{3})(-(.*["."\r\n"."]{1,2})+\\1)? [^"."\r\n"."]+["."\r\n"."]{1,2}$/", $this->_message, $regs)) {
						$go = false;
					}
				}
			} while($go);
			$this->_code = (int)$regs[1];
			return $result;
		}

		private function _settype($mode = 0) {
			if ($this->_ready) {
				if ($mode == 1) {
					if ($this->_curtype != 1) {
						if (!$this->_exec("TYPE I")) {
							return "m";
						}
						$this->_curtype = 1;
					}
				} elseif ($this->_curtype != 0) {
					if (!$this->_exec("TYPE A")) {
						return "m";
					}
					$this->_curtype=0;
				}
			} else {
				return "m";
			}
			return true;
		}
	}
?>
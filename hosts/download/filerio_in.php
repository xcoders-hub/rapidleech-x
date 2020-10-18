<?php

if (!defined('RAPIDLEECH')) {
	require_once('index.html');
	exit();
}

if (!file_exists(HOST_DIR . 'download/GenericXFS_DL.php')) html_error('Cannot load "'.htmlentities(HOST_DIR).'download/GenericXFS_DL.php" (File doesn\'t exists)');
require_once(HOST_DIR . 'download/GenericXFS_DL.php');

class filerio_in extends GenericXFS_DL {
	public $pluginVer = 11;
	public function Download($link) {
		$this->wwwDomain = false; // Switch to true if filehost forces it's domain with www.
		$this->cname = 'xfss'; // Session cookie name
		$this->sslLogin = false; // Force https on login.
		$this->embedDL = false; // Try to unpack player's js for finding download link. (Only hosts with video player)
		$this->unescaper = true; // Enable JS unescape decoder.

		$this->Start($link);
	}

	// They have another op name for the my_account option.
	protected function isLoggedIn() {
		$page = $this->GetPage($this->purl.'?op=view_account', $this->cookie, 0, $this->purl);
		if (stripos($page, '/?op=logout') === false && stripos($page, '/logout') === false) return false;
		return $page;
	}
}

// Written by Th3-822.

?>
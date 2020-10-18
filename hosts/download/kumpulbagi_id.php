<?php

if (!defined('RAPIDLEECH')) {
	require_once('index.html');
	exit;
}

class kumpulbagi_id extends DownloadClass {
	private $page, $cookie = array(), $pA, $DLRegexp = '@https?://s\d+\.(?:kumpulbagi\.(?:id|com)|kbagi\.com)/download\?[^\t\r\n\'\"<>]+@i';
	public function Download($link) {
		$link = parse_url($link);
		$link['scheme'] = 'http';
		$link['host'] = 'kbagi.com';
		$link = rebuild_url($link);
		if (!preg_match('@(https?://kbagi\.com)/(?:[^/]+/)+[^\r\n\t/<>\"]+?,(\d+)[^\r\n\t/<>\"]*@i', $link, $fid)) html_error('Invalid link?.');
		$this->link = $GLOBALS['Referer'] = $fid[0];
		$this->baseurl = $fid[1];
		$this->fid = $fid[2];

		$this->pA = (empty($_REQUEST['premium_user']) || empty($_REQUEST['premium_pass']) ? false : true);
		if (($_REQUEST['premium_acc'] == 'on' && ($this->pA || (!empty($GLOBALS['premium_acc']['kumpulbagi_id']['user']) && !empty($GLOBALS['premium_acc']['kumpulbagi_id']['pass']))))) {
			$user = ($this->pA ? $_REQUEST['premium_user'] : $GLOBALS['premium_acc']['kumpulbagi_id']['user']);
			$pass = ($this->pA ? $_REQUEST['premium_pass'] : $GLOBALS['premium_acc']['kumpulbagi_id']['pass']);
			return $this->Login($user, $pass);
		} else return $this->TryDL();
	}

	private function TryDL() {
		$this->page = $this->GetPage($this->link, $this->cookie);
		$this->cookie = GetCookiesArr($this->page, $this->cookie);

		if (!preg_match($this->DLRegexp, $this->page, $DL)) {
			if (substr($this->page, 9, 3) != '200') html_error('File Not Found or Private.');
			$download = $this->reqAction('DownloadFile', array('fileId' => $this->fid, '__RequestVerificationToken' => $this->getCSRFToken($this->page)));
			if (empty($download['DownloadUrl']) || !preg_match($this->DLRegexp, $download['DownloadUrl'], $DL)) {
				if (empty($this->cookie['.ASPXAUTH_v2']) && $download['Type'] == 'Window') html_error('Login is Required to Download This File.');
				html_error('Download-Link Not Found.');
			}
		}
		return $this->RedirectDownload($DL[0], 'kumpulbagi_placeholder_fname');
	}

	private function getCSRFToken($page, $errorPrefix = 'Error') {
		if (!preg_match('@name="__RequestVerificationToken"\s+type="hidden"\s+value="([\w\-+/=]+)"@i', $page, $token)) return html_error("[$errorPrefix]: Request Token Not Found.");
		return $token[1];
	}

	private function Login($user, $pass) {
		$page = $this->GetPage($this->baseurl . '/', $this->cookie);
		$this->cookie = GetCookiesArr($page, $this->cookie);

		$login = $this->reqAction('Account/Login', array('UserName' => $user, 'Password' => $pass, '__RequestVerificationToken' => $this->getCSRFToken($page, 'Login Error')));
		if (!empty($login['Content'])) is_present($login['Content'], 'ID atau kata sandi salah.', 'Login Error: Wrong Login/Password.');
		if (empty($this->cookie['.ASPXAUTH_v2'])) html_error('Login Error: Cookie ".ASPXAUTH_v2" not Found.');

		return $this->TryDL();
	}

	private function reqAction($path, $post = array()) {
		$page = $this->GetPage("{$this->baseurl}/action/$path", $this->cookie, array_map('urlencode', $post), "{$this->baseurl}/\r\nX-Requested-With: XMLHttpRequest");
		$reply = $this->json2array($page, "reqAction($path) Error");
		if (empty($reply)) html_error("[reqAction($path) Error] Empty Response.");
		$this->cookie = GetCookiesArr($page, $this->cookie);

		return $reply;
	}
}

// [20-3-2016] Written by Th3-822.
// [22-4-2016] Renamed to kumpulbagi_id and fixed. - Th3-822
// [12-8-2016] Switched domain to .com tld (I won't rename the plugin again) & Added support for "mirror" domains. - Th3-822
// [12-10-2016] Changed Domain. - Th3-822

?>
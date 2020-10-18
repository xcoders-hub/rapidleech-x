<?php
if (!defined('RAPIDLEECH')) {
	require_once('index.html');
	exit;
}

class uploaded_net extends DownloadClass {
	private $page, $cookie, $Login;
	public function Download($link) {
		global $premium_acc, $Referer;
		if (!preg_match('@https?://(?:www\.)?(?:uploaded\.(?:to|net)/file|ul\.to(?:/file)?)/([a-zA-Z\d]+)@i', $link, $fid)) html_error('Invalid link?.');
		$this->fid = $fid[1];
		$this->link = $Referer = 'http://uploaded.net/file/'.$fid[1];

		if (empty($_POST['step']) || $_POST['step'] != 1) {
			$x = 0;
			do {
				$this->page = $this->GetPage($this->link, $this->cookie);
				$header = substr($this->page, 0, strpos($this->page, "\r\n\r\n"));
				$this->cookie = GetCookiesArr($header, $this->cookie);
				$x++;
			} while ($x < 6 && substr($header, 9, 3) == '302' && stripos($header, "\nLocation: /file/{$fid[1]}") !== false);
			is_present($header, '/404', 'File Not Found');
			is_present($header, '/410', 'File Was Removed');
			is_present($header, 'Please check the URL for typing errors,', 'File not Found or Removed.');
		}

		$this->pA = (empty($_REQUEST['premium_user']) || empty($_REQUEST['premium_pass']) ? false : true);
		if (($_REQUEST['cookieuse'] == 'on' && preg_match('@login[\s\t]*=[\s\t]*([\w\%\-]+);?@i', $_REQUEST['cookie'], $c)) || ($_REQUEST['premium_acc'] == 'on' && !empty($premium_acc['uploaded_net']['cookie']))) {
			$cookie = (empty($c[1]) ? urldecode($premium_acc['uploaded_net']['cookie']) : urldecode($c[1]));
			if (strpos($cookie, '%')) $cookie = urldecode($cookie);
			$this->cookie = array('login' => urlencode($cookie));

			$x = 0;
			do {
				$this->page = $this->GetPage('http://uploaded.net/me', $this->cookie, 0, 'http://uploaded.net/'.($x > 0 ? 'me' : ''));
				$this->cookie = GetCookiesArr($this->page, $this->cookie);
				$x++;
			} while ($x < 6 && substr($this->page, 9, 3) == '302' && stripos($this->page, "\nLocation: /me") !== false);

			if (substr($this->page, 9, 3) != '200') html_error('Cookie Error: Invalid Cookie?.');
			if (stripos($this->page, '<em>Free</em>') !== false) {
				$this->changeMesg(lang(300).'<br /><b>Cookie: Account isn\'t premium</b><br />Using it as member.');
				$this->page = $this->GetPage($this->link, $this->cookie);
				return $this->FreeDL();
			}
			return $this->PremiumDL();
		} elseif (($_REQUEST['premium_acc'] == 'on' && ($this->pA || (!empty($premium_acc['uploaded_net']['user']) && !empty($premium_acc['uploaded_net']['pass']))))) {
			return $this->CookieLogin(($this->pA ? $_REQUEST['premium_user'] : $premium_acc['uploaded_net']['user']), ($this->pA ? $_REQUEST['premium_pass'] : $premium_acc['uploaded_net']['pass']));
		} else {
			return $this->FreeDL();
		}
	}

	private function FreeDL() {
		$url = 'http://uploaded.net';
		$errs = array('host' => 'Download of this file isn\'t available right now, try again later.', 'limit-dl' => 'Free download limit reached.', 'parallel' => 'You\'re already downloading a file.', 'size' => 'Only Premium users can download this file.', 'slot' => 'Free download of this file isn\'t available right now, try again later.', 'captcha' => 'Wrong CAPTCHA entered.');

		if (empty($_POST['step']) || $_POST['step'] != 1) {
			// Find countdown
			if (!preg_match('@<span[^>]*>[^<>]+<span[^>]*>(\d+)</span>[\s\t\r\n]+seconds[^<>]*</span>@i', $this->page, $cD)) html_error('Countdown not found.');
			// Check slots
			$page = $this->GetPage("$url/io/ticket/slot/".$this->fid, $this->cookie, 0, 0, 0, 1);
			if (stripos($page, 'succ:true') === false) {
				if (preg_match('@\"?err\"?\s*:\s*\"((?:[^\"]+(?:\\\")?)+)(?<!\\\)\"@i', $page, $err) && !empty($errs[$err[1]])) html_error($errs[$err[1]]);
				else html_error($errs['slot']);
			}
			// Download js and find site's recaptcha key
			$js = $this->GetPage("$url/js/download.js", $this->cookie);
			if (!preg_match('@Recaptcha\.create[\s\t]*\([\s\t]*\"[\s\t]*([\w\.\-]+)[\s\t]*\"@i', $js, $cpid)) html_error('reCAPTCHA Not Found.');
			// Do countdown
			if ($cD[1] > 0) $this->CountDown($cD[1]);
			// Prepare data for reCAPTCHA and call it
			$data = $this->DefaultParamArr($this->link, encrypt(CookiesToStr($this->cookie)));
			$data['step'] = '1';
			return $this->reCAPTCHA($cpid[1], $data);
		}

		if (empty($_POST['recaptcha_response_field'])) html_error('You didn\'t enter the image verification code.');
		$this->cookie = StrToCookies(decrypt(urldecode($_POST['cookie'])));

		$post = array('recaptcha_challenge_field' => $_POST['recaptcha_challenge_field'], 'recaptcha_response_field' => $_POST['recaptcha_response_field']);
		$page = $this->GetPage("$url/io/ticket/captcha/".$this->fid, $this->cookie, $post, 0, 0, 1);
		if (!preg_match('@https?://(?:[a-zA-Z\d\-]+\.)+uploaded\.(?:net|to)/dl/[^\r\n\s\t\'\"<>]+@i', $page, $dl)) {
			if (preg_match('@\"?err\"?\s*:\s*\"((?:[^\"]+(?:\\\")?)+)(?<!\\\)\"@i', $page, $err)) {
				if (!empty($errs[$err[1]])) html_error($errs[$err[1]]);
				is_present($err[1], 'free downloads for this hour', $errs['limit-dl'].' Wait and hour an try again.');
				html_error('Unknown error after sending captcha: '.htmlentities($err[1]));
			}
			html_error('Download Link Not Found.');
		}

		$this->RedirectDownload($dl[0], 'uploaded_net_fr', $this->cookie);
	}

	private function PremiumDL() {
		// Parse and show BW from $this->page
		$cutted = cut_str($this->page, '<div id="traffic"', '</table>');
		if (!empty($cutted) && preg_match_all('@>(\d+(?:,\d+)?\s+(?:[KMGT]i?)?B)<@i', $cutted, $bw)) $this->changeMesg(lang(300)."<br />[Acc. Traffic] Download: {$bw[1][0]} + Hybrid: {$bw[1][1]} = Total: ".$bw[1][2]);

		$page = $this->GetPage($this->link, $this->cookie);
		if (!preg_match('@https?://(?:[a-zA-Z\d\-]+\.)+uploaded\.(?:net|to)/dl/[^\r\n\s\t\'\"<>]+@i', $page, $dl)) {
			$body = trim(substr($page, strpos($page, "\r\n\r\n") + 4));
			if ($body == '') html_error('Download-Link Not Found. (Empty page body)');
			if (stripos($body, 'Traffic is completely exhausted,') !== false || stripos($body, 'Su tr&#225;fico h&#237;brido esta completamente gastado') !== false) html_error('Premium account is out of bandwidth.');
			if (stripos($body, 'You used too many different IPs,') !== false || stripos($body, 'descarga bloqueada (ip)') !== false) html_error('Account blocked, too many IPs used for dl.');
			html_error('Account IP-blocked? | Not enough traffic? | Download-Link Not Found');
		}
		$this->RedirectDownload($dl[0], 'uploaded_net_pr', $this->cookie);
	}

	private function Login($user, $pass) {
		$post = array_map('urlencode', array('id' => $user, 'pw' => $pass));
		$page = $this->GetPage('http://uploaded.net/io/login', $this->cookie, $post, 'http://uploaded.net/', 0, 1);
		$body = trim(substr($page, strpos($page, "\r\n\r\n") + 4));
		is_present($body, 'No connection to database', 'Login failed: "No connection to database".');
		if (preg_match('@\{\"err\":\"([^\"]+)\"@i', $body, $err)) html_error('Login Error: "'.html_entity_decode(stripslashes($err[1])).'".');
		$this->cookie = GetCookiesArr($page, $this->cookie);
		if (empty($this->cookie['login'])) {
			if ($body == '') html_error('The host didn\'t replied the login request, wait 15-30 seconds and try again.');
			html_error('Login Error: Cannot find "login" cookie.');
		}

		$this->SaveCookies($user, $pass); // Update cookies file

		$this->page = $this->GetPage('http://uploaded.net/me', $this->cookie, 0, 'http://uploaded.net/');
		if (stripos($this->page, '<em>Free</em>') !== false) {
			$this->changeMesg(lang(300).'<br /><b>Account isn\'t premium</b><br />Using it as member.');
			$this->page = $this->GetPage($this->link, $this->cookie);
			return $this->FreeDL();
		}

		return $this->PremiumDL();
	}

	private function IWillNameItLater($cookie, $decrypt=true) {
		if (!is_array($cookie)) {
			if (!empty($cookie)) return $decrypt ? decrypt(urldecode($cookie)) : urlencode(encrypt($cookie));
			return '';
		}
		if (count($cookie) < 1) return $cookie;
		$keys = array_keys($cookie);
		$values = array_values($cookie);
		$keys = $decrypt ? array_map('decrypt', array_map('urldecode', $keys)) : array_map('urlencode', array_map('encrypt', $keys));
		$values = $decrypt ? array_map('decrypt', array_map('urldecode', $values)) : array_map('urlencode', array_map('encrypt', $values));
		return array_combine($keys, $values);
	}

	private function CookieLogin($user, $pass) {
		global $secretkey;
		if (empty($user) || empty($pass)) html_error('Login Failed: User or Password is empty.');
		$user = strtolower($user);

		$filename = DOWNLOAD_DIR . basename('uploaded_dl.php');
		if (!file_exists($filename) || filesize($filename) <= 6) return $this->Login($user, $pass);

		$file = file($filename);
		$savedcookies = unserialize($file[1]);
		unset($file);

		$hash = hash('crc32b', $user.':'.$pass);
		if (is_array($savedcookies) && array_key_exists($hash, $savedcookies)) {
			$_secretkey = $secretkey;
			$secretkey = hash('crc32b', $pass).sha1($user.':'.$pass).hash('crc32b', $user); // A 56 char key should be safer. :D
			$testCookie = (decrypt(urldecode($savedcookies[$hash]['enc'])) == 'OK') ? $this->IWillNameItLater($savedcookies[$hash]['cookie']) : '';
			$secretkey = $_secretkey;
			if (empty($testCookie) || (is_array($testCookie) && count($testCookie) < 1)) return $this->Login($user, $pass);

			$x = 0;
			do {
				$this->page = $this->GetPage('http://uploaded.net/me', $testCookie, 0, 'http://uploaded.net/'.($x > 0 ? 'me' : ''));
				$testCookie = GetCookiesArr($this->page, $testCookie);
				$x++;
			} while ($x < 6 && substr($this->page, 9, 3) == '302' && stripos($this->page, "\nLocation: /me") !== false);

			if (substr($this->page, 9, 3) != '200') return $this->Login($user, $pass);
			$this->cookie = $testCookie; // Update cookies
			$this->SaveCookies($user, $pass); // Update cookies file
			if (stripos($this->page, '<em>Free</em>') !== false) {
				$this->changeMesg(lang(300).'<br /><b>Account isn\'t premium</b><br />Using it as member.');
				$this->page = $this->GetPage($this->link, $this->cookie);
				return $this->FreeDL();
			}
			return $this->PremiumDL();
		}
		return $this->Login($user, $pass);
	}

	private function SaveCookies($user, $pass) {
		global $secretkey;
		$maxdays = 31; // Max days to keep cookies for more than 1 user.
		$filename = DOWNLOAD_DIR . basename('uploaded_dl.php');
		if (file_exists($filename) && filesize($filename) > 6) {
			$file = file($filename);
			$savedcookies = unserialize($file[1]);
			unset($file);

			// Remove old cookies
			if (is_array($savedcookies)) {
				foreach ($savedcookies as $k => $v) if (time() - $v['time'] >= ($maxdays * 24 * 60 * 60)) unset($savedcookies[$k]);
			} else $savedcookies = array();
		} else $savedcookies = array();
		$hash = hash('crc32b', $user.':'.$pass);
		$_secretkey = $secretkey;
		$secretkey = hash('crc32b', $pass).sha1($user.':'.$pass).hash('crc32b', $user); // A 56 char key should be safer. :D
		$savedcookies[$hash] = array('time' => time(), 'enc' => urlencode(encrypt('OK')), 'cookie' => $this->IWillNameItLater($this->cookie, false));
		$secretkey = $_secretkey;

		file_put_contents($filename, "<?php exit(); ?>\r\n" . serialize($savedcookies), LOCK_EX);
	}
}

//[29-5-2013] Written by Th3-822.
//[09-1-2013] Added '410 Gone' error. - Th3-822
//[06-12-2015] Added support for redirects blocking site access. #6DCambiemosVzla - Th3-822

?>
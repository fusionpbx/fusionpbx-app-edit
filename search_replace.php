<?php
/*
	FusionPBX
	Version: MPL 1.1

	The contents of this file are subject to the Mozilla Public License Version
	1.1 (the "License"); you may not use this file except in compliance with
	the License. You may obtain a copy of the License at
	http://www.mozilla.org/MPL/

	Software distributed under the License is distributed on an "AS IS" basis,
	WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License
	for the specific language governing rights and limitations under the
	License.

	The Original Code is FusionPBX

	The Initial Developer of the Original Code is
	Mark J Crane <markjcrane@fusionpbx.com>
	Portions created by the Initial Developer are Copyright (C) 2026
	the Initial Developer. All Rights Reserved.

	Contributor(s):
	Mark J Crane <markjcrane@fusionpbx.com>
*/

//includes files
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once "resources/check_auth.php";

//check permissions
	if (!permission_exists('edit_view')) {
		echo "access denied";
		exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//compare the tokens
	$key_name = '/app/edit/'.$_POST['mode'];
	$hash = hash_hmac('sha256', $key_name, $_SESSION['keys'][$key_name]);
	if (!hash_equals($hash, $_POST['token'])) {
		echo "access denied";
		exit;
	}

//resolve the edit directory (same logic as file_list.php / file_read.php)
function edit_dir_resolve($settings, $dir) {
	switch ($dir) {
		case 'scripts':
			return $settings->get('switch', 'scripts');
		case 'php':
			return dirname(__DIR__, 2);
		case 'grammar':
			return $settings->get('switch', 'grammar');
		case 'provision':
			switch (PHP_OS) {
				case "Linux":
					if (file_exists('/usr/share/fusionpbx/templates/provision')) {
						return '/usr/share/fusionpbx/templates/provision';
					}
					elseif (file_exists('/etc/fusionpbx/resources/templates/provision')) {
						return '/etc/fusionpbx/resources/templates/provision';
					}
					break;
				case "FreeBSD":
				case "NetBSD":
				case "OpenBSD":
					if (file_exists('/usr/local/share/fusionpbx/templates/provision')) {
						return '/usr/share/fusionpbx/templates/provision';
					}
					elseif (file_exists('/usr/local/etc/fusionpbx/resources/templates/provision')) {
						return '/usr/local/etc/fusionpbx/resources/templates/provision';
					}
					break;
			}
			return $_SERVER["DOCUMENT_ROOT"].PROJECT_PATH."/resources/templates/provision";
		case 'xml':
			return $settings->get('switch', 'conf');
		case 'src':
			return '/usr/src/freeswitch-1.10.12';
	}
	return null;
}

if (!isset($_SESSION)) { session_start(); }
$dir = $_SESSION["app"]["edit"]["dir"] ?? $_POST['dir'] ?? '';
$edit_directory = edit_dir_resolve($settings, $dir);

//binary/irrelevant extensions to skip
$skip_ext = array('.svn', '.git', '.db', '.jpg', '.gif', '.png', '.ico', '.ttf', '.zip', '.tar', '.gz', '.mp3', '.mp4', '.avi', '.pdf', '.so', '.o', '.a', '.bin', '.exe', '.dll');

function edit_dir_is_skipped($path, $skip_ext) {
	foreach ($skip_ext as $ext) {
		if (substr(strtolower($path), -strlen($ext)) == $ext) {
			return true;
		}
	}
	return false;
}

function edit_dir_path_allowed($file_path, $edit_directory) {
	//remove attempts to change the directory
	$file_path = str_replace('..', '', $file_path);
	$file_path = str_replace ("\\", "/", $file_path);
	if (!file_exists($file_path)) {
		return false;
	}
	//validate the path is inside the edit directory
	if (realpath(dirname($file_path)) == realpath($edit_directory)
	|| strpos(realpath($file_path), realpath($edit_directory).'/') === 0) {
		return true;
	}
	return false;
}

$action = $_POST['action'] ?? '';

if ($action == 'search') {
	$search = $_POST['search'] ?? '';
	if ($search == '') {
		echo json_encode(array('error' => 'empty search term'));
		exit;
	}
	if (!isset($edit_directory) || !is_dir($edit_directory)) {
		echo json_encode(array('error' => 'invalid directory'));
		exit;
	}
	$case_sensitive = ($_POST['case_sensitive'] ?? '') == '1';
	$use_regex = ($_POST['use_regex'] ?? '') == '1';

	$pattern = $use_regex
		? ($case_sensitive ? '/'.$search.'/s' : '/'.$search.'/is')
		: null;
	if ($use_regex && @preg_match($pattern, '') === false) {
		echo json_encode(array('error' => 'invalid regular expression'));
		exit;
	}

	$results = array();
	$max_results = 500;
	$max_file_size = 2 * 1024 * 1024; // 2 MB

	$stack = array($edit_directory);
	while (count($stack) > 0 && count($results) < $max_results) {
		$dir_path = array_pop($stack);
		$dir_handle = opendir($dir_path);
		if (!$dir_handle) {
			continue;
		}
		$entries = array();
		while (false !== ($entry = readdir($dir_handle))) {
			if ($entry != '.' && $entry != '..') {
				$entries[] = $entry;
			}
		}
		closedir($dir_handle);
		sort($entries);
		foreach ($entries as $entry) {
			$path = $dir_path.'/'.$entry;
			if (is_dir($path)) {
				if (substr($entry, 0, 1) == '.') {
					continue;
				}
				$stack[] = $path;
			}
			elseif (is_file($path) && !edit_dir_is_skipped($path, $skip_ext) && filesize($path) <= $max_file_size) {
				$content = @file_get_contents($path);
				if ($content === false) {
					continue;
				}
				$lines = explode("\n", $content);
				for ($i = 0; $i < count($lines); $i++) {
					$line = $lines[$i];
					$match = $use_regex
						? @preg_match($pattern, $line)
						: ($case_sensitive ? (strpos($line, $search) !== false) : (stripos($line, $search) !== false));
					if ($match) {
						$context = trim($line);
						if (strlen($context) > 120) {
							$context = substr($context, 0, 120) . '…';
						}
						$results[] = array(
							'file' => str_replace('\\', '/', $path),
							'line' => $i + 1,
							'context' => $context
						);
						if (count($results) >= $max_results) {
							break 3;
						}
					}
				}
			}
		}
	}

	echo json_encode(array('results' => $results, 'count' => count($results)));
	exit;
}
elseif ($action == 'replace') {
	$file_path = $_POST['filepath'] ?? '';
	$search = $_POST['search'] ?? '';
	$replace = $_POST['replace'] ?? '';
	if ($search == '' || $file_path == '') {
		echo json_encode(array('error' => 'empty search term'));
		exit;
	}
	if (!isset($edit_directory) || !is_dir($edit_directory)) {
		echo json_encode(array('error' => 'invalid directory'));
		exit;
	}
	if (!edit_dir_path_allowed($file_path, $edit_directory)) {
		echo json_encode(array('error' => 'access denied'));
		exit;
	}
	$case_sensitive = ($_POST['case_sensitive'] ?? '') == '1';
	$use_regex = ($_POST['use_regex'] ?? '') == '1';

	$pattern = $use_regex
		? ($case_sensitive ? '/'.$search.'/s' : '/'.$search.'/is')
		: null;
	if ($use_regex && @preg_match($pattern, '') === false) {
		echo json_encode(array('error' => 'invalid regular expression'));
		exit;
	}

	$content = @file_get_contents($file_path);
	if ($content === false) {
		echo json_encode(array('error' => 'unable to read file'));
		exit;
	}

	if ($use_regex) {
		$new_content = @preg_replace($pattern, $replace, $content, -1, $count);
	}
	else {
		$count = 0;
		$new_content = $case_sensitive
			? str_replace($search, $replace, $content, $count)
			: str_ireplace($search, $replace, $content, $count);
	}
	if ($new_content === false) {
		echo json_encode(array('error' => 'replacement failed'));
		exit;
	}

	$handle = @fopen($file_path, 'wb');
	if (!$handle) {
		echo json_encode(array('error' => 'write failed - check file owner & permissions'));
		exit;
	}
	fwrite($handle, $new_content);
	fclose($handle);

	//set the reload_xml value to true
	$_SESSION["reload_xml"] = true;

	echo json_encode(array('count' => $count));
	exit;
}

echo json_encode(array('error' => 'unknown action'));

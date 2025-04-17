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
	Portions created by the Initial Developer are Copyright (C) 2008-2023
	the Initial Developer. All Rights Reserved.

	Contributor(s):
	Mark J Crane <markjcrane@fusionpbx.com>
	James Rose <james.o.rose@gmail.com>
*/

//includes files
require_once dirname(__DIR__, 2) . "/resources/require.php";
require_once "resources/check_auth.php";

//check permissions
if (permission_exists('edit_view')) {
	//access granted
} else {
	echo "access denied";
	exit;
}

//add multi-lingual support
$language = new text();
$text = $language->get();

//set the domain and user
$domain_uuid = $_SESSION['domain_uuid'] ?? '';
$user_uuid = $_SESSION['user_uuid'] ?? '';

//ensure database and settings objects are created
global $database;
if (empty($database) || !($database instanceof database)) {
	$database = database::new();
}
if (empty($settings) || !($settings instanceof settings)) {
	$settings = new settings(['database' => $database, 'domain_uuid' => $domain_uuid, 'user_uuid' => $user_uuid]);
}

//create the settings object
	if (!$settings) {
		$settings = new settings();
	}

//add css and javascript
require_once "header.php";

//define function recur_dir
function recur_dir($dir) {
	clearstatcache();
	$html_dir_list = '';
	$html_file_list = '';
	$dir_handle = opendir($dir);
	$dir_array = array();
	if (!($dir_handle)) {
		return;
	}
	$x = 0;
	while (false !== ($file = readdir($dir_handle))) {
		if ($file === '.' || $file === '..') {
			continue;
		}
		$newpath = $dir . '/' . $file;
		$level = explode('/', $newpath);
		if (
			substr(strtolower($newpath), -4) == ".svn" ||
			substr(strtolower($newpath), -4) == ".git" ||
			substr(strtolower($newpath), -3) == ".db" ||
			substr(strtolower($newpath), -4) == ".jpg" ||
			substr(strtolower($newpath), -4) == ".gif" ||
			substr(strtolower($newpath), -4) == ".png" ||
			substr(strtolower($newpath), -4) == ".ico" ||
			substr(strtolower($newpath), -4) == ".ttf"
		) {
			//ignore certain files (and folders)
			continue;
		}
		$dir_array[] = $newpath;
		//only allow up to 1000 files
		if (++$x > 1000) { break; }
	}

	//finished with directory
	closedir($dir_handle);

	//sort directories
	asort($dir_array);

	foreach ($dir_array as $new_path) {
		$level = explode('/', $new_path);

		if (is_dir($new_path)) {
			$dirname = end($level);
			$html_dir_list .= "<div style='white-space: nowrap; padding-left: 16px;'>\n";
			$html_dir_list .= "<a onclick='Toggle(this);' style='display: block; cursor: pointer;'><img alt='folder' src='resources/images/icon_folder.png' style='margin: 1px 2px 3px -1px; vertical-align: middle; margin-right: 5px;'>$dirname</a>";
			$html_dir_list .= "<div style='display: none;'>" . recur_dir($new_path) . "</div>\n";
			$html_dir_list .= "</div>\n";
		} else {
			$filename = end($level);
			$filesize = round(filesize($new_path) / 1024, 2);
			$new_path = str_replace('//', '/', $new_path);
			$new_path = str_replace("\\", "/", $new_path);
			$html_file_list .= "<div style='white-space: nowrap; padding-left: 16px;'>\n";
			$html_file_list .= "<a href='javascript:void(0);' onclick=\"loadFileTab('$new_path');\" title='$new_path &#10; $filesize KB'>";
			$html_file_list .= "<img alt='file' src='resources/images/icon_file.png' style='margin: 1px 2px 3px -1px; vertical-align: middle; margin-right: 5px;'>$filename</a>\n";
			$html_file_list .= "</div>\n";
		}
	}

	//return completed html
	return $html_dir_list . "\n" . $html_file_list;
}

//get the directory
	if (!isset($_SESSION)) { session_start(); }

	switch ($_SESSION["app"]["edit"]["dir"]) {
		case 'scripts':
			$edit_directory = $settings->get('switch', 'scripts', '/usr/share/freeswitch/scripts');
			break;
		case 'php':
			$edit_directory = dirname(__DIR__, 2);
			break;
		case 'grammar':
			$edit_directory = $settings->get('switch', 'grammar', '/usr/share/freeswitch/grammar');
			break;
		case 'provision':
			switch (PHP_OS) {
				case "Linux":
					if (file_exists('/usr/share/fusionpbx/templates/provision')) {
						$edit_directory = '/usr/share/fusionpbx/templates/provision';
					} elseif (file_exists('/etc/fusionpbx/resources/templates/provision')) {
						$edit_directory = '/etc/fusionpbx/resources/templates/provision';
					} else {
						$edit_directory = dirname(__DIR__, 2) . "/resources/templates/provision";
					}
					break;
				case "FreeBSD":
				case "OpenBSD":
				case "NetBSD":
					if (file_exists('/usr/local/share/fusionpbx/templates/provision')) {
						$edit_directory = '/usr/share/fusionpbx/templates/provision';
					} else {
						$edit_directory = dirname(__DIR__, 2) . "/resources/templates/provision";
					}
					break;
				default:
					$edit_directory = dirname(__DIR__, 2) . "/resources/templates/provision/";
			}
			break;
		case 'xml':
			$edit_directory = $settings->get('switch', 'conf', '/etc/freeswitch/autoload_configs');
			break;
		default:
			//do not allow unknown settings
			exit();
	}

// keyboard shortcut bindings
echo "<script src='" . PROJECT_PATH . "/resources/jquery/jquery-3.6.1.min.js'></script>\n";

//save file
key_press('ctrl+s', 'down', 'window', null, null, "$('form#frm_edit').submit(); return false;", true);

//open file manager/clip library pane
key_press('ctrl+q', 'down', 'window', null, null, 'toggle_sidebar(); focus_editor(); return false;', true);

//prevent backspace (browser history back)
key_press('backspace', 'down', 'window', null, null, 'return false;', true);

echo "</head>\n";
echo "<body style='margin: 0px; padding: 5px;'>\n";

echo "<div style='text-align: left; padding-top: 3px; padding-bottom: 3px;'>\n";
echo "	<a href='javascript:void(0);' onclick=\"window.open('file_options.php','filewin','left=20,top=20,width=310,height=350,toolbar=0,resizable=0');\" style='text-decoration:none;' title='" . $text['label-files'] . "'>\n";
echo "			<img src='resources/images/icon_gear.png' border='0' align='absmiddle' style='margin: 0px 2px 4px -1px;' />\n";
echo $text['label-files'];
echo "	</a>\n";
echo "</div>\n";
echo "<div style='text-align: left; margin-left: -16px;'>\n";

if (file_exists($edit_directory)) {
	echo recur_dir($edit_directory);
}

echo "</div>\n";

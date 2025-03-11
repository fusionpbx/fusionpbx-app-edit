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
	}
	else {
		echo "access denied";
		exit;
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
		if (($dir_handle)) {
			$x = 0;
			while (false !== ($file = readdir($dir_handle))) {
				if ($file != "." AND $file != "..") {
					$newpath = $dir.'/'.$file;
					$level = explode('/',$newpath);
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
					}
					else {
						$dir_array[] = $newpath;
					}
					if ($x > 1000) { break; }
					$x++;
				}
			}
		}

		asort($dir_array);
		foreach ($dir_array as $newpath){
			$level = explode('/',$newpath);

			if (is_dir($newpath)) {
				$dirname = end($level);
				$html_dir_list .= "<div style='white-space: nowrap; padding-left: 16px;'>\n";
				$html_dir_list .= "<a onclick='Toggle(this);' style='display: block; cursor: pointer;'><img src='resources/images/icon_folder.png' border='0' align='absmiddle' style='margin: 1px 2px 3px 0px;'>".$dirname."</a>";
				$html_dir_list .= "<div style='display: none;'>".recur_dir($newpath)."</div>\n";
				$html_dir_list .= "</div>\n";
			}
			else {
				$filename = end($level);
				$filesize = round(filesize($newpath)/1024, 2);
				$newpath = str_replace ('//', '/', $newpath);
				$newpath = str_replace ("\\", "/", $newpath);
				$html_file_list .= "<div style='white-space: nowrap; padding-left: 16px;'>\n";
				$html_file_list .= "<a href='javascript:void(0);' onclick=\"document.getElementById('filepath').value='".$newpath."'; document.getElementById('current_file').value = '".$newpath."'; makeRequest('file_read.php','file=".urlencode($newpath)."');\" title='".$newpath." &#10; ".$filesize." KB'>";
				$html_file_list .= "<img src='resources/images/icon_file.png' border='0' align='absmiddle' style='margin: 1px 2px 3px -1px;'>".$filename."</a>\n";
				$html_file_list .= "</div>\n";
			}
		}

		closedir($dir_handle);
		return $html_dir_list ."\n". $html_file_list;
	}

//get the directory
	if (!isset($_SESSION)) { session_start(); }
	switch ($_SESSION["app"]["edit"]["dir"]) {
		case 'scripts':
			$edit_directory = $_SESSION['switch']['scripts']['dir'];
			break;
		case 'php':
			$edit_directory = $_SERVER["DOCUMENT_ROOT"].'/'.PROJECT_PATH;
			break;
		case 'grammar':
			$edit_directory = $_SESSION['switch']['grammar']['dir'];
			break;
		case 'provision':
			switch (PHP_OS) {
				case "Linux":
					if (file_exists('/usr/share/fusionpbx/templates/provision')) {
						$edit_directory = '/usr/share/fusionpbx/templates/provision';
					}
					elseif (file_exists('/etc/fusionpbx/resources/templates/provision')) {
						$edit_directory = '/etc/fusionpbx/resources/templates/provision';
					}
					else {
						$edit_directory = $_SERVER["DOCUMENT_ROOT"].PROJECT_PATH."/resources/templates/provision";
					}
					break;
				case "FreeBSD":
					if (file_exists('/usr/local/share/fusionpbx/templates/provision')) {
						$edit_directory = '/usr/share/fusionpbx/templates/provision';
					}
					elseif (file_exists('/usr/local/etc/fusionpbx/resources/templates/provision')) {
						$edit_directory = '/usr/local/etc/fusionpbx/resources/templates/provision';
					}
					else {
						$edit_directory = $_SERVER["DOCUMENT_ROOT"].PROJECT_PATH."/resources/templates/provision";
					}
					break;
				case "NetBSD":
					if (file_exists('/usr/local/share/fusionpbx/templates/provision')) {
						$edit_directory = '/usr/share/fusionpbx/templates/provision';
					}
					else {
						$edit_directory = $_SERVER["DOCUMENT_ROOT"].PROJECT_PATH."/resources/templates/provision";
					}
					break;
				case "OpenBSD":
					if (file_exists('/usr/local/share/fusionpbx/templates/provision')) {
						$edit_directory = '/usr/share/fusionpbx/templates/provision';
					}
					else {
						$edit_directory = $_SERVER["DOCUMENT_ROOT"].PROJECT_PATH."/resources/templates/provision";
					}
					break;
				default:
					$edit_directory = $_SERVER["DOCUMENT_ROOT"].PROJECT_PATH."/resources/templates/provision/";
			}
			break;
		case 'xml':
			$edit_directory = $_SESSION['switch']['conf']['dir'];
			break;
	}
	if (!isset($edit_directory) && is_array($_SESSION['editor']['path'])) {
		foreach ($_SESSION['editor']['path'] as $path) {
			if ($_SESSION["app"]["edit"]["dir"] == $path) {
				$edit_directory = $path;
				break;
			}
		}
	}


// keyboard shortcut bindings
echo "<script src='".PROJECT_PATH."/resources/jquery/jquery-3.6.1.min.js'></script>\n";
echo "<script src='https://code.jquery.com/jquery-migrate-3.1.0.js'></script>\n";

//save file
key_press('ctrl+s', 'down', 'window', null, null, "$('form#frm_edit').submit(); return false;", true);

//open file manager/clip library pane
key_press('ctrl+q', 'down', 'window', null, null, 'toggle_sidebar(); focus_editor(); return false;', true);

//prevent backspace (browser history back)
key_press('backspace', 'down', 'window', null, null, 'return false;', true);

echo "</head>\n";
echo "<body style='margin: 0px; padding: 5px;'>\n";

echo "<div style='text-align: left; padding-top: 3px; padding-bottom: 3px;'><a href='javascript:void(0);' onclick=\"window.open('file_options.php','filewin','left=20,top=20,width=310,height=350,toolbar=0,resizable=0');\" style='text-decoration:none;' title='".$text['label-files']."'><img src='resources/images/icon_gear.png' border='0' align='absmiddle' style='margin: 0px 2px 4px -1px;'>".$text['label-files']."</a></div>\n";
echo "<div style='text-align: left; margin-left: -16px;'>\n";
if (function_exists('apcu_enabled') && apcu_enabled() && apcu_exists('edit_html_list')) {
	echo apcu_fetch('edit_html_list');
	exit();
}

if (file_exists($edit_directory)) {
	$edit_html_list = recur_dir($edit_directory);

	if (function_exists('apcu_enabled') && apcu_enabled()) {
		apcu_store('edit_html_list', $edit_html_list); // only available for 5 minutes
	}
	echo $edit_html_list;
}

echo "</div>\n";

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
	Portions created by the Initial Developer are Copyright (C) 2008-2024
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

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//set the directory title and mode
	switch ($_GET["dir"]) {
		case 'xml':
			$title = 'XML';
			$mode = 'xml';
			$dir = 'xml';
			break;
		case 'provision':
			$title = 'Provision';
			$mode = 'xml';
			$dir = 'provision';
			break;
		case 'php':
			$title = 'PHP';
			$mode = 'php';
			$dir = 'php';
			break;
		case 'scripts':
			$title = 'Scripts';
			$mode = 'lua';
			$dir = 'scripts';
			break;
		case 'grammar':
			$title = 'Grammar';
			$mode = 'xml';
			$dir = 'grammar';
			break;
		default:
			$mode = 'text';
			$dir = '';
	}

//save the sanitized value
	$_SESSION['app']['edit']['dir'] = $dir;

//load editor preferences/defaults
	$setting_size       = $settings->get('editor', 'font_size', '12px');
	$setting_theme      = $settings->get('editor', 'theme', 'cobalt');
	$setting_invisibles = $settings->get('editor', 'invisibles','false');
	$setting_indenting  = $settings->get('editor', 'indent_guides','false');
	$setting_numbering  = $settings->get('editor', 'line_numbers','true');

//get and then set the favicon
	$favicon = $settings->get('theme', 'favicon', PROJECT_ROOT .'/themes/default/favicon.ico');

//create a token
	$key_name = '/app/edit/'.$mode;
	$_SESSION['keys'][$key_name] = bin2hex(random_bytes(32));
	$_SESSION['token'] = hash_hmac('sha256', $key_name, $_SESSION['keys'][$key_name]);

	//The buffer must be empty
	while(ob_get_level() > 0)
		ob_get_clean();

?><!doctype html>
<html>
<head>
	<meta http-equiv='Content-Type' content='text/html; charset=UTF-8' />
	<title><?php echo $title; ?></title>
	<link rel="icon" type="image/x-icon" href="<?php echo $favicon; ?>">
	<link rel='stylesheet' type='text/css' href='<?php echo PROJECT_PATH; ?>/resources/fontawesome/css/all.min.css.php'>
	<script language="JavaScript" type="text/javascript" src="<?php echo PROJECT_PATH; ?>/resources/jquery/jquery-3.6.1.min.js"></script>
	<script language="JavaScript" type="text/javascript">
		function submit_check() {
			if (document.getElementById('filepath').value != '') {
				document.getElementById('editor_source').value = editor.getSession().getValue();
				return true;
			}
			focus_editor();
			return false;
		}

		function toggle_option(opt) {
			switch (opt) {
				case 'numbering': 	toggle_option_do('showLineNumbers'); toggle_option_do('fadeFoldWidgets'); break;
				case 'invisibles':	toggle_option_do('showInvisibles'); break;
				case 'indenting':	toggle_option_do('displayIndentGuides'); break;
			}
			focus_editor();
		}

		function toggle_option_do(opt_name) {
			var opt_val = editor.getOption(opt_name);
			editor.setOption(opt_name, ((opt_val) ? false : true));
		}

		function toggle_sidebar() {
			var td_sidebar = document.getElementById('sidebar');
			if (td_sidebar.style.display == '') {
				document.getElementById('td_save').style.paddingLeft = '12px';
				td_sidebar.style.display = 'none';
			}
			else {
				document.getElementById('td_save').style.paddingLeft = '0';
				td_sidebar.style.display = '';
			}
			focus_editor();
		}

		function insert_clip(before, after) {
			var selected_text = editor.session.getTextRange(editor.getSelectionRange());
			editor.insert(before + selected_text + after);
			focus_editor();
		}

		function focus_editor() {
			editor.focus();
		}

		function http_request(url, form_data) {
			var http = new XMLHttpRequest();
			http.open('POST', url, true);
			//http.onload = function(e) { ... };
			http.onload = function(e) {
				if (this.status == 200) {
					//data sent successfully
					alert(this.responseText);
				}
				else {
					alert('<?php echo $text['message-problem']; ?>');
				}
			};
			http.send(form_data);
		}

		function save() {
			var form_data = new FormData();
			form_data.append('filepath', document.getElementById('filepath').value);
			form_data.append('content', editor.getSession().getValue());
			form_data.append('token',document.getElementById('token').value);
			form_data.append('mode',"<?php echo $mode; ?>");

			http_request('file_save.php', form_data);
		}

	</script>
	<style>
		div#editor {
			box-shadow: 0 5px 15px #333;
			}

		i.ace_control {
			cursor: pointer;
			margin-right: 5px;
			opacity: 0.5;
			}

		i.ace_control:hover {
			opacity: 1.0;
			}
	</style>
</head>
<body style="padding: 0; margin: 0; overflow: hidden;">
  <div id="frame" style="display: flex; height: 100vh; width: 100vw;">
    <!-- Sidebar -->
    <div id="sidebar" style="width: 300px; height: 100%; display: flex; flex-direction: column;">
		<div id="file_list" style="border: none; height: 65%; width: 100%; overflow: auto;">
			Loading...
		</div>
		<div id="clip_list" style="border: none; border-top: 1px solid #ccc; height: calc(35% - 1px); width: 100%; overflow: auto;">
			Loading...
		</div>
    </div>

    <!-- Main Content -->
    <div id="ace_content" style="flex: 1; height: 100%; display: flex; flex-direction: column;">
      <!-- Editor Controls -->
      <form style="margin: 0;" name="frm_edit" id="frm_edit" method="post" action="file_save.php" onsubmit="return submit_check();">
        <textarea name="content" id="editor_source" style="display: none;"></textarea>
        <input type="hidden" name="filepath" id="filepath" value="">
        <input type="hidden" name="token" id="token" value="<?php echo $_SESSION['token']; ?>">
        <div id="editor-controls" style="display: flex; align-items: center; width: 100%; height: 30px;">
          <div id="td_save" style="display: inline-flex; align-items: center;">
            <i class="fas fa-save fa-lg ace_control" title="<?php echo $text['label-save_changes']; ?>" onclick="save();"></i>
          </div>
          <div style="flex: 1; padding: 0 15px 0 18px;">
            <input id="current_file" type="text" style="height: 23px; width: 100%;">
          </div>
          <div style="width: 1px; height: 40px;"></div>
          <div style="padding-left: 6px;">
            <i class="fas fa-window-maximize fa-lg fa-rotate-270 ace_control" title="<?php echo $text['label-toggle_side_bar']; ?>" onclick="toggle_sidebar();"></i>
          </div>
          <div style="padding-left: 6px;">
            <i class="fas fa-list-ul fa-lg ace_control" title="<?php echo $text['label-toggle_line_numbers']; ?>" onclick="toggle_option('numbering');"></i>
          </div>
          <div style="padding-left: 6px;">
            <i class="fas fa-eye-slash fa-lg ace_control" title="<?php echo $text['label-toggle_invisibles']; ?>" onclick="toggle_option('invisibles');"></i>
          </div>
          <div style="padding-left: 6px;">
            <i class="fas fa-indent fa-lg ace_control" title="<?php echo $text['label-toggle_indent_guides']; ?>" onclick="toggle_option('indenting');"></i>
          </div>
          <div style="padding-left: 6px;">
            <i class="fas fa-search fa-lg ace_control" title="<?php echo $text['label-find_replace']; ?>" onclick="editor.execCommand('replace');"></i>
          </div>
          <div style="padding-left: 6px;">
            <i class="fas fa-chevron-down fa-lg ace_control" title="<?php echo $text['label-go_to_line']; ?>" onclick="editor.execCommand('gotoline');"></i>
          </div>
          <div style="padding-left: 15px;">
            <select id="mode" style="height: 23px; max-width: 70px;" onchange="editor.getSession().setMode('ace/mode/' + this.options[this.selectedIndex].value); focus_editor();">
              <?php
              $modes['php'] = 'PHP';
              $modes['css'] = 'CSS';
              $modes['html'] = 'HTML';
              $modes['javascript'] = 'JS';
              $modes['json'] = 'JSON';
              $modes['ini'] = 'Conf';
              $modes['lua'] = 'Lua';
              $modes['text'] = 'Text';
              $modes['xml'] = 'XML';
              $modes['sql'] = 'SQL';
              $modes['sh'] = 'SH';
              $modes['smarty'] = 'Smarty';
              $modes['svg'] = 'SVG';
              $modes['makefile'] = 'Makefile';
              $modes['c_cpp'] = 'C';
              $modes['c_cpp'] = 'CPP';
              $modes['pgsql'] = 'PGSQL';
              foreach ($modes as $value => $label) {
                  $selected = ($value == $mode) ? 'selected' : null;
                  echo "<option value='".$value."' ".$selected.">".$label."</option>\n";
              }
              ?>
            </select>
          </div>
          <div style="padding-left: 4px;">
            <select id="size" style="height: 23px;" onchange="document.getElementById('editor').style.fontSize = this.options[this.selectedIndex].value; focus_editor();">
              <?php
              $sizes = explode(',','9px,10px,11px,12px,14px,16px,18px,20px');
              if (!in_array($setting_size, $sizes)) {
                  echo "<option value='".$setting_size."'>".$setting_size."</option>\n";
                  echo "<option value='' disabled='disabled'></option>\n";
              }
              foreach ($sizes as $size) {
                  $selected = ($size == $setting_size) ? 'selected' : null;
                  echo "<option value='".$size."' ".$selected.">".$size."</option>\n";
              }
              ?>
            </select>
          </div>
          <div style="padding-left: 4px; padding-right: 4px;">
            <select id="theme" style="height: 23px; max-width: 100px;" onchange="editor.setTheme('ace/theme/' + this.options[this.selectedIndex].value); focus_editor();">
              <?php
              $themes['Bright']['chrome']= 'Chrome';
              $themes['Bright']['clouds']= 'Clouds';
              $themes['Bright']['crimson_editor']= 'Crimson Editor';
              $themes['Bright']['dawn']= 'Dawn';
              $themes['Bright']['dreamweaver']= 'Dreamweaver';
              $themes['Bright']['eclipse']= 'Eclipse';
              $themes['Bright']['github']= 'GitHub';
              $themes['Bright']['iplastic']= 'IPlastic';
              $themes['Bright']['solarized_light']= 'Solarized Light';
              $themes['Bright']['textmate']= 'TextMate';
              $themes['Bright']['tomorrow']= 'Tomorrow';
              $themes['Bright']['xcode']= 'XCode';
              $themes['Bright']['kuroir']= 'Kuroir';
              $themes['Bright']['katzenmilch']= 'KatzenMilch';
              $themes['Bright']['sqlserver']= 'SQL Server';
              $themes['Dark']['ambiance']= 'Ambiance';
              $themes['Dark']['chaos']= 'Chaos';
              $themes['Dark']['clouds_midnight']= 'Clouds Midnight';
              $themes['Dark']['cobalt']= 'Cobalt';
              $themes['Dark']['idle_fingers']= 'idle Fingers';
              $themes['Dark']['kr_theme']= 'krTheme';
              $themes['Dark']['merbivore']= 'Merbivore';
              $themes['Dark']['merbivore_soft']= 'Merbivore Soft';
              $themes['Dark']['mono_industrial']= 'Mono Industrial';
              $themes['Dark']['monokai']= 'Monokai';
              $themes['Dark']['pastel_on_dark']= 'Pastel on dark';
              $themes['Dark']['solarized_dark']= 'Solarized Dark';
              $themes['Dark']['terminal']= 'Terminal';
              $themes['Dark']['tomorrow_night']= 'Tomorrow Night';
              $themes['Dark']['tomorrow_night_blue']= 'Tomorrow Night Blue';
              $themes['Dark']['tomorrow_night_bright']= 'Tomorrow Night Bright';
              $themes['Dark']['tomorrow_night_eighties']= 'Tomorrow Night 80s';
              $themes['Dark']['twilight']= 'Twilight';
              $themes['Dark']['vibrant_ink']= 'Vibrant Ink';
              foreach ($themes as $optgroup => $theme) {
                  echo "<optgroup label='".$optgroup."'>\n";
                  foreach ($theme as $value => $label) {
                      $selected = (strtolower($label) == strtolower($setting_theme)) ? 'selected' : null;
                      echo "<option value='".$value."' ".$selected.">".$label."</option>\n";
                  }
                  echo "</optgroup>\n";
              }
              ?>
            </select>
          </div>
        </div>
      </form>
      <!-- Editor -->
	  	<div id="editor" style="text-align: left; width: 100%; height: calc(100% - 30px); font-size: 12px;"></div>
	  </div>
    </div>

  <script src="<?php echo PROJECT_PATH; ?>/resources/ace/ace.js" charset="utf-8"></script>
  <script src="<?php echo PROJECT_PATH; ?>/resources/ace/ext-inline_autocomplete.js"></script>
  <script>
    // Load ACE extensions
    ace.require("ace/ext/language_tools");

    // Initialize ACE Editor
    var editor = ace.edit("editor");
    editor.setOptions({
      mode: 'ace/mode/<?=$mode?>',
      theme: 'ace/theme/'+document.getElementById('theme').options[document.getElementById('theme').selectedIndex].value,
      selectionStyle: 'text',
      cursorStyle: 'smooth',
      showInvisibles: <?=$setting_invisibles?>,
      displayIndentGuides: <?=$setting_indenting?>,
      showLineNumbers: <?=$setting_numbering?>,
      showGutter: true,
      scrollPastEnd: true,
      fadeFoldWidgets: <?=$setting_numbering?>,
      showPrintMargin: false,
      highlightGutterLine: false,
      useSoftTabs: false,
      enableBasicAutocompletion: true,
      enableLiveAutocompletion: true,
      enableSnippets: true
    });

    // Prevent form submission with Enter key
    <?php key_press('enter', 'down', '#current_file', null, null, 'return false;', false); ?>

    // Save file with Ctrl+S
    <?php key_press('ctrl+s', 'down', 'window', null, null, "save(); return false;", false); ?>

    // Open file manager/clip library pane with Ctrl+Q
    <?php key_press('ctrl+q', 'down', 'window', null, null, 'toggle_sidebar(); focus_editor(); return false;', false); ?>

    // Remove unwanted shortcuts
    editor.commands.bindKey("Ctrl-T", null); // Disable new browser tab shortcut

	// Levenshtein distance algorithm
	function levenshteinDistance(a, b) {
		let m = a.length, n = b.length;
		let dp = [];
		for (let i = 0; i <= m; i++) {
			dp[i] = [i];
		}
		for (let j = 0; j <= n; j++) {
			dp[0][j] = j;
		}
		for (let i = 1; i <= m; i++) {
			for (let j = 1; j <= n; j++) {
				if (a[i - 1] === b[j - 1]) {
					dp[i][j] = dp[i - 1][j - 1];
				} else {
					dp[i][j] = Math.min(
						dp[i - 1][j] + 1,    // deletion
						dp[i][j - 1] + 1,    // insertion
						dp[i - 1][j - 1] + 1 // substitution
					);
				}
			}
		}
		return dp[m][n];
	}

	// Example function to find the closest matching class key
	function findClosestMatch(refName, phpMethods) {
		let bestMatch = null;
		let bestDistance = Infinity;
		let lowerRef = refName.toLowerCase();

		// Loop through all classes in phpMethods
		for (let key in phpMethods) {
			// Assume the simple class name is the last segment after a backslash
			let parts = key.split("\\");
			let simpleName = parts[parts.length - 1].toLowerCase();
			let distance = levenshteinDistance(lowerRef, simpleName);
			if (distance < bestDistance) {
				bestDistance = distance;
				bestMatch = key;
			}
		}
		return bestMatch;
	}

	// Function to fetch PHP class methods using fetch() with promises
	async function fetch_php_methods() {
		try {
			let response = await fetch('/app/edit/resources/get_php_methods.php');
			if (!response.ok) throw new Error("Failed to load PHP methods.");
			return await response.json();
		} catch (error) {
			console.error("Error fetching PHP methods:", error);
			return {}; // Return empty object on failure
		}
	}

	// Initialize ACE auto-completion after fetching PHP methods
	async function init_ace_completion() {
		const php_methods = await fetch_php_methods();

		// Custom completer for PHP class methods
		var php_class_completer = {
			getCompletions: function(editor, session, pos, prefix, callback) {

				// Define current_class_name by extracting the basename of the current file without extension.
				var current_file_path = document.getElementById('current_file').value;
				var current_file_name = current_file_path.split('/').pop();

				// Remove the extension (everything after the last dot)
				var current_class_name = current_file_name.replace(/\.[^/.]+$/, "");

				// Get the current line text
				var line = session.getLine(pos.row);

				// Use regex to detect object (->) or static (::) access.
				// This regex captures either "$this" or any other word.
				const object_match = line.match(/(\$this|\w+)\s*->\s*\w*$/);
				const static_match = line.match(/(self|static|\w+)::\w*$/);

				// Extract the referenced name; if it's "$this", use the current_class_name.
				var ref_name = object_match ? object_match[1] : (static_match ? static_match[1] : null);
				if (ref_name === '$this' | ref_name === 'self' | ref_name === 'static') {
					ref_name = current_class_name;
				}

				// If not a class, maybe a user function; use the prefix.
				if (prefix.length > 0) ref_name = prefix;

				if (!ref_name) return callback(null, []);

				// Find the closest matching class using fuzzy matching.
				var matched_class = findClosestMatch(ref_name, php_methods);
				if (!matched_class) return callback(null, []);

				// Map the methods of the matched class into completions.
				var completions = php_methods[matched_class].map(function(method) {
					// If static syntax is used but the method is not static, skip it.
					if (static_match !== null && !method.static) {
						return {};
					}

					// Map the item to the documentation
					var item = {
						caption: method.name + method.params,
						snippet: method.name + method.params.replace(/\$/g, "\\$"),
						meta: method.meta,
						docHTML: method.doc ? method.doc : "No Documentation"
					};

					// TODO: fix italics
					// Use italics in pop-up for static methods
					if (method.static) {
						item.className = "ace_static_method";
					}

					//return the mapped json object
					return item;
				});

				callback(null, completions);
			}
		};

		// Initialize ACE Editor
		ace.require("ace/ext/language_tools");

		// Replace the current list of completions with our custom one so we don't have every single word in the document listed as a completion option
		editor.completers = [php_class_completer];

		// Ensure font size is set
		document.getElementById('editor').style.fontSize = '<?=$setting_size?>';
		focus_editor();
	}

	// Run auto-completion setup
	init_ace_completion();
</script>
</body>
<script>
	fetch('clip_list.php')
		.then(response => {
			if (!response.ok) {
				throw new Error('Network response was not ok');
			}
			return response.text();
		})
		.then(html => {
			document.getElementById('clip_list').innerHTML = html;
		})
		.catch(error => {
			console.error('Error fetching clip_list:', error);
	});

	async function loadFileList() {
		try {
			const response = await fetch('file_list.php');
			if (!response.ok) {
				throw new Error('Network response not okay');
			}
			const html = await response.text();
			document.getElementById('file_list').innerHTML = html;
		} catch (error) {
			console.error('Error fetching files:', error);
		}
	}

	function makeRequest(url, strpost) {
        var http_request = false;

        if (window.XMLHttpRequest) { // Mozilla, Safari, ...
            http_request = new XMLHttpRequest();
            if (http_request.overrideMimeType) {
                http_request.overrideMimeType('text/xml');
                // See note below about this line
            }
        } else if (window.ActiveXObject) { // IE
            try {
                http_request = new ActiveXObject("Msxml2.XMLHTTP");
            } catch (e) {
                try {
                    http_request = new ActiveXObject("Microsoft.XMLHTTP");
                } catch (e) {}
            }
        }

        if (!http_request) {
            alert('<?=$text['message-give-up']?>');
            return false;
        }
        http_request.onreadystatechange = function() { returnContent(http_request); };
        if (http_request.overrideMimeType) {
              http_request.overrideMimeType('text/html');
        }
        http_request.open('POST', url, true);


        if (strpost.length == 0) {
            //http_request.send(null);
            http_request.send('name=value&foo=bar');
        }
        else {
            http_request.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
            http_request.send(strpost);
        }

    }

    function returnContent(http_request) {

        if (http_request.readyState === 4) {
            if (http_request.status === 200) {
				document.getElementById('editor_source').value=http_request.responseText;
				editor.getSession().setValue(document.getElementById('editor_source').value);
				editor.gotoLine(1);
				editor.scrollToLine(1, true, true, function() {});
				editor.focus();
            }
            else {
                alert('<?=$text['message-problem']?>');
            }
        }

    }
	// ---------------------------------------------
	// --- http://www.codeproject.com/jscript/dhtml_treeview.asp
	// --- Name:    Easy DHTML Treeview           --
	// --- Author:  D.D. de Kerf                  --
	// --- Version: 0.2          Date: 13-6-2001  --
	// ---------------------------------------------
	function Toggle(node) {
		// Unfold the branch if it isn't visible
		if (node.nextSibling.style.display == 'none') {
			node.nextSibling.style.display = 'block';
		}
		// Collapse the branch if it IS visible
		else {
			node.nextSibling.style.display = 'none';
		}
	}

// Load files from server
	loadFileList();

</script>
</html>
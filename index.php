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

// Include required files and check access
require_once dirname(__DIR__, 2) . "/resources/require.php";
require_once "resources/check_auth.php";

if (!permission_exists('edit_view')) {
    echo "access denied";
    exit;
}

$domain_uuid = $_SESSION['domain_uuid'] ?? '';
$user_uuid = $_SESSION['user_uuid'] ?? '';

// Add multi-lingual support
$language = new text();
$text = $language->get();

// Set the directory title and mode based on the query parameter
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

// Save the sanitized directory
$_SESSION['app']['edit']['dir'] = $dir;

// Ensure the database and settings objects are created
global $database;
if (empty($database) || !($database instanceof database)) {
    $database = database::new();
}
if (empty($settings) || !($settings instanceof settings)) {
    $settings = new settings(['database' => $database, 'domain_uuid' => $domain_uuid, 'user_uuid' => $user_uuid]);
}

// Load editor preferences/defaults
$setting_size = $settings->get('editor', 'font_size', '12px');
$setting_theme = $settings->get('editor', 'theme', 'cobalt');
$setting_invisibles = $settings->get('editor', 'invisibles', 'false');
$setting_indenting = $settings->get('editor', 'indent_guides', 'false');
$setting_numbering = $settings->get('editor', 'line_numbers', 'true');

// Get the favicon
$favicon = $settings->get('theme', 'favicon', PROJECT_ROOT . '/themes/default/favicon.ico');

// Create a token for file saving
$key_name = '/app/edit/' . $mode;
$_SESSION['keys'][$key_name] = bin2hex(random_bytes(32));
$_SESSION['token'] = hash_hmac('sha256', $key_name, $_SESSION['keys'][$key_name]);

// Make sure the output buffer is empty
while (ob_get_level() > 0) {
    ob_get_clean();
}
?>
<!doctype html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo $title; ?></title>
    <link rel="icon" type="image/x-icon" href="<?php echo $favicon; ?>">
    <link rel="stylesheet" href="<?php echo PROJECT_PATH; ?>/resources/fontawesome/css/all.min.css.php">
    <script src="<?php echo PROJECT_PATH; ?>/resources/jquery/jquery-3.6.1.min.js"></script>
    <!-- Ace Editor -->
    <script src="<?= PROJECT_PATH ?>/resources/ace/ace.js" charset="utf-8"></script>
    <script src="<?= PROJECT_PATH ?>/resources/ace/ext-inline_autocomplete.js"></script>
    <style>
        /* Basic reset */
        html, body {
            margin: 0;
            padding: 0;
            height: 100%;
            overflow: hidden;
            font-family: sans-serif;
        }
        /* Main frame layout */
        #frame {
            display: flex;
            height: 100vh;
            width: 100vw;
        }
        /* Left Sidebar */
        #float_sidebar {
            width: 20%;
            display: flex;
            flex-direction: column;
            background: #f8f8f8;
            border-right: 1px solid #ccc;
        }
        #float_sidebar > div {
            flex: 1;
            overflow: auto;
            padding: 10px;
        }
        #file_list {
            border-bottom: 1px solid #ccc;
        }
        /* Main Content */
        #float_content {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        /* Editor Header: tabs and toolbar */
        #editor-header {
            height: 40px;
            background: #f0f0f0;
            display: flex;
            align-items: center;
            padding: 0 10px;
            border-bottom: 1px solid #ccc;
        }
        #fileTabs {
            display: flex;
            list-style: none;
            margin: 0;
            padding: 0;
        }
        #fileTabs li {
            padding: 5px 10px;
            margin-right: 2px;
            border: 1px solid #ccc;
            border-bottom: none;
            cursor: pointer;
        }
        #fileTabs li.active {
            background: #fff;
            border-top: 2px solid #007acc;
            font-weight: bold;
        }
        #toolbar {
            margin-left: auto;
            display: flex;
            align-items: center;
        }
        .ace_control {
            cursor: pointer;
            margin-right: 10px;
            opacity: 0.7;
        }
        .ace_control:hover {
            opacity: 1;
        }
        /* Editor Body */
        #editor-body {
            flex: 1;
        }
        #editor {
            width: 100%;
            height: 100%;
        }
        /* Status Bar */
		#editor-status {
			display: flex;
			align-items: center;
			/* Add right padding to keep content away from the screen edge */
			padding-right: 15px;
			font-size: 14pt;
		}

		/* Fixed widths for the left sections (adjust as needed) */
		#status-message {
			width: 200px;
		}

		#status-filepath {
			width: 800px;
		}

		/* Style the cursor status group */
		#cursor-status-group {
			margin-left: auto;  /* Push this group to the far right */
/*			width: 200px;        Set a fixed width to prevent resizing */
			border-left: 2px solid #ccc; /* Vertical separator on the left */
			padding-left: 2px;
			display: flex;
/*			justify-content: flex-end;*/
			gap: 2px;
			box-sizing: border-box;
		}

		/* Give fixed widths to the individual cursor spans so that changes in text don't affect layout */
		#status-cursor-line,
		#status-cursor-column {
			width: 150px;  /* Adjust widths as needed */
			/*text-align: right;*/
		}
    </style>
</head>
<body>
<div id="frame">
    <!-- Left Sidebar: File List (upper) and Clips (lower) -->
    <div id="float_sidebar">
        <div id="file_list">Loading file list...</div>
        <div id="clip_list">Loading clips...</div>
    </div>
    <!-- Main Content -->
    <div id="float_content">
        <!-- Editor Header: File Tabs + Toolbar -->
        <div id="editor-header">
            <ul id="fileTabs"></ul>
            <div id="toolbar">
                <i class="fas fa-save ace_control" title="<?php echo $text['label-save_changes']; ?>" onclick="save();"></i>
                <i class="fas fa-search ace_control" title="<?php echo $text['label-find_replace']; ?>" onclick="editor.execCommand('replace');"></i>
                <i class="fas fa-chevron-down ace_control" title="<?php echo $text['label-go_to_line']; ?>" onclick="editor.execCommand('gotoline');"></i>
                <!-- Editor mode, size, and theme selectors -->
                <select id="mode" onchange="editor.getSession().setMode('ace/mode/' + this.value);">
                    <?php
                    $modes = [
                        'php' => 'PHP',
                        'css' => 'CSS',
                        'html' => 'HTML',
                        'javascript' => 'JS',
                        'json' => 'JSON',
                        'ini' => 'Conf',
                        'lua' => 'Lua',
                        'text' => 'Text',
                        'xml' => 'XML',
                        'sql' => 'SQL',
                        'sh' => 'SH',
                        'smarty' => 'Smarty',
                        'svg' => 'SVG',
                        'makefile' => 'Makefile',
                        'c_cpp' => 'C/CPP',
                        'pgsql' => 'PGSQL'
                    ];
                    foreach ($modes as $value => $label) {
                        $selected = ($value == $mode) ? 'selected' : '';
                        echo "<option value='{$value}' {$selected}>{$label}</option>";
                    }
                    ?>
                </select>
                <select id="size" onchange="document.getElementById('editor').style.fontSize = this.value;">
                    <?php
                    $sizes = explode(',', '9px,10px,11px,12px,14px,16px,18px,20px');
                    foreach ($sizes as $size) {
                        $selected = ($size == $setting_size) ? 'selected' : '';
                        echo "<option value='{$size}' {$selected}>{$size}</option>";
                    }
                    ?>
                </select>
                <select id="theme" onchange="editor.setTheme('ace/theme/' + this.value);">
                    <?php
                    $themes = [
                        'chrome' => 'Chrome',
                        'clouds' => 'Clouds',
                        'crimson_editor' => 'Crimson Editor',
                        'dawn' => 'Dawn',
                        'dreamweaver' => 'Dreamweaver',
                        'eclipse' => 'Eclipse',
                        'github' => 'GitHub',
                        'iplastic' => 'IPlastic',
                        'solarized_light' => 'Solarized Light',
                        'textmate' => 'TextMate',
                        'tomorrow' => 'Tomorrow',
                        'xcode' => 'XCode',
                        'ambiance' => 'Ambiance',
                        'chaos' => 'Chaos',
                        'clouds_midnight' => 'Clouds Midnight',
                        'cobalt' => 'Cobalt',
                        'idle_fingers' => 'idle Fingers',
                        'kr_theme' => 'krTheme',
                        'merbivore' => 'Merbivore',
                        'monokai' => 'Monokai',
                        'pastel_on_dark' => 'Pastel on dark',
                        'solarized_dark' => 'Solarized Dark',
                        'terminal' => 'Terminal',
                        'tomorrow_night' => 'Tomorrow Night',
                        'tomorrow_night_blue' => 'Tomorrow Night Blue',
                        'tomorrow_night_bright' => 'Tomorrow Night Bright',
                        'tomorrow_night_eighties' => 'Tomorrow Night 80s',
                        'twilight' => 'Twilight',
                        'vibrant_ink' => 'Vibrant Ink'
                    ];
                    foreach ($themes as $value => $label) {
                        $selected = (strtolower($label) == strtolower($setting_theme)) ? 'selected' : '';
                        echo "<option value='{$value}' {$selected}>{$label}</option>";
                    }
                    ?>
                </select>
            </div>
        </div>
        <!-- Editor Body -->
        <div id="editor-body">
            <div id="editor"></div>
        </div>
        <!-- Editor Status Bar -->
        <div id="editor-status">
            <span id="status-message">&nbsp;</span>
			<span id="status-filepath">New File</span>
			<div id="cursor-status-group">
				<span id="status-cursor-line" style="padding-left: 5px;">Line:</span>
				<span id="status-cursor-column">Column:</span>
			</div>
        </div>
        <!-- Hidden Form for Saving (if needed) -->
        <form id="frm_edit" method="post" action="file_save.php" onsubmit="return submit_check();" style="display:none;">
            <textarea name="content" id="editor_source"></textarea>
            <input type="hidden" name="filepath" id="filepath" value="">
            <input type="hidden" name="token" id="token" value="<?= $_SESSION['token'] ?>">
            <input type="hidden" name="mode" value="<?= $mode ?>">
        </form>
    </div>
</div>

<script>
	// Initialize Ace Editor
	var editor = ace.edit("editor");

	// Status Bar
	const status_message = document.getElementById('status-message');
	const status_filepath = document.getElementById('status-filepath');

	// Multiple file handler
	var loadedFiles = [];

	// Track modified files
	let isModified = false;

    // Basic functions for form submission and saving
    function submit_check() {
        if (document.getElementById('filepath').value !== '') {
            document.getElementById('editor_source').value = editor.getSession().getValue();
            return true;
        }
        editor.focus();
        return false;
    }

	function getOptions() {
		// Set Editor Options
		return {
			mode: 'ace/mode/<?php echo $mode; ?>',
			theme: 'ace/theme/' + document.getElementById('theme').value,
			selectionStyle: 'text',
			cursorStyle: 'smooth',
			showInvisibles: <?php echo $setting_invisibles; ?>,
			displayIndentGuides: <?php echo $setting_indenting; ?>,
			showLineNumbers: <?php echo $setting_numbering; ?>,
			showGutter: true,
			scrollPastEnd: true,
			fadeFoldWidgets: <?php echo $setting_numbering; ?>,
			showPrintMargin: false,
			highlightGutterLine: false,
			useSoftTabs: false,
			enableBasicAutocompletion: true,
			enableLiveAutocompletion: <?php echo ($mode === 'php') ? 'true' : 'false'; ?>,
			enableSnippets: true
		};
	}

    function save() {
        let formData = new FormData();
        formData.append('filepath', document.getElementById('filepath').value);
        formData.append('content', editor.getSession().getValue());
        formData.append('token', document.getElementById('token').value);
        formData.append('mode', "<?php echo $mode; ?>");

        let xhr = new XMLHttpRequest();
        xhr.open('POST', 'file_save.php', true);
        xhr.onload = function () {
            if (xhr.status === 200) {
				let file = getActiveFile();
				if (file) {
					file.originalContent = editor.getSession().getValue();
					file.session.message = "File Saved.";
					status_message.innerHTML = "File Saved.";
					file.session.tab.innerText = file.fileName;
				}
            } else {
                alert("<?php echo $text['message-problem']; ?>");
            }
        };
        xhr.send(formData);
    }

    // Sidebar: Load File List and Clip List
    async function loadFileList() {
        try {
            const response = await fetch('file_list.php');
            if (!response.ok) throw new Error('Network response not ok');
            const html = await response.text();
            document.getElementById('file_list').innerHTML = html;
        } catch (error) {
            console.error('Error fetching file list:', error);
        }
    }
    async function loadClipList() {
        try {
            const response = await fetch('clip_list.php');
            if (!response.ok) throw new Error('Network response not ok');
            const html = await response.text();
            document.getElementById('clip_list').innerHTML = html;
        } catch (error) {
            console.error('Error fetching clip list:', error);
        }
    }

	// Helper function to return the path of the active file
	function getActiveFile() {
		const currentPath = document.getElementById('filepath').value;
		return loadedFiles.find(file => file.filePath === currentPath);
	}

    // File Tabs Management
    function addTab(filePath, content) {
        var fileName = filePath.split('/').pop();
        // If the file is already loaded, activate it.
        if (loadedFiles.some(file => file.filePath === filePath)) {
            activateTab(filePath);
            return;
        }
		var session = ace.createEditSession(content);
		session.setMode("ace/mode/<?= $mode ?>");

		//set the undo state so we know where the file is unchanged
		//editor.setSession(session);
		session.getUndoManager().markClean();

		//track by the filePath
		session.id = filePath;
		session.message = "Read " + content.length + " bytes";

		loadedFiles.push({filePath: filePath, fileName: fileName, session: session});
        var tab = document.createElement('li');
        tab.setAttribute('data-file', filePath);
        tab.innerText = fileName;
        tab.onclick = () => activateTab(filePath);

		//save the tab and filename
		session.tab = tab;
		session.fileName = fileName;

        // Close button for the tab.
        var closeBtn = document.createElement('span');
        closeBtn.innerText = " x";
        closeBtn.style.marginLeft = "5px";
        closeBtn.style.cursor = "pointer";
        closeBtn.onclick = function (e) {
            e.stopPropagation();
            closeTab(filePath);
        };
        tab.appendChild(closeBtn);
        document.getElementById('fileTabs').appendChild(tab);
		activateTab(filePath);
		//var file = loadedFiles.find(f => f.filePath === filePath);
		//status_message.innerHTML = "Read " + file.content.length + " bytes";
    }

	function updateCursorStatus() {
		const cursor = editor.selection.getCursor();
		document.getElementById('status-cursor-line').innerText = "Line: " + (cursor.row + 1);
		document.getElementById('status-cursor-column').innerText = "Column: " + (cursor.column + 1);
	}

	// Switch to another tab
	function activateTab(filePath) {
		document.querySelectorAll('#fileTabs li').forEach(tab => tab.classList.remove('active'));
        var activeTab = document.querySelector('#fileTabs li[data-file="' + filePath + '"]');
        if (activeTab) activeTab.classList.add('active');
        var file = loadedFiles.find(f => f.filePath === filePath);
        if (file) {
			//switch the editor to the session
			editor.setSession(file.session);

			//add a listener
			editor.getSession().on('change', function(delta) {
				const file = getActiveFile();
				if (file) {
					if (editor.getSession().getUndoManager().isClean()) {
						status_message.innerHTML = "";
						status_filepath.innerHTML = "New File";
					} else {
						file.session.message = "Modified";
						status_message.innerHTML = "Modified";
						file.session.tab.innerText = "*" + file.fileName;
					}
					//updateCursorStatus();
				} else {
					status_message.innerHTML = "";
					status_filepath.innerHTML = "New File";
				}
			});

			//add a cursor position listener
			editor.selection.on('changeCursor', updateCursorStatus);

			//set filepath
            document.getElementById('filepath').value = file.filePath;

			//show status
			status_message.innerHTML = file.session.message;
			status_filepath.innerHTML = file.filePath;
        } else {
			status_message.innerHTML = "";
			status_filepath.innerHTML = "New File";
		}
    }

	// Close the tab and remove it
	// TODO: set a dialog if the isModified is still set
    function closeTab(filePath) {
        loadedFiles = loadedFiles.filter(file => file.filePath !== filePath);
        var tab = document.querySelector('#fileTabs li[data-file="' + filePath + '"]');
        if (tab) tab.parentNode.removeChild(tab);
        if (loadedFiles.length > 0) {
            activateTab(loadedFiles[loadedFiles.length - 1].filePath);
        } else {
            editor.getSession().setValue('');
            document.getElementById('filepath').value = '';
			status_message.innerHTML = "";
			status_filepath.innerHTML = "";
        }
    }

	// Function to load a file and add it as a new tab.
	function loadFileTab(filePath) {
		fetch('file_read.php', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded'
			},
			body: 'file=' + encodeURIComponent(filePath)
		})
			.then(response => {
				if (!response.ok) {
					throw new Error("Error loading file: " + filePath);
				}
				return response.text();
			})
			.then(content => {
				// Once you have the content, call the tab management function.
				addTab(filePath, content);
			})
			.catch(error => {
				console.error('Error loading file: ', error);
			});
	}

    // Ace Autocompletion
    async function loadAndRegisterCompletions() {
        try {
            const response = await fetch("resources/get_php_methods.php");
            if (!response.ok) throw new Error("Failed to load completions");
            const data = await response.json();
            const completions = [];

            // Process global functions.
            data.functions.forEach(fn => {
                const snippet = fn.name + "(" + "$0" + ")";
                completions.push({
                    caption: fn.name + fn.params,
                    value: fn.name,
                    meta: fn.meta || "function",
                    docHTML: '<b>' + fn.name + '</b>' + fn.params + '<br/>' + fn.doc,
                    snippet: snippet
                });
            });
            // Process class methods.
            for (const className in data.classes) {
                if (data.classes.hasOwnProperty(className)) {
                    data.classes[className].forEach(method => {
                        const displayName = className + (method.static ? "::" : "->") + method.name;
                        const snippet = displayName + "(" + "$0" + ")";
                        completions.push({
                            caption: displayName + method.params,
                            value: displayName,
                            meta: method.meta || "method",
                            docHTML: '<b>' + displayName + '</b>' + method.params + '<br/>' + method.doc,
                            snippet: snippet
                        });
                    });
                }
            }
			// Map the custom completion to the editor
            const customCompleter = {
                getCompletions: function(editor, session, pos, prefix, callback) {
                    let line = session.getLine(pos.row).substring(0, pos.column);
                    let filtered = [];
                    const staticMatch = line.match(/(?:\$)?(\w+)\s*::\s*$/);
                    const instanceMatch = line.match(/(?:\$)?(\w+)\s*->\s*$/);
                    if (staticMatch) {
                        const targetClass = staticMatch[1].toLowerCase();
                        filtered = completions.filter(item => item.value.toLowerCase().startsWith(targetClass + "::"));
                    } else if (instanceMatch) {
                        const targetClass = instanceMatch[1].toLowerCase();
                        filtered = completions.filter(item => {
                            let val = item.value.toLowerCase();
                            return val.startsWith(targetClass + "->") || val.startsWith(targetClass + "::");
                        });
                    } else {
                        filtered = completions.filter(item =>
                            item.value.toLowerCase().includes(prefix.toLowerCase()) ||
                            item.caption.toLowerCase().includes(prefix.toLowerCase())
                        );
                    }
                    filtered.sort((a, b) => a.caption.localeCompare(b.caption));
                    callback(null, filtered);
                },
                insertMatch: function(editor, data) {
                    if (data.snippet) {
                        const SnippetManager = ace.require("ace/snippets").snippetManager;
                        SnippetManager.insertSnippet(editor, data.snippet);
                    } else {
                        editor.insert(data.value);
                    }
                }
            };
			// Get the language tools
            const langTools = ace.require("ace/ext/language_tools");
			// Set the completer to use only our version
            langTools.setCompleters([customCompleter]);
        } catch (error) {
            console.error("Error loading completions:", error);
        }
    }

	// Call the async functions
    loadFileList();
    loadClipList();
    loadAndRegisterCompletions();

	// Setup the editor styling
	editor.setOptions(getOptions());
	document.getElementById('editor').style.fontSize = document.getElementById('size').value;

	// Setup the editor hotkey CTRL+S
	<?php key_press('ctrl+s', 'down', 'window', null, null, "save(); return false;", false); ?>

	// Prevent form submission with Enter key
	<?php key_press('enter', 'down', '#current_file', null, null, 'return false;', false); ?>

	// Open file manager/clip library pane with Ctrl+Q
	<?php key_press('ctrl+q', 'down', 'window', null, null, 'toggle_sidebar(); focus_editor(); return false;', false); ?>

	// ---------------------------------------------
	// --- http://www.codeproject.com/jscript/dhtml_treeview.asp
	// --- Name:    Easy DHTML Treeview           --
	// --- Author:  D.D. de Kerf                  --
	// --- Version: 0.2          Date: 13-6-2001  --
	// ---------------------------------------------
	function Toggle(node) {
		// Unfold the branch if it isn't visible
		if (node.nextSibling.style.display === 'none') {
			node.nextSibling.style.display = 'block';
		} else {
		// Collapse the branch if it IS visible
			node.nextSibling.style.display = 'none';
		}
	}
</script>
</body>
</html>

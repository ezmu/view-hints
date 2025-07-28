<?php

namespace Ezmu\ViewHints;

use Illuminate\Support\Str;
use Illuminate\View\Engines\CompilerEngine;

class HintedCompilerEngine extends CompilerEngine {

    public function get($path, array $data = []) {
        if (request()->has('templatehints')) {

            // Clean previous temp hinted blade files
            foreach (glob(storage_path('framework/views/__hinted_*.blade.php')) as $temp) {
                @unlink($temp);
            }

            // Build hint content (escaped path)
            $hint = e($path) . "\n";

            $originalContent = file_get_contents($path);
            $tempPath = storage_path('framework/views/__hinted_' . md5($path) . '.blade.php');

            // Wrap original content with a div showing path + editor modals
            $wrappedContent = "<div class='template-hint' style='border:1px dashed red;padding:3px;font-size:12px;margin:5px 0;'>"
                    . nl2br($hint) . $this->renderEditorModal($path, $data) . "\n"
                    . $originalContent . "</div>";
            file_put_contents($tempPath, $wrappedContent);

            $path = $tempPath;
        }

        return parent::get($path, $data);
    }
  
    protected function renderEditorModal($path, $data) {
        $filePath = realpath($path);
        $originalCode = htmlspecialchars(file_get_contents($filePath));

        $relativePath = str_replace(base_path() . '/', '', $filePath);
        $data = $data;
        $id = 'hint-' . md5($relativePath);
        $jsonData = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $bladeLink = "vscode://file/{$filePath}";
        $burl = url("");
        $csrf = csrf_token();
        $hintshtml = "";

        // Inject simple Blade editor modal once
        if (!defined('VIEW_HINT_EDITOR_INJECTED')) {
            $hintshtml .= $this->loadSimpleEditor($filePath);
        }

        // Inject GrapesJS editor modal once
        if (!defined('VIEW_HINT_GRAPESJS_INJECTED')) {
            define('VIEW_HINT_GRAPESJS_INJECTED', true);
            $hintshtml .= <<<HTML

<!-- GrapesJS + Plugins -->
<script src="https://unpkg.com/grapesjs"></script>
<script src="https://unpkg.com/grapesjs-preset-webpage"></script>
<script src="https://unpkg.com/grapesjs-blocks-basic"></script>
<script src="https://unpkg.com/grapesjs-plugin-forms"></script>
<script src="https://unpkg.com/grapesjs-navbar"></script>
<script src="https://unpkg.com/grapesjs-tooltip"></script>
<script src="https://unpkg.com/grapesjs-tabs"></script>
<script src="https://unpkg.com/grapesjs-custom-code"></script>
<script src="https://unpkg.com/grapesjs-touch"></script>
<script src="https://unpkg.com/grapesjs-parser-postcss"></script>
<script src="https://unpkg.com/grapesjs-tui-image-editor"></script>
<script src="https://unpkg.com/grapesjs-typed"></script>
<script src="https://unpkg.com/grapesjs-style-bg"></script>
<script src="https://cdn.jsdelivr.net/npm/js-beautify@1.14.7/js/lib/beautify-html.js"></script>
<style>
#grapesjs-editor-modal {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.7);
    z-index: 99999;
}
#grapesjs-editor-modal .editor-wrapper {
    background: #fff;
    width: 90vw;
    height: 90vh;
    margin: 5vh auto;
    display: flex;
    flex-direction: column;
    border-radius: 8px;
    overflow: hidden;
}
.editor-toolbar {
    padding: 10px;
    background: #f2f2f2;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.editor-body {
    flex: 1;
    display: flex;
}
#gjs {
    flex: 3;
    border-right: 1px solid #ddd;
}
#code-editor {
    flex: 1;
    padding: 10px;
    font-family: monospace;
    font-size: 14px;
    border-left: 1px solid #ccc;
    background: #f9f9f9;
    white-space: pre-wrap;
    overflow: auto;overflow: scroll;
    max-height: 500px;
}
.editor-footer {
    padding: 10px;
    background: #f2f2f2;
    text-align: right;
}
.btn-editor {
    padding: 6px 12px;
    margin-left: 10px;
    border: none;
    border-radius: 4px;
    font-weight: bold;
    cursor: pointer;
}
.btn-save { background: #28a745; color: white; }
.btn-close { background: #dc3545; color: white; }
</style>

<div id="grapesjs-editor-modal">
    <div class="editor-wrapper">
        <div class="editor-toolbar">
            <strong>GrapesJS Editor</strong>
            <button class="btn-editor btn-close" onclick="closeGrapesModal()">Close</button>
            <div class="editor-footer">
                <button class="btn-editor btn-save" onclick="saveGrapesContent()">Save</button>
            </div>
        </div>
        <div class="editor-body">
            <div id="gjs"></div>
            <textarea id="code-editor" style="min-width:300px"></textarea>
            <script>
              const textarea1 = document.getElementById('code-editor');

              const editor1 = CodeMirror.fromTextArea(textarea1, {
                mode: 'php',
                theme: 'dracula',
                lineNumbers: true,
                matchBrackets: true,
                autofocus: true,
                lineWrapping: false,
                viewportMargin: Infinity,
                extraKeys: {
                    "Ctrl-F": "findPersistent",
                    "Ctrl-H": "replace",
                    "Ctrl-S": function () {
                        saveBladeFile();
                    },
                    "Ctrl-/": "toggleComment",
                }
              });
            </script>
        </div>
    </div>
</div>

<script>
let lastBladePath = null;
const baseUrl = '{$burl}';
let typingTimer = null;

function closeGrapesModal() {
    document.getElementById('grapesjs-editor-modal').style.display = 'none';
    if (window.editor) window.editor.destroy();
}

function saveGrapesContent() {
    let htmlToSave = window.editor.getHtml();
    let css = window.editor.getCss();
  
    if (!lastBladePath) return alert("No file loaded");

    htmlToSave = htmlToSave.replace(/data-blade-([\w-]+)="(.*?)"/g, function(match, attr, val) {
        val = val.replace(/"/g, "'");
        return "data-blade-" + attr + "=\"" + val + "\"";
    });

    htmlToSave = decodeHTMLEntities(htmlToSave);
    fetch(baseUrl + '/dev/save-blade', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector("meta[name='csrf-token']").content
        },

        body: JSON.stringify({ path: lastBladePath, content: htmlToSave + "<style>" + css + "</style>" })
    })
    .then(res => res.json())
    .then(resp => alert(resp.message || 'Saved'))
    .catch(err => alert('Save failed: ' + err.message));
}

function decodeHTMLEntities(str) {
    const textarea = document.createElement('textarea');
    textarea.innerHTML = str;
    return textarea.value;
}

document.addEventListener('click', function(e) {
    if (e.target.classList.contains('template-grapesjs-btn')) {
        e.preventDefault();
        const bladePath = e.target.getAttribute('data-blade');
        if (!bladePath) return alert('Blade path missing');
        lastBladePath = bladePath;

        fetch(baseUrl + '/dev/load-blade?path=' + encodeURIComponent(bladePath))
            .then(res => res.text())
            .then(html => {
                document.getElementById('code-editor').innerText = html;
                document.getElementById('grapesjs-editor-modal').style.display = 'block';

                if (window.editor) window.editor.destroy();

                window.editor = grapesjs.init({
                    container: '#gjs',
                    height: '100%',
                    fromElement: false,
                    storageManager: false,
                    components: html,
                    beautifyHtml: false,
                    plugins: [
                        'gjs-blocks-basic',
                        'gjs-preset-webpage',
                        'grapesjs-plugin-forms',
                        'grapesjs-navbar',
                        'grapesjs-tooltip',
                        'grapesjs-tabs',
                        'grapesjs-custom-code',
                        'grapesjs-touch',
                        'grapesjs-parser-postcss',
                        'grapesjs-tui-image-editor',
                        'grapesjs-typed',
                        'grapesjs-style-bg',
                    ]
                });

                // Sync GrapesJS → code editor textarea
                window.editor.on('change:changesCount', () => {
                    const content = window.editor.getHtml() + "<style>" + window.editor.getCss() + "</style>";
                    document.getElementById('code-editor').innerText = content;
                });
            })
            .catch(err => {
                alert(err.message || "Failed to load file");
                closeGrapesModal();
            });
    }
});

// Ctrl+S to save in GrapesJS editor
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault();
        saveGrapesContent();
    }
});

// Sync code editor textarea → GrapesJS components
document.getElementById('code-editor').addEventListener('input', () => {
    clearTimeout(typingTimer);
    typingTimer = setTimeout(() => {
        const html = document.getElementById('code-editor').innerText;
        if (!window.editor) return;
        window.editor.setComponents(html);
    }, 1000);
});

// Click on code editor to select element in GrapesJS
document.getElementById('code-editor').addEventListener('click', (e) => {
    const selection = window.getSelection();
    if (!selection.rangeCount) return;
    const selectedText = selection.toString().trim();
    if (!selectedText.startsWith('<')) return;

    try {
        const parser = new DOMParser();
        const doc = parser.parseFromString(selectedText, 'text/html');
        const el = doc.body.firstElementChild;
        if (!el) return;

        const tag = el.tagName.toLowerCase();
        const id = el.getAttribute('id');
        const cls = el.getAttribute('class');

        const all = window.editor.DomComponents.getWrapper().find('*');
        const found = all.find(comp =>
            comp.get('tagName') === tag &&
            (!id || comp.getId() === id) &&
            (!cls || comp.getClasses().includes(cls))
        );
        if (found) {
            window.editor.select(found);
        }
    } catch (e) {
        console.warn('Click match failed', e);
    }
});
</script>
HTML;
        }

        // Basic styling and script injection for template hints variables
        $hintshtml .= <<<HTML
<style>
.template-hint { border: 1px solid #bbb; background: #fcfcfc; padding: 10px; margin: 10px 0; font-family: monospace; font-size: 13px; color: #333; }
.template-hint h4 { margin: 0 0 8px 0; font-size: 14px; }
.template-hint table { border-collapse: collapse; width: 100%; font-size: 12px; margin-top: 5px }
.template-hint th, .template-hint td { border: 1px solid #ccc; padding: 4px; text-align: left; vertical-align: top }
.template-hint thead th { background: #f0f0f0; position: sticky; top: 0; z-index: 2 }
.template-hint .template-hint-toggle { padding: 4px 8px; font-size: 12px; background: #eee; border: 1px solid #aaa; cursor: pointer; margin-bottom: 5px; display: inline-block; border-radius: 4px; }
.template-hint .template-hint-search { margin: 8px 0; }
.template-hint .search-input { width: 100%; padding: 5px 8px; border: 1px solid #ccc; border-radius: 4px; font-family: monospace; font-size: 13px; }
.template-hint .template-hint-scroll { max-height: 400px; overflow-y: auto; border: 1px solid #ddd; }
.template-hint tbody tr.hidden { display: none; }
.sub-table { margin-left: 20px; border-left: 3px solid #ccc; }
.d-none { display: none; }
.template-hint table {
  border-collapse: collapse;
  width: 100%;
  font-size: 12px;
  margin-top: 5px;
  display: contents;
}
</style>
HTML;

        if (!defined('VIEW_HINT_SCRIPT_INJECTED')) {
            define('VIEW_HINT_SCRIPT_INJECTED', true);
            $hintshtml .= <<<HTML
<script>
function filterHintTable(input) {
    const filter = input.value.toLowerCase();
    const rows = input.closest('.template-hint').querySelectorAll('tbody tr');
    rows.forEach(row => {
        const text = row.innerText.toLowerCase();
        row.classList.toggle('hidden', !text.includes(filter));
    });
}
function exportHintJSON(id) {
    const jsonTextarea = document.getElementById('jsonData-' + id);
    if (!jsonTextarea) return alert('No data to export');
    const dataStr = jsonTextarea.value;
    const blob = new Blob([dataStr], {type: "application/json"});
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = id + "-variables.json";
    a.click();
    URL.revokeObjectURL(url);
}
</script>
HTML;
        }

        $relativePath = Str::after($filePath, base_path() . '/');
        $escapedPath = htmlspecialchars($relativePath, ENT_QUOTES);
        $editBtn = <<<HTML
<span class="template-edit-btn" data-blade="{$relativePath}" style="cursor:pointer; font-size:10px; color:#007bff;">📝Edit Blade</span>
&nbsp;&nbsp;
<span class="template-grapesjs-btn" data-blade="{$relativePath}" style="cursor:pointer; font-size:10px; color:#28a745;">🎨Edit GrapesJS</span>
HTML;

        $hintshtml .= <<<HTML
<div class="template-hint"> {$editBtn}
    <div class="template-hint-toggle" onclick="document.getElementById('{$id}').classList.toggle('d-none')">
        🔍 View Variables for: <a href="{$bladeLink}" target="_blank" style="text-decoration:none;">{$relativePath}</a>
    </div>
    <div class="d-none" id="{$id}">
        <div class="template-hint-search">
            <input type="text" class="search-input" placeholder="🔍 Filter variables..." onkeyup="filterHintTable(this)">
        </div>
        <div style="margin-bottom: 8px;">
            <button onclick="exportHintJSON('{$id}')" style="padding:4px 8px; cursor:pointer;">💾 Export JSON</button>
        </div>
        <div class="template-hint-scroll">
            {$this->renderVariableTable($data)}
        </div>
        <textarea id="jsonData-{$id}" style="display:none;">{$jsonData}</textarea>
    </div>
</div>
HTML;
        return $hintshtml;
    }

    private function isListOfObjects(array $array): bool {
        return !empty($array) && is_object(reset($array));
    }

    private function listToArray(array $list): array {
        return array_map(function ($item) {
            if (is_object($item)) {
                return method_exists($item, 'toArray') ? $item->toArray() : get_object_vars($item);
            }
            return ['value' => $item, 'type'  => gettype($item)];
        }, $list);
    }

    private function getClassFilePath(string $class): ?string {
        try {
            $reflector = new \ReflectionClass($class);
            return $reflector->getFileName();
        } catch (\ReflectionException $e) {
            return null;
        }
    }

    static function loadSimpleEditor() {
 
        $burl = url("");
        $csrf = csrf_token();
  define("VIEW_HINT_EDITOR_INJECTED", true);
        return <<<HTML
             <meta name="csrf-token" content="{$csrf}">

<!-- GrapesJS CSS -->
<link href="https://unpkg.com/grapesjs/dist/css/grapes.min.css" rel="stylesheet" />
<!-- CodeMirror Styles & Scripts -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/codemirror.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/theme/dracula.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/codemirror.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/mode/htmlmixed/htmlmixed.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/mode/xml/xml.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/mode/javascript/javascript.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/mode/css/css.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/mode/clike/clike.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/mode/php/php.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/addon/search/search.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/addon/search/searchcursor.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/addon/dialog/dialog.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/addon/dialog/dialog.min.css">
<style>
    .CodeMirror {
        height: 500px;
        max-height: 80vh;
        border: 1px solid #ccc;
        font-size: 14px;
    }
    #blade-editor-modal {
        display: none;
        position: fixed;
        top: 10%;
        left: 50%;
        transform: translateX(-50%);
        z-index: 9999;
        background: white;
        border: 1px solid #ccc;
        box-shadow: 0 0 10px rgba(0,0,0,.2);
        width: 80%;
        padding: 10px;
    }
</style>

<!-- Blade Editor Modal -->
<div id="blade-editor-modal">
    <h4>Edit Blade File: <span id="blade-editor-path"></span></h4>
    <textarea id="blade-editor-content" style="display:none; height: 500px;"></textarea>
    <div style="margin-top:10px; text-align:right;">
        <button onclick="saveBladeFile()">💾 Save</button>
        <button onclick="document.getElementById('blade-editor-modal').style.display = 'none'">❌ Cancel</button>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const baseUrl = location.origin;
    const textarea = document.getElementById('blade-editor-content');
    const pathEl = document.getElementById('blade-editor-path');
    const modal = document.getElementById('blade-editor-modal');

    const editor = CodeMirror.fromTextArea(textarea, {
        mode: 'php',
        theme: 'dracula',
        lineNumbers: true,
        matchBrackets: true,
        autofocus: true,
        lineWrapping: false,
        viewportMargin: Infinity,
        extraKeys: {
            "Ctrl-F": "findPersistent",
            "Ctrl-H": "replace",
            "Ctrl-S": function () {
                saveBladeFile();
            },
            "Ctrl-/": "toggleComment",
        }
    });

    window.bladeEditor = editor;

    document.querySelectorAll('.template-edit-btn').forEach(el => {
        el.addEventListener('click', () => {
            const file = el.getAttribute('data-blade');
            fetch(`{$burl}/dev/get-blade?path=` + encodeURIComponent(file))
                .then(res => res.json())
                .then(data => {
                    pathEl.innerText = file;
                    editor.setValue(data.content);
                    modal.style.display = 'block';
                    setTimeout(() => editor.refresh(), 50);
                })
                .catch(err => alert('Error loading file: ' + err));
        });
    });
    document.querySelectorAll('.template-edit-btn-backup').forEach(el => {
        el.addEventListener('click', () => {
            const file = el.getAttribute('data-blade');
            fetch(`{$burl}/dev/get-view-backup?path=` + encodeURIComponent(file))
                .then(res => res.json())
                .then(data => {
                    pathEl.innerText = file;
                    editor.setValue(data.content);
                    modal.style.display = 'block';
                    setTimeout(() => editor.refresh(), 50);
                })
                .catch(err => alert('Error loading file: ' + err));
        });
    });

    window.saveBladeFile = function () {
        const path = pathEl.innerText;
        const content = editor.getValue();
        fetch(`{$burl}/dev/save-blade`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || ''
            },
            body: JSON.stringify({ path, content })
        })
        .then(res => res.json())
        .then(res => {
            alert(res.message || 'Saved!');
            modal.style.display = 'none';
        })
        .catch(err => alert('Error saving blade: ' + err));
    };
});
</script>

HTML;
    }
    private function restoreBlocks(){
        
                        $realFile = $e->getFile();
                        foreach ($e->getTrace() as $frame) {
                            if (!empty($frame['file']) && str_ends_with($frame['file'], '.blade.php')) {
                                $realFile = $frame['file'];
                                break;
                            }
                        }

                        $relativePath = str_replace(base_path() . '/', '', $realFile);
                        $restoreHtml = '';
                        $editorUrl = '/dev/edit-blade?file=' . urlencode($relativePath);

                        // STEP 2: Find backup
                        $backupDir = storage_path('backups/blades/' . dirname($relativePath));
                        if (is_dir($backupDir)) {
                            $finder = Finder::create()
                                    ->in($backupDir)
                                    ->name(basename($relativePath) . '*.bak')
                                    ->sortByModifiedTime()
                                    ->reverseSorting();

                            foreach ($finder as $backupFile) {
                                $restoreHtml .= "<p>🗃️ Backup found: <code>{$backupFile->getFilename()}</code></p>
                                <form method='POST' action='/dev/restore-blade' style='display:inline;'>
                                    " . csrf_field() . "
                                    <input type='hidden' name='path' value='{$relativePath}'>
                                    <input type='hidden' name='backupPath' value='{$backupFile->getRealPath()}'>
                                    <button type='submit' style='background:red;color:white;padding:4px 8px;border:none;border-radius:5px;'>🔁 Restore</button>
                                </form>";
                                break;
                            }
                        }

                        // STEP 3: Return the custom UI
                        return "<div style='background:#fff0f0;border:2px dashed red;padding:15px;margin:15px;font-family:monospace;z-index:9999;'>
                        <h3>💥 Blade Error in: <code>{$relativePath}</code></h3>
                        {$restoreHtml}
                        <a href='{$editorUrl}' target='_blank' style='display:inline-block;margin-top:10px;padding:6px 10px;background:#333;color:#fff;border-radius:4px;text-decoration:none;'>✏️ Edit Blade</a>
                    </div>";
    }
    private function renderVariableTable($data, $level = 0) {
        
        $html = '<table class="' . ($level ? 'sub-table' : '') . '">';
        
        $html .= '<thead><tr><th>Variable</th><th>Type</th><th>Value</th></tr></thead><tbody>';

        foreach ($data as $key => $value) {
            $type = gettype($value);
            $safeKey = is_int($key) ? "[{$key}]" : "\${$key}";

            if (is_scalar($value) || is_null($value)) {
                $val = is_null($value) ? 'null' : htmlspecialchars((string) $value);
                $html .= "<tr><td>{$safeKey}</td><td>{$type}</td><td>{$val}</td></tr>";
            } elseif (is_array($value)) {
                if ($this->isListOfObjects($value)) {
                    $class = get_class(reset($value));
                    $filePath = $this->getClassFilePath($class);
                    $fileLink = $filePath ? "<a href=\"vscode://file/{$filePath}\" target=\"_blank\">{$class}</a>" : $class;
                    $html .= "<tr><td>{$safeKey}</td><td>Array of {$fileLink}</td><td>" .
                            $this->renderVariableTable($this->listToArray($value), $level + 1) . "</td></tr>";
                } else {
                    $html .= "<tr><td>{$safeKey}</td><td>{$type}</td><td>" .
                            $this->renderVariableTable($value, $level + 1) . "</td></tr>";
                }
            } elseif (is_object($value)) {
                $class = get_class($value);
                $filePath = $this->getClassFilePath($class);
                $fileLink = $filePath ? "<a href=\"vscode://file/{$filePath}\" target=\"_blank\">{$class}</a>" : $class;
                $objectData = method_exists($value, 'toArray') ? $value->toArray() : get_object_vars($value);
                $html .= "<tr><td>{$safeKey}</td><td>Object ({$fileLink})</td><td>" .
                        $this->renderVariableTable($objectData, $level + 1) . "</td></tr>";
            } else {
                $html .= "<tr><td>{$safeKey}</td><td>{$type}</td><td>Not displayable</td></tr>";
            }
        }

        $html .= '</tbody></table>';
        return $html;
    }

}

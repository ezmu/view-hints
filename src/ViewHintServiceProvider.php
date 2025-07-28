<?php

namespace Ezmu\ViewHints;
use Ezmu\ViewHints\HintedCompilerEngine;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\File;
use Illuminate\Http\Request;
use Illuminate\View\ViewException;
use Symfony\Component\Finder\Finder;
use Throwable;

class ViewHintServiceProvider extends ServiceProvider {

    public function register() {
        if (request()->query('templatehints')) {
   
        }
    }

    public function loadRoutes() {

        Route::middleware(['web'])->group(function () {


            Route::get('/dev/get-view-backup', function (Request $request) {
                $backupPath = $request->query('path');

                // Security: validate path is inside storage/backups/blades/
                $backupBase = storage_path('backups/blades');
                if (!str_contains($backupPath, '/backups/blades/') || !File::exists($backupPath)) {
                    abort(404, 'Backup file not found or invalid path.');
                }

                $content = File::get($backupPath);

                return response()->json(['content' => file_get_contents($backupPath)]);
            });
            Route::get('/dev/get-blade', function (Request $request) {

                $path = base_path($request->query('path'));
                if (!file_exists($path)) {
                    return response()->json(['error' => 'File not found'], 404);
                }
                return response()->json(['content' => file_get_contents($path)]);
            });

            Route::get('/dev/load-blade', function (Request $request) {

                function convertBladeAttrsToDataBlade(string $html): string {
                    return preg_replace_callback(
                            '/(\w+)="{{\s*(.*?)\s*}}"/',
                            function ($matches) {
                                $attr = $matches[1];
                                $expr = $matches[2];
                                return 'data-blade-' . $attr . '="' . htmlspecialchars($expr, ENT_QUOTES) . '"';
                            },
                            $html
                    );
                }

                $path = base_path($request->query('path'));
                if (!file_exists($path)) {
                    return response('File not found', 404);
                }
                $safe_blade = convertBladeAttrsToDataBlade(file_get_contents($path));
                return response($safe_blade)->header('Content-Type', 'text/html');
            });

            Route::post('/dev/save-blade', function (\Illuminate\Http\Request $request) {
                try {
                    $relativePath = $request->input('path');
                    $path = base_path($relativePath);

                    if (!File::exists($path)) {
                        return response()->json(['error' => 'File not found'], 404);
                    }

                    $backupDir = storage_path('backups/blades/' . dirname($relativePath));
                    File::ensureDirectoryExists($backupDir);

                    $timestamp = now()->format('Ymd_His');
                    $backupPath = $backupDir . '/' . basename($relativePath) . '.' . $timestamp . '.bak';

                    File::copy($path, $backupPath);

                    File::put($path, $request->input('content'));

                    return response()->json([
                                'message' => 'Blade file saved',
                                'backup' => $backupPath,
                    ]);
                } catch (\Throwable $e) {
                    return response()->json([
                                'error' => 'Server error',
                                'details' => $e->getMessage(),
                                    ], 500);
                }
            });

            Route::post('/dev/restore-blade', function (Request $request) {
                $backupPath = $request->input('backupPath');
                $path = base_path($request->input('path'));

                if (File::exists($backupPath)) {
                    File::copy($backupPath, $path);
                    return redirect()->back()->with('message', 'Restored from backup');
                }

                return back()->with('error', 'Backup file not found');
            });
        });
    }
private function renderException(){
      $handleBladeError = function (Throwable $e): string {
  
            $message = $e->getMessage();
            if (preg_match('/\(View:\s([^\)]+)\)/', $message, $matches)) {
                $realFile = $matches[1];
            } else {
                $realFile = $e->getFile();
            }

            if (str_contains($realFile, 'storage/framework/views/')) {
                $lines = @file($realFile);
                if ($lines) {
                    foreach ($lines as $line) {
                        if (preg_match('/compiled from: (.+\.blade\.php)/', $line, $matches)) {
                            $realFile = $matches[1];
                            break;
                        }
                    }
                }
            }

            $relativePath = str_replace(base_path() . '/', '', $realFile);
            $restoreHtml = '';
            $backupDir = storage_path('backups/blades/' . dirname($relativePath));

            if (is_dir($backupDir)) {
                $finder = Finder::create()
                    ->in($backupDir)
                    ->name(basename($relativePath) . '*.bak')
                    ->sortByModifiedTime()
                    ->reverseSorting();
                $burl = url('');
                foreach ($finder as $backupFile) {
                    $backupPath = $backupFile->getRealPath();
                    $restoreHtml .= "<p><i class='ti ti-database-export'></i>Backup found: <code>{$backupFile->getFilename()}</code>
                        &nbsp;
                        <span class='template-edit-btn-backup' data-blade='{$backupPath}' style='cursor:pointer; font-size:10px; color:#007bff;'><i class='ti ti-eye'></i>View Backup</span>
                        <form method='POST' action='{$burl}/dev/restore-blade' style='display:inline;'>
                            " . csrf_field() . "
                            <input type='hidden' name='path' value='{$relativePath}'>
                            <input type='hidden' name='backupPath' value='{$backupFile->getRealPath()}'>
                            <button type='submit' style='background:red;color:white;padding:4px 8px;border:none;border-radius:5px;'><i class='ti ti-database-export'></i>Restore</button>
                        </form></p>";
                    break;
                }
            }

            $editBtn = "<span class='template-edit-btn' data-blade='{$relativePath}' style='cursor:pointer; font-size:10px; color:#007bff;'><i class='ti ti-pencil'></i>Edit Blade</span>&nbsp;&nbsp;";

            return "<div style='background:#fff0f0;border:2px dashed red;padding:15px;margin:15px;font-family:monospace;z-index:9999;'>
                <h3><i class='ti ti-alert-circle'></i>Blade Error in: <code>{$relativePath}</code></h3>
                {$restoreHtml}
                {$editBtn}
            </div>";
        };
        
                $this->app['Illuminate\Contracts\Debug\ExceptionHandler']->renderable(function (ViewException $e, $request) use ($handleBladeError) {
       
                $html = $handleBladeError($e);
                $response = response($this->loadExEditor().$html . $e->getPrevious()->getMessage(), 500);

                
                return $response;
            
        });
}

    public function loadExEditor() {
        
     
        $burl = url("");
        $csrf = csrf_token();
  define("VIEW_HINT_EDITOR_INJECTED", true);
        return <<<HTML
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
<!-- GrapesJS + Plugins -->
             <meta name="csrf-token" content="{$csrf}">


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
    <h4><i class="ti ti-pencil"></i>Edit Blade File: <span id="blade-editor-path"></span></h4>
    <textarea id="blade-editor-content" style="display:none; height: 500px;"></textarea>
    <div style="margin-top:10px; text-align:right;">
        <button onclick="saveBladeFile()"><i class="ti ti-device-floppy"></i>Save</button>
        <button onclick="document.getElementById('blade-editor-modal').style.display = 'none'"><i class="ti ti-x"></i>Cancel</button>
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
    public function boot() {
    if (!config('app.debug') 
     
            ) {
        return;
    }
        $this->loadRoutes();
           $this->renderException();
        if (request()->has('templatehints')) {

         

            $this->app->extend('view.engine.resolver', function ($resolver, $app) {

                $bladeCompiler = $app['blade.compiler'];

                $resolver->register('blade', function () use ($bladeCompiler) {

                    return new HintedCompilerEngine($bladeCompiler);
                });
                return $resolver;
            });
        }
    }

}

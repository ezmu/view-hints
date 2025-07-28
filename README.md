# Hints View for Laravel Blade

Hints View is a Laravel development tool that injects helpful Blade debugging and editing features directly into your application views. It is designed to speed up UI development and debugging by making Blade file structures and variables transparent and editable.

---

## Features

### Blade Path Display
- Shows the full path of every rendered Blade file.
- Helps identify which Blade file or `@include` rendered a specific section.

### View Variable Inspector
- Lists all variables passed to each Blade view.
- Displays variable names, types, and values.
- Supports objects and arrays.
- Includes a live search and filter bar to quickly locate variables.

### Open in VSCode
- Every Blade path is clickable.
- Opens the corresponding file in VSCode using the `vscode://file/...` protocol.

### Inline Edit Mode
- Edit Blade files directly from the browser using a built-in code editor (CodeMirror).
- Changes are saved via AJAX.
- Automatically creates backups before saving.

### Visual Composer Editor
- Drag and drop HTML blocks into specific Blade sections.
- Insert cards, sliders, layout blocks visually.
- Save edited layout blocks to separate Blade partials.
- Supports live editing and layout customization inside the browser.
- Designed to speed up building complex UI layouts without leaving your Laravel app.

---

## Installation

1. Require the package using Composer (replace with your package name):

```bash
composer require ezmu/view-hints

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

2. Register the service provider in config/app.php or your custom providers array:

'providers' => [
    // Other service providers...
    Ezmu\ViewHints\ViewHintServiceProvider::class,
],


Usage
Enabling Hints with ?templatehints=1

To toggle the hints dynamically, append ?templatehints=1 to any URL while logged in as an admin user.

Example URL:

https://yourapp.test/dashboard?templatehints=1

The hints will only appear if all of the following are true:

    APP_DEBUG is true

    The URL contains ?templatehints=1

Requirements

    Laravel 9.x or higher

    PHP 8.x or higher


Contributing

Contributions, issues, and feature requests are welcome! Feel free to fork and submit pull requests.
License

MIT License
Author

EzEldeen A. Y. Mushtaha
GitHub: https://github.com/ezmu
LinkedIn: https://www.linkedin.com/in/ezmush/

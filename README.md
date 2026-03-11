# Glowie IDE Helper

This is a developer-tooling plugin for [Glowie Framework](https://github.com/glowieframework/glowie) that automatically generates and updates PHPDoc `@property` annotations for your DB models.

By default, magic properties in PHP models are "invisible" to IDEs like VS Code or PhpStorm. This plugin introspects your database schema, model casts, and relationships to inject accurate DocBlocks directly into your model files.

## Installation

Install in your Glowie project using Composer:

```shell
composer require glowieframework/ide-helper
```

Then add the **IdeHelper** class to the `plugins` array in the `app/config/Config.php` file:

```php
'plugins' => [
    // ... other plugins here
    \Glowie\Plugins\IdeHelper\IdeHelper::class,
],
```

## Usage

In your Glowie project, run in the terminal:

```shell
php firefly ide-helper:run
```

## Credits

IDE Helper and Glowie are actively developed by [Gabriel Silva](https://gabrielsilva.dev.br).

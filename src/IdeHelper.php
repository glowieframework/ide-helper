<?php

namespace Glowie\Plugins\IdeHelper;

use Glowie\Core\Plugin;
use Glowie\Core\CLI\Firefly;
use Glowie\Plugins\IdeHelper\Run;

/**
 * Glowie plugin for type-hinting models.
 * @category Plugin
 * @package glowieframework/ide-helper
 * @author Glowie
 * @copyright Copyright (c) Glowie
 * @license MIT
 * @link https://glowie.gabrielsilva.dev.br
 */
class IdeHelper extends Plugin
{

    /**
     * Initializes the plugin.
     */
    public function register()
    {
        Firefly::custom('ide-helper', Run::class);
    }
}

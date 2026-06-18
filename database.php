<?php
namespace Grav\Plugin;

use Composer\Autoload\ClassLoader;
use Grav\Common\Plugin;
use Grav\Plugin\Database\Database;

/**
 * Class DatabasePlugin
 * @package Grav\Plugin
 */
class DatabasePlugin extends Plugin
{
    /**
     * @return array
     *
     * The getSubscribedEvents() gives the core a list of events
     *     that the plugin wants to listen to. The key of each
     *     array section is the event that the plugin listens to
     *     and the value (in the form of an array) contains the
     *     callable (or function) as well as the priority. The
     *     higher the number the higher the priority.
     */
    public static function getSubscribedEvents()
    {
        return [
            'onCliInitialize' => [
                ['autoload', 100000],
                ['register', 1000]
            ],
            'onPluginsInitialized' => [
                ['autoload', 100000],
                ['register', 1000]
            ]
        ];
    }

    /**
     * [onPluginsInitialized:100000] Composer autoload.
     *
     * @return ClassLoader
     */
    public function autoload()
    {
        return require __DIR__ . '/vendor/autoload.php';
    }

    /**
     * Register the service and let third-party plugins register their own
     * database drivers.
     */
    public function register()
    {
        $this->grav['database'] = function () {
            return new Database();
        };

        // Mirrors the shortcode-core `onShortcodeHandlers` model: third-party
        // database engines subscribe to `onDatabaseDrivers` and call
        // $grav['database']->registerDriver(...). This only ever fires when the
        // database plugin is loaded and enabled.
        $this->grav->fireEvent('onDatabaseDrivers');
    }
}

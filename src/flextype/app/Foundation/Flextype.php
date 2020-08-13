<?php

declare(strict_types=1);

namespace Flextype\App\Foundation;

use Slim\App;
use Zeuxisoo\Whoops\Provider\Slim\WhoopsMiddleware;
use function date_default_timezone_set;
use function error_reporting;
use function file_exists;
use function function_exists;
use function mb_internal_encoding;
use function mb_language;
use function mb_regex_encoding;
use Bnf\Slim3Psr15\CallableResolver;
use Cocur\Slugify\Slugify;
use Flextype\App\Foundation\Cache\Cache;
use Flextype\App\Foundation\Cors;
use Flextype\App\Foundation\Entries\Entries;
use Flextype\App\Foundation\Media\MediaFiles;
use Flextype\App\Foundation\Media\MediaFilesMeta;
use Flextype\App\Foundation\Media\MediaFolders;
use Flextype\App\Foundation\Media\MediaFoldersMeta;
use Flextype\App\Foundation\Plugins;
use Flextype\App\Support\Parsers\Markdown;
use Flextype\App\Support\Parsers\Shortcode;
use Flextype\App\Support\Serializers\Frontmatter;
use Flextype\App\Support\Serializers\Json;
use Flextype\App\Support\Serializers\Yaml;
use Flextype\Component\Registry\Registry;
use Flextype\Component\Session\Session;
use Intervention\Image\ImageManager;
use League\Event\Emitter;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use League\Glide\Api\Api;
use League\Glide\Manipulators\Background;
use League\Glide\Manipulators\Blur;
use League\Glide\Manipulators\Border;
use League\Glide\Manipulators\Brightness;
use League\Glide\Manipulators\Contrast;
use League\Glide\Manipulators\Crop;
use League\Glide\Manipulators\Encode;
use League\Glide\Manipulators\Filter;
use League\Glide\Manipulators\Gamma;
use League\Glide\Manipulators\Orientation;
use League\Glide\Manipulators\Pixelate;
use League\Glide\Manipulators\Sharpen;
use League\Glide\Manipulators\Size;
use League\Glide\Manipulators\Watermark;
use League\Glide\Responses\SlimResponseFactory;
use League\Glide\ServerFactory;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use ParsedownExtra;
use Thunder\Shortcode\ShortcodeFacade;
use function date;
use function extension_loaded;


/**
 * The Singleton class defines the `GetInstance` method that serves as an
 * alternative to constructor and lets clients access the same instance of this
 * class over and over.
 */
class Flextype
{
    /**
     * The Singleton's instance is stored in a static field. This field is an
     * array, because we'll allow our Singleton to have subclasses. Each item in
     * this array will be an instance of a specific Singleton's subclass. You'll
     * see how this works in a moment.
     */
    private static $instances = [];

    private $registry;

    /**
     * The Singleton's constructor should always be private to prevent direct
     * construction calls with the `new` operator.
     */
    protected function __construct() {

        $this->startSession();
        $this->_initDraft();
    }

    /**
     * Start the session
     */
    protected function startSession()
    {
        Session::start();
    }

    public function _initDraft()
    {

        /**
         * Init Registry
         */
        $this->registry = new Registry();

        /**
         * Preflight the Flextype
         */
        include_once ROOT_DIR . '/src/flextype/preflight.php';

        /**
         * Create new application
         */
        $app = new App([
            'settings' => [
                'debug' => $this->registry->get('flextype.settings.errors.display'),
                'whoops.editor' => $this->registry->get('flextype.settings.whoops.editor'),
                'whoops.page_title' => $this->registry->get('flextype.settings.whoops.page_title'),
                'displayErrorDetails' => $this->registry->get('flextype.settings.display_error_details'),
                'addContentLengthHeader' => $this->registry->get('flextype.settings.add_content_length_header'),
                'routerCacheFile' => $this->registry->get('flextype.settings.router_cache_file'),
                'determineRouteBeforeAppMiddleware' => $this->registry->get('flextype.settings.determine_route_before_app_middleware'),
                'outputBuffering' => $this->registry->get('flextype.settings.output_buffering'),
                'responseChunkSize' => $this->registry->get('flextype.settings.response_chunk_size'),
                'httpVersion' => $this->registry->get('flextype.settings.http_version'),
            ],
        ]);

        $this->registerDependencies($app);

dd($this->registry->get('flextype.settings.charset'));
        /**
         * Set internal encoding
         */
        function_exists('mb_language') and mb_language('uni');
        function_exists('mb_regex_encoding') and mb_regex_encoding($this->registry->get('flextype.settings.charset'));
        function_exists('mb_internal_encoding') and mb_internal_encoding($this->registry->get('flextype.settings.charset'));

        /**
         * Display Errors
         */
        if ($this->registry->get('flextype.settings.errors.display')) {

            /**
             * Add WhoopsMiddleware
             */
            $app->add(new WhoopsMiddleware($app));
        } else {
            error_reporting(0);
        }

        /**
         * Set default timezone
         */
        date_default_timezone_set($this->registry->get('flextype.settings.timezone'));

        /**
         * Init shortocodes
         *
         * Load Flextype Shortcodes from directory /flextype/app/Support/Parsers/Shortcodes/ based on flextype.settings.shortcode.shortcodes array
         */
        $shortcodes = $this->registry->get('flextype.settings.shortcode.shortcodes');

        foreach ($shortcodes as $shortcode_name => $shortcode) {
            $shortcode_file_path = ROOT_DIR . '/src/flextype/app/Support/Parsers/Shortcodes/' . str_replace("_", '', ucwords($shortcode_name, "_")) . 'Shortcode.php';
            if (! file_exists($shortcode_file_path)) {
                continue;
            }

            include_once $shortcode_file_path;
        }

        /**
         * Init entries fields
         *
         * Load Flextype Entries fields from directory /flextype/app/Foundation/Entries/Fields/ based on flextype.settings.entries.fields array
         */
        $entry_fields = $this->registry->get('flextype.settings.entries.fields');

        foreach ($entry_fields as $field_name => $field) {
            $entry_field_file_path = ROOT_DIR . '/src/flextype/app/Foundation/Entries/Fields/' . str_replace("_", '', ucwords($field_name, "_")) . 'Field.php';
            if (! file_exists($entry_field_file_path)) {
                continue;
            }

            include_once $entry_field_file_path;
        }

        /**
         * Init plugins
         */
        $flextype['plugins']->init($flextype, $app);

        /**
         * Enable lazy CORS
         *
         * CORS (Cross-origin resource sharing) allows JavaScript web apps to make HTTP requests to other domains.
         * This is important for third party web apps using Flextype, as without CORS, a JavaScript app hosted on example.com
         * couldn't access our APIs because they're hosted on another.com which is a different domain.
         */
        $flextype['cors']->init();

        /**
         * Run application
         */
        $app->run();
    }

    protected function registerEndpoints($app) {
        include_once ROOT_DIR . '/src/flextype/app/Endpoints/Utils/errors.php';
        include_once ROOT_DIR . '/src/flextype/app/Endpoints/Utils/access.php';
        include_once ROOT_DIR . '/src/flextype/app/Endpoints/entries.php';
        include_once ROOT_DIR . '/src/flextype/app/Endpoints/registry.php';
        include_once ROOT_DIR . '/src/flextype/app/Endpoints/files.php';
        include_once ROOT_DIR . '/src/flextype/app/Endpoints/folders.php';
        include_once ROOT_DIR . '/src/flextype/app/Endpoints/images.php';
    }

    protected function registerDependencies($app)
    {
        /**
         * Get Flextype Dependency Injection Container
         */
        $flextype = $app->getContainer();

        $flextype['callableResolver'] = function ($flextype) {
            return new CallableResolver($flextype);
        };

        $registry = $this->registry;

        $this->registry = function () use ($registry) {
            return $registry;
        };

        $flextype['logger'] = function () {
            $logger = new Logger('flextype');
            $logger->pushHandler(new StreamHandler(PATH['logs'] . '/' . date('Y-m-d') . '.log'));

            return $logger;
        };

        $flextype['emitter'] = function () {
            return new Emitter();
        };

        $flextype['slugify'] = function ($flextype) {
            return new Slugify([
                'separator' => $this->registry->get('flextype.settings.slugify.separator'),
                'lowercase' => $this->registry->get('flextype.settings.slugify.lowercase'),
                'trim' => $this->registry->get('flextype.settings.slugify.trim'),
                'regexp' => $this->registry->get('flextype.settings.slugify.regexp'),
                'lowercase_after_regexp' => $this->registry->get('flextype.settings.slugify.lowercase_after_regexp'),
                'strip_tags' => $this->registry->get('flextype.settings.slugify.strip_tags'),
            ]);
        };

        $flextype['cache_adapter'] = function ($flextype) {
            $driver_name = $this->registry->get('flextype.settings.cache.driver');

            if (! $driver_name || $driver_name === 'auto') {
                if (extension_loaded('apcu')) {
                    $driver_name = 'apcu';
                } elseif (extension_loaded('wincache')) {
                    $driver_name = 'wincache';
                } else {
                    $driver_name = 'phparrayfile';
                }
            }

            $drivers_classes = [
                'apcu' => 'Apcu',
                'wincache' => 'WinCache',
                'phpfile' => 'PhpFile',
                'phparrayfile' => 'PhpArrayFile',
                'array' => 'Array',
                'filesystem' => 'Filesystem',
                'memcached' => 'Memcached',
                'redis' => 'Redis',
                'sqlite3' => 'SQLite3',
                'zenddatacache' => 'ZendDataCache',
            ];

            $class_name = $drivers_classes[$driver_name];

            $adapter = "Flextype\\App\\Foundation\\Cache\\{$class_name}CacheAdapter";

            return new $adapter($flextype);
        };

        $flextype['cache'] = function ($flextype) {
            return new Cache($flextype);
        };

        $flextype['shortcode'] = function ($flextype) {
            return new Shortcode($flextype, new ShortcodeFacade());
        };

        $flextype['markdown'] = function ($flextype) {
            return new Markdown($flextype, new ParsedownExtra());
        };

        $flextype['json'] = function ($flextype) {
            return new Json($flextype);
        };

        $flextype['yaml'] = function ($flextype) {
            return new Yaml($flextype);
        };

        $flextype['frontmatter'] = function ($flextype) {
            return new Frontmatter($flextype);
        };

        $flextype['images'] = function ($flextype) {

            // Get images settings
            $imagesSettings = $this->registry->get('flextype.settings.image');

            // Set source filesystem
            $source = new Filesystem(
                new Local(PATH['project'] . '/uploads/entries/')
            );

            // Set cache filesystem
            $cache = new Filesystem(
                new Local(PATH['cache'] . '/glide')
            );

            // Set watermarks filesystem
            $watermarks = new Filesystem(
                new Local(PATH['project'] . '/watermarks')
            );

            // Set image manager
            $imageManager = new ImageManager($imagesSettings);

            // Set manipulators
            $manipulators = [
                new Orientation(),
                new Crop(),
                new Size(2000*2000),
                new Brightness(),
                new Contrast(),
                new Gamma(),
                new Sharpen(),
                new Filter(),
                new Blur(),
                new Pixelate(),
                new Watermark($watermarks),
                new Background(),
                new Border(),
                new Encode(),
            ];

            // Set API
            $api = new Api($imageManager, $manipulators);

            // Setup Glide server
            return ServerFactory::create([
                'source' => $source,
                'cache' => $cache,
                'api' => $api,
                'response' => new SlimResponseFactory(),
            ]);
        };

        $flextype['entries'] = function ($flextype) {
            return new Entries($flextype);
        };

        $flextype['media_folders'] = function ($flextype) {
            return new MediaFolders($flextype);
        };

        $flextype['media_files'] = function ($flextype) {
            return new MediaFiles($flextype);
        };

        $flextype['media_folders_meta'] = function ($flextype) {
            return new MediaFoldersMeta($flextype);
        };

        $flextype['media_files_meta'] = function ($flextype) {
            return new MediaFilesMeta($flextype);
        };

        $flextype['plugins'] = function ($flextype) use ($app) {
            return new Plugins($flextype, $app);
        };

        $flextype['cors'] = function ($flextype) use ($app) {
            return new Cors($flextype, $app);
        };

    }

    /**
     * Singletons should not be cloneable.
     */
    protected function __clone() { }

    /**
     * Singletons should not be restorable from strings.
     */
    public function __wakeup()
    {
        throw new \Exception("Cannot unserialize a singleton.");
    }

    /**
     * This is the static method that controls the access to the singleton
     * instance. On the first run, it creates a singleton object and places it
     * into the static field. On subsequent runs, it returns the client existing
     * object stored in the static field.
     *
     * This implementation lets you subclass the Singleton class while keeping
     * just one instance of each subclass around.
     */
    public static function getInstance(): Flextype
    {
        $cls = static::class;
        if (!isset(self::$instances[$cls])) {
            self::$instances[$cls] = new static;
        }

        return self::$instances[$cls];
    }

}

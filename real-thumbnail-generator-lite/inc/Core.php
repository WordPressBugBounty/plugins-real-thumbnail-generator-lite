<?php

namespace DevOwl\RealThumbnailGenerator;

use DevOwl\RealThumbnailGenerator\attachment\Regenerate;
use DevOwl\RealThumbnailGenerator\attachment\Thumbnail;
use DevOwl\RealThumbnailGenerator\base\Core as BaseCore;
use DevOwl\RealThumbnailGenerator\editor\Adapter;
use DevOwl\RealThumbnailGenerator\editor\Editor;
use DevOwl\RealThumbnailGenerator\lite\Core as LiteCore;
use DevOwl\RealThumbnailGenerator\overrides\interfce\IOverrideCore;
use DevOwl\RealThumbnailGenerator\rest\Analyse;
use DevOwl\RealThumbnailGenerator\rest\Service;
use DevOwl\RealThumbnailGenerator\view\View;
use DevOwl\RealThumbnailGenerator\Vendor\MatthiasWeb\Utils\Service as UtilsService;
use DevOwl\RealThumbnailGenerator\Vendor\MatthiasWeb\Utils\ServiceNoStore;
// @codeCoverageIgnoreStart
\defined('ABSPATH') or die('No script kiddies please!');
// Avoid direct file request
// @codeCoverageIgnoreEnd
/**
 * Singleton core class which handles the main system for plugin. It includes
 * registering of the autoload, all hooks (actions & filters) (see BaseCore class).
 * @internal
 */
class Core extends BaseCore implements IOverrideCore
{
    use LiteCore;
    /**
     * Singleton instance.
     */
    private static $me;
    /**
     * See RpmInitiator.
     *
     * @var RpmInitiator
     */
    private $rpmInitiator;
    /**
     * Application core constructor.
     */
    protected function __construct()
    {
        parent::__construct();
        // Enable `no-store` for our relevant WP REST API endpoints
        ServiceNoStore::hook('/' . UtilsService::getNamespace($this));
        \DevOwl\RealThumbnailGenerator\Localization::enableWordPressDotOrgLanguagePacksDownload(RTG_SLUG, RTG_SLUG_LITE);
        \add_filter('wp_die_ajax_handler', [Regenerate::getInstance(), 'wp_die']);
        \add_filter('wp_die_handler', [Regenerate::getInstance(), 'wp_die']);
        \add_filter('rest_post_dispatch', [Regenerate::getInstance(), 'wp_die'], 10, 1);
        $this->rpmInitiator = new \DevOwl\RealThumbnailGenerator\RpmInitiator();
        $this->rpmInitiator->start();
        $this->overrideConstruct();
        $this->overrideConstructFreemium();
        (new \DevOwl\RealThumbnailGenerator\AdInitiator())->start();
    }
    /**
     * Define constants which relies on i18n localization loaded.
     */
    public function i18n()
    {
        parent::i18n();
        $translatedUrl = \__('https://devowl.io/go/real-thumbnail-generator?source=rtg-lite', RTG_TD);
        \define('RTG_PRO_VERSION', $translatedUrl);
    }
    /**
     * The init function is fired even the init hook of WordPress. If possible
     * it should register all hooks to have them in one place.
     */
    public function init()
    {
        // add_image_size('favicon', 32, 32, true);
        // add_image_size('another', 64, 64, true);
        // add_image_size('et-pb-image--responsive--phone', 480, 270, true);
        // add_image_size('et-pb-image--responsive--desktop', 1280, 720, true);
        // Register all your hooks here
        \add_action('rest_api_init', [Analyse::instance(), 'rest_api_init']);
        \add_action('rest_api_init', [Service::instance(), 'rest_api_init']);
        \add_action('delete_attachment', [Thumbnail::getInstance(), 'delete_attachment']);
        \add_action('admin_enqueue_scripts', [$this->getAssets(), 'admin_enqueue_scripts']);
        \add_action('wp_enqueue_scripts', [$this->getAssets(), 'wp_enqueue_scripts']);
        \add_filter('plugin_action_links_' . \plugin_basename(RTG_FILE), [View::getInstance(), 'plugin_action_links']);
        \add_filter('media_row_actions', [View::getInstance(), 'media_row_actions'], 10, 2);
        \add_filter('attachment_fields_to_edit', [View::getInstance(), 'attachment_fields_to_edit'], 10, 2);
        \add_filter('admin_menu', [View::getInstance(), 'admin_menu']);
        \add_filter('wp_update_attachment_metadata', [Regenerate::getInstance(), 'wp_update_attachment_metadata'], 10, 2);
        \add_filter('wp_image_editors', [Editor::getInstance(), 'wp_image_editors'], \PHP_INT_MAX);
        \add_filter('wp_generate_attachment_metadata', [Adapter::class, 'wp_generate_attachment_metadata'], \PHP_INT_MAX, 2);
        \add_filter('add_attachment', [Adapter::class, 'add_attachment']);
        \add_filter('crop_thumbnails_filename', [Adapter::class, 'crop_thumbnails_filename'], 11, 5);
        \add_filter('crop_thumbnails_before_update_metadata', [Adapter::class, 'crop_thumbnails_before_update_metadata'], 11);
        $this->overrideInit();
    }
    /**
     * Get ad initiator from `real-product-manager-wp-client`.
     *
     * @codeCoverageIgnore
     */
    public function getRpmInitiator()
    {
        return $this->rpmInitiator;
    }
    /**
     * Get singleton core class.
     *
     * @return Core
     */
    public static function getInstance()
    {
        return !isset(self::$me) ? self::$me = new \DevOwl\RealThumbnailGenerator\Core() : self::$me;
    }
}
/**
 * See API docs.
 *
 * @api {get} /real-thumbnail-generator/v1/plugin Get plugin information
 * @apiHeader {string} X-WP-Nonce
 * @apiName GetPlugin
 * @apiGroup Plugin
 *
 * @apiSuccessExample {json} Success-Response:
 * {
 *     Name: "My plugin",
 *     PluginURI: "https://example.com/my-plugin",
 *     Version: "0.1.0",
 *     Description: "This plugin is doing something.",
 *     Author: "<a href="https://example.com">John Smith</a>",
 *     AuthorURI: "https://example.com",
 *     TextDomain: "my-plugin",
 *     DomainPath: "/languages",
 *     Network: false,
 *     Title: "<a href="https://example.com">My plugin</a>",
 *     AuthorName: "John Smith"
 * }
 * @apiVersion 0.1.0
 */

<?php

namespace DevOwl\RealThumbnailGenerator;

use DevOwl\RealThumbnailGenerator\Vendor\DevOwl\Freemium\Assets as FreemiumAssets;
use DevOwl\RealThumbnailGenerator\Vendor\DevOwl\RealUtils\Core;
use DevOwl\RealThumbnailGenerator\base\UtilsProvider;
use DevOwl\RealThumbnailGenerator\Vendor\DevOwl\RealProductManagerWpClient\Core as RpmWpClientCore;
use DevOwl\RealThumbnailGenerator\Vendor\DevOwl\RealProductManagerWpClient\license\License;
use DevOwl\RealThumbnailGenerator\Vendor\MatthiasWeb\Utils\Assets as UtilsAssets;
use DevOwl\RealThumbnailGenerator\Vendor\MatthiasWeb\Utils\Constants;
// @codeCoverageIgnoreStart
\defined('ABSPATH') or die('No script kiddies please!');
// Avoid direct file request
// @codeCoverageIgnoreEnd
/**
 * Asset management for frontend scripts and styles.
 * @internal
 */
class Assets
{
    use UtilsProvider;
    use UtilsAssets;
    use FreemiumAssets;
    /**
     * Enqueue scripts and styles depending on the type. This function is called
     * from both admin_enqueue_scripts and wp_enqueue_scripts. You can check the
     * type through the $type parameter. In this function you can include your
     * external libraries from src/public/lib, too.
     *
     * @param string $type The type (see utils Assets constants)
     * @param string $hook_suffix The current admin page
     */
    public function enqueue_scripts_and_styles($type, $hook_suffix = null)
    {
        // Generally check if an entrypoint should be loaded
        if (!\in_array($type, [Constants::ASSETS_TYPE_ADMIN], \true)) {
            return;
        }
        $realUtils = RTG_ROOT_SLUG . '-real-utils-helper';
        // Your assets implementation here... See utils Assets for enqueue* methods
        $useNonMinifiedSources = $this->useNonMinifiedSources();
        // Use this variable if you need to differ between minified or non minified sources
        // Our utils package relies on jQuery, but this shouldn't be a problem as the most themes still use jQuery (might be replaced with https://github.com/github/fetch)
        // Enqueue external utils package
        $scriptDeps = $this->enqueueUtils();
        $scriptDeps = \array_merge($scriptDeps, [$realUtils]);
        // real-product-manager-wp-client (for licensing purposes)
        \array_unshift($scriptDeps, RpmWpClientCore::getInstance()->getAssets()->enqueue($this));
        // Enqueue plugin entry points
        // react-window
        \array_unshift($scriptDeps, $this->enqueueLibraryScript('react-window', [[$useNonMinifiedSources, 'react-window/dist/index-dev.umd.js'], 'react-window/dist/index-prod.umd.js'], ['react', 'react-dom']));
        $handle = $this->enqueueScript('admin', [[$this->isPro(), 'admin.pro.js'], 'admin.lite.js'], $scriptDeps);
        $this->enqueueStyle('admin', 'admin.css', [$realUtils]);
        // Localize script with server-side variables
        \wp_localize_script($handle, 'realThumbnailGenerator', $this->localizeScript($type));
    }
    /**
     * Localize the WordPress backend and frontend. If you want to provide URLs to the
     * frontend you have to consider that some JS libraries do not support umlauts
     * in their URI builder. For this you can use utils Assets#getAsciiUrl.
     *
     * Also, if you want to use the options typed in your frontend you should
     * adjust the following file too: src/public/ts/store/option.tsx
     *
     * @param string $context
     * @return array
     */
    public function overrideLocalizeScript($context)
    {
        require_once ABSPATH . WPINC . '/class-wp-image-editor.php';
        require_once ABSPATH . WPINC . '/class-wp-image-editor-gd.php';
        require_once ABSPATH . WPINC . '/class-wp-image-editor-imagick.php';
        $core = $this->getCore();
        $pluginUpdater = $core->getRpmInitiator()->getPluginUpdater();
        $licenseActivation = $pluginUpdater->getCurrentBlogLicense()->getActivation();
        $showLicenseFormImmediate = !$licenseActivation->hasInteractedWithFormOnce();
        $isDevLicense = $licenseActivation->getInstallationType() === License::INSTALLATION_TYPE_DEVELOPMENT;
        if ($context === Constants::ASSETS_TYPE_ADMIN) {
            return \array_merge(['showLicenseFormImmediate' => $showLicenseFormImmediate, 'isDevLicense' => $isDevLicense, 'licenseActivationLink' => $pluginUpdater->getView()->getActivateLink(\true), 'canManageOptions' => \current_user_can('manage_options'), 'thumbnailFolder' => \get_option(RTG_OPT_PREFIX . '_thumbnail_folder', ''), 'thumbnailFilename' => \get_option(RTG_OPT_PREFIX . '_thumbnail_filename', ''), 'chunkSize' => \intval(\get_option(RTG_OPT_PREFIX . '_chunk_size', 8)), 'pluginsUrl' => \admin_url('plugins.php'), 'implementations' => \apply_filters('wp_image_editors', ['WP_Image_Editor_Imagick', 'WP_Image_Editor_GD'])], $this->localizeFreemiumScript());
        }
        return [];
    }
}

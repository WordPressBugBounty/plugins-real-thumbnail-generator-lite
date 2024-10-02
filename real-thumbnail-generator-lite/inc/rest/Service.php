<?php

namespace DevOwl\RealThumbnailGenerator\rest;

use DevOwl\RealThumbnailGenerator\base\UtilsProvider;
use DevOwl\RealThumbnailGenerator\Core;
use DevOwl\RealThumbnailGenerator\editor\Editor;
use DevOwl\RealThumbnailGenerator\Vendor\MatthiasWeb\Utils\Service as UtilsService;
use WP_Error;
use WP_REST_Response;
// @codeCoverageIgnoreStart
\defined('ABSPATH') or die('No script kiddies please!');
// Avoid direct file request
// @codeCoverageIgnoreEnd
/**
 * Create a REST Service for basic purposes.
 * @internal
 */
class Service
{
    use UtilsProvider;
    /**
     * C'tor.
     */
    private function __construct()
    {
        // Silence is golden.
    }
    /**
     * Register endpoints.
     */
    public function rest_api_init()
    {
        $namespace = UtilsService::getNamespace($this);
        \register_rest_route($namespace, '/settings', ['methods' => 'PUT', 'callback' => [$this, 'routePutSettings'], 'permission_callback' => [$this, 'permission_callback']]);
        \register_rest_route($namespace, '/settings/verify', ['methods' => 'POST', 'callback' => [$this, 'routeVerifySettings'], 'permission_callback' => [$this, 'permission_callback']]);
    }
    /**
     * Check if user is allowed to call this service requests.
     */
    public function permission_callback()
    {
        $permit = \DevOwl\RealThumbnailGenerator\rest\Service::permit('manage_options');
        return $permit === null ? \true : $permit;
    }
    /**
     * Check if user is allowed to call this service requests with `activate_plugins` cap.
     */
    public function permission_callback_activate_plugins()
    {
        $permit = \DevOwl\RealThumbnailGenerator\rest\Service::permit('activate_plugins');
        return $permit === null ? \true : $permit;
    }
    /**
     * See API docs.
     *
     * @param WP_REST_Request $request
     * @api {get} /real-thumbnail-generator/v1/settings Update settings
     * @apiHeader {string} X-WP-Nonce
     * @apiParam {string} thumbnailFolder The thumbnail folder scheme
     * @apiParam {string} thumbnailFilename The thumbnail filename scheme
     * @apiParam {number} chunkSize The chunk size
     * @apiName UpdateSettings
     * @apiGroup Settings
     * @apiVersion 1.0.0
     */
    public function routePutSettings($request)
    {
        $thumbnailFolder = $request->get_param('thumbnailFolder');
        $thumbnailFilename = $request->get_param('thumbnailFilename');
        $chunkSize = $request->get_param('chunkSize');
        if ($chunkSize < 1) {
            return new WP_Error('rest_rtg_invalid_chunk', \__('Please use a chunk size greater than 0!', RTG_TD));
        }
        // Verify if schema is valid
        if (!empty($thumbnailFolder) || !empty($thumbnailFilename)) {
            $verify = Editor::getInstance()->testThumbnailPath($thumbnailFolder, $thumbnailFilename);
            if (\count($verify['errors']) > 0) {
                return new WP_Error('rest_rtg_invalid_schema', \__('The defined Thumbnail folder and file name is not usable. Please check that it is syntactically correct!', RTG_TD), ['verify' => $verify]);
            }
        }
        \update_option(RTG_OPT_PREFIX . '_thumbnail_folder', $thumbnailFolder);
        \update_option(RTG_OPT_PREFIX . '_thumbnail_filename', $thumbnailFilename);
        \update_option(RTG_OPT_PREFIX . '_chunk_size', $chunkSize);
        return new WP_REST_Response(['success' => \true]);
    }
    /**
     * See API docs.
     *
     * @param WP_REST_Request $request
     * @api {post} /real-thumbnail-generator/v1/settings/verify Verify settings
     * @apiHeader {string} X-WP-Nonce
     * @apiParam {string} thumbnailFolder The thumbnail folder scheme
     * @apiParam {string} thumbnailFilename The thumbnail filename scheme
     * @apiName VerifySettings
     * @apiGroup Settings
     * @apiVersion 1.0.0
     */
    public function routeVerifySettings($request)
    {
        $thumbnailFolder = $request->get_param('thumbnailFolder');
        $thumbnailFilename = $request->get_param('thumbnailFilename');
        return new WP_REST_Response(Editor::getInstance()->testThumbnailPath($thumbnailFolder, $thumbnailFilename));
    }
    /**
     * Checks if the current user has a given capability and throws an error if not.
     *
     * @param string $cap The capability
     * @throws WP_Error
     */
    public static function permit($cap = 'upload_files')
    {
        if (!\current_user_can($cap)) {
            return new WP_Error('rest_rtg_forbidden', \__('Forbidden'), ['status' => 403]);
        }
        return null;
    }
    /**
     * New instance.
     */
    public static function instance()
    {
        return new \DevOwl\RealThumbnailGenerator\rest\Service();
    }
}

<?php

namespace DevOwl\RealThumbnailGenerator\rest;

use DevOwl\RealThumbnailGenerator\attachment\Analyse as AttachmentAnalyse;
use DevOwl\RealThumbnailGenerator\attachment\Regenerate;
use DevOwl\RealThumbnailGenerator\attachment\Thumbnail;
use DevOwl\RealThumbnailGenerator\base\UtilsProvider;
use DevOwl\RealThumbnailGenerator\Vendor\MatthiasWeb\Utils\Service as UtilsService;
use WP_REST_Request;
use WP_REST_Response;
// @codeCoverageIgnoreStart
\defined('ABSPATH') or die('No script kiddies please!');
// Avoid direct file request
// @codeCoverageIgnoreEnd
/**
 * Create a REST Service for the analyse / bulk regenerate process.
 *
 * @codeCoverageIgnore Example implementations gets deleted the most time after plugin creation!
 * @internal
 */
class Analyse
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
        \register_rest_route($namespace, '/stats', ['methods' => 'GET', 'callback' => [$this, 'routeStats'], 'permission_callback' => [$this, 'permission_callback']]);
        \register_rest_route($namespace, '/attachments', ['methods' => 'GET', 'callback' => [$this, 'routeAttachments'], 'permission_callback' => [$this, 'permission_callback']]);
        \register_rest_route($namespace, '/attachments/(?P<id>\\d+)', ['methods' => 'GET', 'callback' => [$this, 'routeSingle'], 'permission_callback' => [$this, 'permission_callback']]);
        \register_rest_route($namespace, '/attachments', ['methods' => 'POST', 'callback' => [$this, 'routeRegenerate'], 'permission_callback' => [$this, 'permission_callback']]);
        \register_rest_route($namespace, '/attachments/(?P<id>\\d+)', ['methods' => 'POST', 'callback' => [$this, 'routeRegenerateSingle'], 'permission_callback' => [$this, 'permission_callback']]);
        \register_rest_route($namespace, '/attachments/(?P<id>\\d+)', ['methods' => 'DELETE', 'callback' => [$this, 'routeDeleteSingle'], 'permission_callback' => [$this, 'permission_callback']]);
        \register_rest_route($namespace, '/attachments', ['methods' => 'DELETE', 'callback' => [$this, 'routeDelete'], 'permission_callback' => [$this, 'permission_callback']]);
    }
    /**
     * Check if user is allowed to call this service requests.
     */
    public function permission_callback()
    {
        $permit = \DevOwl\RealThumbnailGenerator\rest\Service::permit('upload_files');
        return $permit === null ? \true : $permit;
    }
    /**
     * See API docs.
     *
     * @param WP_REST_Request $request
     * @api {get} /real-thumbnail-generator/v1/stats Get stats
     * @apiHeader {string} X-WP-Nonce
     * @apiName GetStats
     * @apiGroup Analyse
     * @apiVersion 1.0.0
     */
    public function routeStats($request)
    {
        return new WP_REST_Response(AttachmentAnalyse::getInstance()->fetchStats());
    }
    /**
     * See API docs.
     *
     * @param WP_REST_Request $request
     * @api {get} /real-thumbnail-generator/v1/attachments Get attachments
     * @apiHeader {string} X-WP-Nonce
     * @apiParam {number} [posts_per_page=40]
     * @apiParam {number} [page=1]
     * @apiName GetAttachments
     * @apiGroup Analyse
     * @apiVersion 1.0.0
     */
    public function routeAttachments($request)
    {
        return new WP_REST_Response(AttachmentAnalyse::getInstance()->fetchAttachments($request->get_param('posts_per_page'), $request->get_param('page')));
    }
    /**
     * See API docs.
     *
     * @param WP_REST_Request $request
     * @api {get} /real-thumbnail-generator/v1/attachments/:id Get single attachment
     * @apiHeader {string} X-WP-Nonce
     * @apiParam {number} [posts_per_page=40]
     * @apiParam {number} [page=1]
     * @apiName GetSingle
     * @apiGroup Analyse
     * @apiVersion 1.0.0
     */
    public function routeSingle($request)
    {
        $id = $request->get_param('id');
        $check = $this->prepareSingleCheck($id);
        if (\is_wp_error($check)) {
            return $check;
        }
        return new WP_REST_Response($check);
    }
    /**
     * Prepare output for a single post / attachment.
     *
     * @param int $id
     */
    private function prepareSingleCheck($id)
    {
        $check = Thumbnail::getInstance()->check($id);
        if (\is_wp_error($check)) {
            return $check;
        }
        $check['usedSchema'] = \trailingslashit($check['schemaf']) . $check['schema'];
        $check['newSchema'] = Thumbnail::getInstance()->getSchema();
        $check['deletableSpaceFormat'] = \size_format($check['deletableSpace']);
        $check['filesizeSumFormat'] = \size_format($check['filesizeSum']);
        unset($check['meta']);
        unset($check['attachedFile']);
        unset($check['unused']);
        unset($check['mustGenerate']);
        unset($check['notFound']);
        unset($check['deletable']);
        unset($check['available']);
        return $check;
    }
    /**
     * See API docs.
     *
     * @param WP_REST_Request $request
     * @api {post} /real-thumbnail-generator/v1/attachments Regenerate attachments
     * @apiHeader {string} X-WP-Nonce
     * @apiParam {number} [posts_per_page=40]
     * @apiParam {number} [page=1]
     * @apiParam {string[]} sizes The sizes to regenerate
     * @apiParam {boolean} [forceNewSchema] If true all sizes are regenerated with the new schema
     * @apiParam {boolean} [skipExisting] If true existing files are skipped
     * @apiName RegenerateAttachments
     * @apiGroup Analyse
     * @apiVersion 1.0.0
     */
    public function routeRegenerate($request)
    {
        $posts_per_page = $request->get_param('posts_per_page');
        $page = $request->get_param('page');
        $attachments = AttachmentAnalyse::getInstance()->fetchAttachments($posts_per_page, $page);
        $sizes = $request->get_param('sizes');
        $forceNewSchema = $request->get_param('forceNewSchema');
        $skipExisting = $request->get_param('skipExisting');
        $ids = [];
        foreach ($attachments as $check) {
            $ids[] = $check['id'];
        }
        // Register only sizes in global variable
        if (\is_array($sizes) && \count($sizes) > 0) {
            $_REQUEST['onlyThisSizes'] = $sizes;
        }
        if ($skipExisting) {
            $_REQUEST['skipExisting'] = \true;
        }
        // Do the regeneration
        include_once ABSPATH . 'wp-admin/includes/image.php';
        // Not included in REST
        $result = Regenerate::getInstance()->regenerate($ids, $forceNewSchema, \true);
        $fetch = AttachmentAnalyse::getInstance()->fetchAttachments($posts_per_page, $page);
        // Merge errors
        foreach ($fetch as $key => $check) {
            if (isset($result[$check['id']]) && isset($result[$check['id']]['error'])) {
                $fetch[$key]['error'] = $result[$check['id']]['error'];
            }
        }
        return new WP_REST_Response($fetch);
    }
    /**
     * See API docs.
     *
     * @param WP_REST_Request $request
     * @api {post} /real-thumbnail-generator/v1/attachments/:id Regenerate single attachment
     * @apiHeader {string} X-WP-Nonce
     * @apiParam {boolean} [forceNewSchema] If true all sizes are regenerated with the new schema
     * @apiName RegenerateAttachment
     * @apiGroup Analyse
     * @apiVersion 1.0.0
     */
    public function routeRegenerateSingle($request)
    {
        $id = $request->get_param('id');
        $forceNewSchema = $request->get_param('forceNewSchema');
        // Do the regeneration
        include_once ABSPATH . 'wp-admin/includes/image.php';
        // Not included in REST
        $result = Regenerate::getInstance()->regenerate([$id], $forceNewSchema, \true);
        // Output
        $check = $this->prepareSingleCheck($id);
        if (\is_wp_error($check)) {
            return $check;
        }
        // Merge error
        if (isset($result[$id]['error'])) {
            $check['error'] = $result[$id]['error'];
        }
        return new WP_REST_Response($check);
    }
    /**
     * See API docs.
     *
     * @param WP_REST_Request $request
     * @api {delete} /real-thumbnail-generator/v1/attachments/:id Delete thumbnails of sizes that no longer exist
     * @apiHeader {string} X-WP-Nonce
     * @apiName DeleteUnusedAttachment
     * @apiGroup Analyse
     * @apiVersion 1.0.0
     */
    public function routeDeleteSingle($request)
    {
        $id = $request->get_param('id');
        // Do the regeneration
        include_once ABSPATH . 'wp-admin/includes/image.php';
        // Not included in REST
        $result = Thumbnail::getInstance()->deleteUnused([$id]);
        if (\is_wp_error($result)) {
            return $result;
        }
        // Output
        $check = $this->prepareSingleCheck($id);
        if (\is_wp_error($check)) {
            return $check;
        }
        return new WP_REST_Response($check);
    }
    /**
     * See API docs.
     *
     * @param WP_REST_Request $request
     * @api {delete} /real-thumbnail-generator/v1/attachments Delete all unused sizes
     * @apiHeader {string} X-WP-Nonce
     * @apiParam {number} [posts_per_page=40]
     * @apiParam {number} [page=1]
     * @apiName DeleteAllUnused
     * @apiGroup Analyse
     * @apiVersion 1.0.0
     */
    public function routeDelete($request)
    {
        $posts_per_page = $request->get_param('posts_per_page');
        $page = $request->get_param('page');
        $attachments = AttachmentAnalyse::getInstance()->fetchAttachments($posts_per_page, $page);
        $ids = [];
        foreach ($attachments as $check) {
            $ids[] = $check['id'];
        }
        // Do the regeneration
        include_once ABSPATH . 'wp-admin/includes/image.php';
        // Not included in REST
        $result = Thumbnail::getInstance()->deleteUnused($ids);
        if (\is_wp_error($result)) {
            return $result;
        }
        return new WP_REST_Response(AttachmentAnalyse::getInstance()->fetchAttachments($posts_per_page, $page));
    }
    /**
     * New instance.
     */
    public static function instance()
    {
        return new \DevOwl\RealThumbnailGenerator\rest\Analyse();
    }
}

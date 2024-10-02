<?php

namespace DevOwl\RealThumbnailGenerator\attachment;

use DevOwl\RealThumbnailGenerator\base\UtilsProvider;
use DevOwl\RealThumbnailGenerator\editor\Editor;
// @codeCoverageIgnoreStart
\defined('ABSPATH') or die('No script kiddies please!');
// Avoid direct file request
// @codeCoverageIgnoreEnd
/**
 * Analyse information and stats.
 * @internal
 */
class Analyse
{
    use UtilsProvider;
    /**
     * Singleton instance.
     */
    private static $me;
    /**
     * Fetch attachments info
     *
     * @param int $posts_per_page
     * @param int $page
     * @return array
     */
    public function fetchAttachments($posts_per_page = 40, $page = 1)
    {
        global $wpdb;
        $rtgThumbnail = \DevOwl\RealThumbnailGenerator\attachment\Thumbnail::getInstance();
        $rtgRegenerate = \DevOwl\RealThumbnailGenerator\attachment\Regenerate::getInstance();
        // Create the query args
        $posts_per_page = (int) (\is_numeric($posts_per_page) ? $posts_per_page : 40);
        $page = (int) (\is_numeric($page) ? $page : 1);
        // Create the SQL
        // phpcs:disable WordPress.DB.PreparedSQL
        $sql = $wpdb->prepare($this->getAttachmentSql('wp.ID AS id, wp.guid, wpm.meta_value AS wp_metadata') . ' ORDER BY wp.post_date ASC LIMIT %d, %d', ($page - 1) * $posts_per_page, $posts_per_page);
        $result = $wpdb->get_results($sql, ARRAY_A);
        // phpcs:enable WordPress.DB.PreparedSQL
        for ($i = 0; $i < \count($result); $i++) {
            $row =& $result[$i];
            $row['wp_metadata'] = \unserialize($row['wp_metadata']);
            $attachedFile = $rtgThumbnail->get_attached_file_from_meta($row['id'], $row['wp_metadata']);
            $check = $rtgThumbnail->check($row['id'], \true, $attachedFile, $row['wp_metadata']);
            $row['check'] = $rtgThumbnail->prepareForJS($check);
            $row['filename'] = \basename($attachedFile);
            if (!\is_wp_error($check)) {
                // File not found on server but still in database...
                $rtgRegenerate->synchronize($row['id'], $check, 'wp_die');
                // Reduce the download and find the thumbnail preview url / orientation
                if (isset($row['wp_metadata']['sizes']) && isset($row['wp_metadata']['sizes']['thumbnail'])) {
                    $thumbnail = $row['wp_metadata']['sizes']['thumbnail'];
                    $thumbnailFile = $thumbnail['file'];
                    $parsed = \parse_url($row['guid']);
                    $row['orientation'] = $thumbnail['width'] > $thumbnail['height'] ? 'landscape' : 'portrait';
                } else {
                    $row['orientation'] = $row['wp_metadata']['width'] > $row['wp_metadata']['height'] ? 'landscape' : 'portrait';
                }
            } else {
                $row['orientation'] = 'portrait';
            }
            $row['thumbnailUrl'] = \wp_get_attachment_image_src($row['id']);
            if (isset($row['thumbnailUrl'][0])) {
                $row['thumbnailUrl'] = $row['thumbnailUrl'][0];
            }
            unset($row['guid']);
            unset($row['wp_metadata']);
            $row['id'] = (int) $row['id'];
        }
        return $result;
    }
    /**
     * Fetch different stats for the analyse page.
     *
     * @return array
     */
    public function fetchStats()
    {
        global $wpdb;
        $arr = [];
        $schema = \DevOwl\RealThumbnailGenerator\attachment\Thumbnail::getInstance()->getSchema();
        $table_name = $this->getTableName('attachments');
        // phpcs:disable WordPress.DB.PreparedSQL
        $sums = $wpdb->get_row('SELECT SUM(free_space) AS sum_free_space, SUM(cnt_regenerate) AS sum_cnt_regenerate FROM ' . $table_name, ARRAY_A);
        // phpcs:enable WordPress.DB.PreparedSQL
        $deletableSpace = $sums['sum_free_space'];
        $deletableSpace = (int) $deletableSpace > 0 ? $deletableSpace : 0;
        $arr['sizes'] = \DevOwl\RealThumbnailGenerator\attachment\Thumbnail::getInstance()->get_image_sizes();
        $arr['schema'] = Editor::getInstance()->wrapSpan($schema);
        $arr['deletableSpaceFormat'] = \size_format($deletableSpace);
        $arr['deletableSpace'] = $deletableSpace;
        $arr['newThumbnailsCount'] = $sums['sum_cnt_regenerate'] > 0 ? (int) $sums['sum_cnt_regenerate'] : 0;
        // phpcs:disable WordPress.DB.PreparedSQL
        $arr['imagesCount'] = (int) $wpdb->get_var($this->getAttachmentSql('COUNT(*)'));
        // phpcs:enable WordPress.DB.PreparedSQL
        $arr['totalCount'] = (int) $wpdb->get_var('SELECT SUM( ROUND (
            	( LENGTH(meta_value) - LENGTH( REPLACE ( meta_value, \'s:4:"file"\', \'\') ) )
            	/ LENGTH(\'s:4:"file"\')
            ) ) AS count
            FROM ' . $wpdb->postmeta . '
            WHERE meta_key = \'_wp_attachment_metadata\'');
        return $arr;
    }
    /**
     * Generate SQL for analyse purposes.
     *
     * @param string $fields
     */
    private function getAttachmentSql($fields)
    {
        global $wpdb;
        $rtgThumbnail = \DevOwl\RealThumbnailGenerator\attachment\Thumbnail::getInstance();
        $isPdfable = $rtgThumbnail->isPdfable();
        return "SELECT {$fields}\n            FROM {$wpdb->posts} AS wp\n            INNER JOIN {$wpdb->postmeta} AS wpm ON wpm.post_id = wp.ID AND wpm.meta_key = '_wp_attachment_metadata'\n            WHERE (wp.post_mime_type LIKE 'image/%'" . ($isPdfable ? " OR wp.post_mime_type = 'application/pdf'" : '') . ")\n            AND wp.post_type = 'attachment'\n            AND ((wp.post_status = 'inherit' " . (\current_user_can(\get_post_type_object('attachment')->cap->read_private_posts) ? " OR wp.post_status = 'private'" : '') . '))';
    }
    /**
     * Get singleton instance.
     *
     * @return Analyse
     */
    public static function getInstance()
    {
        return self::$me === null ? self::$me = new \DevOwl\RealThumbnailGenerator\attachment\Analyse() : self::$me;
    }
}

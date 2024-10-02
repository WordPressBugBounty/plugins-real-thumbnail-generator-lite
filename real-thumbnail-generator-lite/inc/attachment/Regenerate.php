<?php

namespace DevOwl\RealThumbnailGenerator\attachment;

use DevOwl\RealThumbnailGenerator\base\UtilsProvider;
use ErrorException;
use Exception;
use DevOwl\RealThumbnailGenerator\attachment\Thumbnail;
use DevOwl\RealThumbnailGenerator\editor\Editor;
use WP_Error;
// @codeCoverageIgnoreStart
\defined('ABSPATH') or die('No script kiddies please!');
// Avoid direct file request
// @codeCoverageIgnoreEnd
/**
 * Regeneration process.
 * @internal
 */
class Regenerate
{
    use UtilsProvider;
    private static $me = null;
    /**
     * Synchronize attachments on wp_die.
     */
    private $wpDieSynchronize = [];
    private $synchronizeAction = '';
    /**
     * Hold the deletable files in the metadata so it can be deleted
     * from the the server.
     *
     * @param array $data Array of updated attachment meta data
     * @param int $post_id Attachment ID
     */
    public function wp_update_attachment_metadata($data, $post_id)
    {
        if (isset($GLOBALS['rtg_suppress_update_metadata']) && $GLOBALS['rtg_suppress_update_metadata'] === \true) {
            // perhaps we should work with remove_filter?
            return $data;
        }
        // Check if there are sizes which are deletable
        $check = Thumbnail::getInstance()->check($post_id, \false);
        if (!\is_wp_error($check) && \count($check['deletable']) > 0) {
            // Try to merge with sizes
            if (!isset($data['sizes'])) {
                $data['sizes'] = [];
            }
            $data['sizes'] = \array_unique(\array_merge($data['sizes'], $check['deletable']), \SORT_REGULAR);
        }
        // Correct the check from the actions
        if ($this->synchronizeAction === 'regenerate') {
            $check['mustGenerate'] = [];
        }
        // Save in own table for analyse purposes
        $this->synchronize($post_id, $check, \defined('DOING_AJAX') && \constant('DOING_AJAX') ? 'wp_die' : null);
        return $data;
    }
    /**
     * Synchronizes a check result with the rtg_attachments table so on this
     * base there can be made analysis.
     *
     * @param int $postId The post ID
     * @param array $check The result of Thumbnail::check()
     * @param array|string $group If an array is given the the VALUES() SQL's are stored here. If you want to run
     *               the SQL you must use the this::synchronizeGroup function.
     * @return boolean
     */
    public function synchronize($postId, $check, $group = null)
    {
        global $wpdb;
        $cnt = 0;
        $fileSize = 0;
        if (!\is_wp_error($check)) {
            $cnt = \count($check['deletable']);
            $fileSize = $check['deletableSpace'];
        }
        // Determine the group
        if (\is_array($group)) {
            $doQuery = \false;
        } elseif ($group !== 'wp_die') {
            $doQuery = \true;
            $group = [];
        }
        // Prepare the query for the group
        $newValues = $wpdb->prepare('(%d, %d, %d, %d)', $postId, $fileSize, $cnt, \is_array($check) ? \count($check['mustGenerate']) + \count($check['notFound']) : 0);
        if ($group === 'wp_die') {
            $this->wpDieSynchronize[] = $newValues;
        } else {
            $group[] = $newValues;
            if ($doQuery) {
                $this->synchronizeGroup($group);
            }
        }
        return \true;
    }
    /**
     * Synchronizes a group of attachments.
     *
     * @param array $group An array of VALUES() SQL's
     */
    public function synchronizeGroup($group)
    {
        global $wpdb;
        if (\count($group) === 0) {
            return \false;
        }
        $table_name = $this->getTableName('attachments');
        $valuesSql = \implode(',', $group);
        $sql = "INSERT INTO {$table_name} (`post_id`, `free_space`, `cnt`, `cnt_regenerate`)\n            VALUES " . $valuesSql . ' ON DUPLICATE KEY UPDATE free_space=VALUES(free_space), cnt=VALUES(cnt), cnt_regenerate=VALUES(cnt_regenerate)';
        // phpcs:disable WordPress.DB.PreparedSQL
        $wpdb->query($sql);
        // phpcs:enable WordPress.DB.PreparedSQL
        return \true;
    }
    /**
     * Synchronizes a group of attachments at the end of the WordPress call.
     *
     * @param mixed $arg0
     * @return mixed
     */
    public function wp_die($arg0)
    {
        $this->synchronizeGroup($this->wpDieSynchronize);
        return $arg0;
    }
    /**
     * Regenerates a set of Thumbnails. If you want to regenerate only a set of
     * defined sizes you have to define the $_REQUEST['onlyThisSizes'] = array('thumbnail')
     * variable.
     *
     * If you want to skip existing files define the $_REQUEST['skipExisting'] = true.
     *
     * @param int|int[] $ids Array of IDs or single ID
     * @param boolean $forceNew Determines if the new schema should be forced
     * @param boolean $prepareForJs Use the Thumbnail::prepareForJS function to minify download
     * @return false|array Array of post IDs with WP_Error or true
     * @throws Exception
     */
    public function regenerate($ids, $forceNew = \false, $prepareForJs = \false)
    {
        if (\is_numeric($ids)) {
            $ids = [$ids];
        }
        if (!\is_array($ids)) {
            return \false;
        }
        $result = [];
        // Iterate all ids
        foreach ($ids as $value) {
            try {
                \set_error_handler([$this, 'set_error_handler']);
                $single = $this->single($value, $forceNew, $prepareForJs);
                if (\is_wp_error($single)) {
                    // Throw
                    throw new Exception($single->get_error_message());
                } else {
                    $result[$value] = $single;
                }
                \restore_error_handler();
            } catch (Exception $e) {
                $result[$value] = ['error' => $e->getMessage()];
                \restore_error_handler();
            }
        }
        return $result;
    }
    /**
     * Override error handler to catch all exceptions.
     *
     * @param integer $errno
     * @param string $errstr
     * @param string $errfile
     * @param int $errline
     * @param mixed $errcontext
     * @return false
     * @throws ErrorException
     * @see https://stackoverflow.com/a/1241751/5506547
     */
    public function set_error_handler($errno, $errstr, $errfile, $errline, $errcontext = [])
    {
        // error was suppressed with the @-operator
        if (0 === \error_reporting()) {
            return \false;
        }
        throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    }
    /**
     * Regenerate a single post ID.
     *
     * @param int $postId The post id
     * @param boolean $forceNew Determines if the new schema should be forced
     * @param boolean $prepareForJs Use the Thumbnail::prepareForJS function to minify download
     * @return true|WP_Error
     */
    private function single($postId, $forceNew, $prepareForJs)
    {
        $check = Thumbnail::getInstance()->check($postId);
        if (\is_wp_error($check)) {
            return $check;
        }
        // Do the regenerate
        $rtgEditor = Editor::getInstance();
        $rtgEditor->activeRegenerationCheck = $check;
        $rtgEditor->activeRegenerationForceNew = $forceNew;
        $metadata = \wp_generate_attachment_metadata($postId, $check['attachedFile']);
        $rtgEditor->activeRegenerationCheck = \false;
        $rtgEditor->activeRegenerationCheck = \false;
        if (\is_array($metadata) && (isset($metadata['file']) || Thumbnail::getInstance()->isPdf($postId))) {
            $this->synchronizeAction = 'regenerate';
            \wp_update_attachment_metadata($postId, $metadata);
            $this->synchronizeAction = '';
            return $prepareForJs ? Thumbnail::getInstance()->prepareForJS($check) : $check;
        } else {
            return new WP_Error('generate-fails', \sprintf(
                // translators:
                \__('The thumbnails of image ID %d could not be generated for an unknown reason.', RTG_TD),
                $postId
            ));
        }
    }
    /**
     * Get singleton instance.
     *
     * @return Regenerate
     */
    public static function getInstance()
    {
        return self::$me === null ? self::$me = new \DevOwl\RealThumbnailGenerator\attachment\Regenerate() : self::$me;
    }
}

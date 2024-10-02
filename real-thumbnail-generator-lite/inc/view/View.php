<?php

namespace DevOwl\RealThumbnailGenerator\view;

use DevOwl\RealThumbnailGenerator\attachment\Thumbnail;
use DevOwl\RealThumbnailGenerator\base\UtilsProvider;
use WP_Post;
// @codeCoverageIgnoreStart
\defined('ABSPATH') or die('No script kiddies please!');
// Avoid direct file request
// @codeCoverageIgnoreEnd
/**
 * Server-side view handling.
 * @internal
 */
class View
{
    use UtilsProvider;
    private static $me = null;
    /**
     * Show a "Settings" link in plugins list.
     *
     * @param string[] $actions
     * @return string[]
     */
    public function plugin_action_links($actions)
    {
        $actions[] = \sprintf('<a href="%s">%s</a>', $this->getThumbnailsPageUrl(), \__('Regenerate Thumbnails', RTG_TD));
        return $actions;
    }
    /**
     * When editing a attachment show up the available thumbnail size count and regenerate button.
     *
     * @param array $form_fields
     * @param WP_Post $post
     */
    public function attachment_fields_to_edit($form_fields, $post)
    {
        if ($post->post_type !== 'attachment' || !(\wp_attachment_is_image($post->ID) || Thumbnail::getInstance()->isPdf($post->ID)) || !\current_user_can('upload_files')) {
            return $form_fields;
        }
        $check = Thumbnail::getInstance()->check($post->ID, \false);
        // Create form field
        $form_fields['rtg'] = ['label' => \__('Thumbnails', RTG_TD), 'input' => 'html', 'html' => '<div class="rtg-attachment-compat">
            <div class="alignleft">' . \sprintf(
            // translators:
            \__('%1$d of %2$s registered', RTG_TD),
            \is_wp_error($check) ? -1 : $check['cntGenerated'],
            \is_wp_error($check) ? -1 : $check['cntRegistered']
        ) . '</div>
    <button class="button alignright" data-rtg="' . $post->ID . '" data-action="info" style="margin-left:5px">' . \__('More', RTG_TD) . '</button>
    <button class="button alignright" data-rtg="' . $post->ID . '" data-action="regenerate">' . \__('Regenerate', RTG_TD) . '</button>
</div>'];
        return $form_fields;
    }
    /**
     * Adds a link to each table row so a single one can be regenerated.
     *
     * @param array $actions
     * @param WP_Post $post
     */
    public function media_row_actions($actions, $post)
    {
        if (('image/' === \substr($post->post_mime_type, 0, 6) || Thumbnail::getInstance()->isPdf($post)) && \current_user_can('upload_files')) {
            $actions['rtg_regenerate'] = '<a href="javascript:undefined" data-rtg="' . $post->ID . '" data-action="regenerate">' . \__('Regenerate', RTG_TD) . '</a>';
        }
        return $actions;
    }
    /**
     * Add submenu item "Thumbnails" with a link to the dialog.
     *
     * @see https://wordpress.stackexchange.com/a/266319/83335
     * @see https://github.com/WordPress/WordPress/blob/41c9686313bc85db95531dec1e8f75139f3fdd9a/wp-admin/menu.php#L66
     */
    public function admin_menu()
    {
        global $submenu;
        // Use unique marker so we can calculate the correct position for this item and alter afterwards with our custom URL
        $slugMarker = 'rtg-regenerator';
        \add_submenu_page('upload.php', '', '', 'upload_files', $slugMarker, '', 11);
        ($subMenus =& $submenu['upload.php']) ?? [];
        foreach ($subMenus as &$val) {
            if ($val[2] === $slugMarker) {
                $val = [\__('Regenerate Thumbnails', RTG_TD), 'upload_files', $this->getThumbnailsPageUrl()];
            }
        }
    }
    /**
     * Get the URL to the thumbnails dialog.
     */
    public function getThumbnailsPageUrl()
    {
        return \admin_url('upload.php?thumbnails=true');
    }
    /**
     * Get singleton instance.
     *
     * @return View
     */
    public static function getInstance()
    {
        return self::$me === null ? self::$me = new \DevOwl\RealThumbnailGenerator\view\View() : self::$me;
    }
}

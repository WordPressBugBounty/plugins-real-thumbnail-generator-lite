<?php

namespace DevOwl\RealThumbnailGenerator;

use DevOwl\RealThumbnailGenerator\base\UtilsProvider;
use DevOwl\RealThumbnailGenerator\Vendor\MatthiasWeb\Utils\Activator as UtilsActivator;
// @codeCoverageIgnoreStart
\defined('ABSPATH') or die('No script kiddies please!');
// Avoid direct file request
// @codeCoverageIgnoreEnd
/**
 * The activator class handles the plugin relevant activation hooks: Uninstall, activation,
 * deactivation and installation. The "installation" means installing needed database tables.
 * @internal
 */
class Activator
{
    use UtilsProvider;
    use UtilsActivator;
    /**
     * Method gets fired when the user activates the plugin.
     */
    public function activate()
    {
        // Your implementation...
    }
    /**
     * Method gets fired when the user deactivates the plugin.
     */
    public function deactivate()
    {
        // Your implementation...
    }
    // Documented in Activator
    public function getFirstDatabaseTableName()
    {
        return $this->getTableName($this->getTableName('attachments'));
    }
    /**
     * Install tables, stored procedures or whatever in the database.
     * This method is always called when the version bumps up or for
     * the first initial activation.
     *
     * @param boolean $errorlevel If true throw errors
     */
    public function dbDelta($errorlevel)
    {
        global $wpdb;
        $charset_collate = $this->getCharsetCollate();
        $table_name = $this->getTableName('attachments');
        $sql = "CREATE TABLE {$table_name} (\n    \t  `post_id` bigint(20) unsigned NOT NULL,\n    \t  `free_space` bigint(20) unsigned NOT NULL DEFAULT 0,\n    \t  `cnt` int(5) unsigned NOT NULL DEFAULT 0,\n    \t  `cnt_regenerate` mediumint(10) unsigned NOT NULL DEFAULT 0,\n    \t  UNIQUE KEY rtgatt (post_id)\n    \t) {$charset_collate};";
        \dbDelta($sql);
        if ($errorlevel) {
            $wpdb->print_error();
        }
    }
}

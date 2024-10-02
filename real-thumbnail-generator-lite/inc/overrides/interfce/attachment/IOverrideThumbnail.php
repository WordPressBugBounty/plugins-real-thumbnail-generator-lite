<?php

namespace DevOwl\RealThumbnailGenerator\overrides\interfce\attachment;

// @codeCoverageIgnoreStart
\defined('ABSPATH') or die('No script kiddies please!');
// Avoid direct file request
// @codeCoverageIgnoreEnd
/** @internal */
interface IOverrideThumbnail
{
    /**
     * Get schema for folder.
     *
     * @return string
     */
    public function getSchemaFolder();
    /**
     * Get schema for filename.
     *
     * @return string
     */
    public function getSchemaFilename();
    /**
     * Deletes image sizes of a set of post IDs.
     *
     * @param int|int[] $ids Array of IDs or single ID
     * @return false or Array of post IDs with \WP_Error or true
     */
    public function deleteUnused($ids);
}

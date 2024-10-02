<?php

namespace DevOwl\RealThumbnailGenerator\lite\attachment;

use DevOwl\RealThumbnailGenerator\attachment\Thumbnail as AttachmentThumbnail;
use WP_Error;
// @codeCoverageIgnoreStart
\defined('ABSPATH') or die('No script kiddies please!');
// Avoid direct file request
// @codeCoverageIgnoreEnd
/** @internal */
trait Thumbnail
{
    // Documented in IOverrideThumbnail
    public function getSchemaFolder()
    {
        return '';
    }
    // Documented in IOverrideThumbnail
    public function getSchemaFilename()
    {
        return AttachmentThumbnail::DEFAULT_SCHEMA_FILENAME;
    }
    // Documented in IOverrideThumbnail
    public function deleteUnused($ids)
    {
        return new WP_Error('rest_rtg_lite', \__('This feature is not available in the free version.', RTG_TD), ['status' => 500]);
    }
}

<?php

namespace DevOwl\RealThumbnailGenerator\editor\impl;

use DevOwl\RealThumbnailGenerator\editor\Extend;
use DevOwl\RealThumbnailGenerator\editor\GDExtend;
use WP_Thumb_Image_Editor_GD;
// @codeCoverageIgnoreStart
\defined('ABSPATH') or die('No script kiddies please!');
// Avoid direct file request
// @codeCoverageIgnoreEnd
/** @internal */
class Thumb_Image_Editor_GD extends WP_Thumb_Image_Editor_GD
{
    use Extend;
    use GDExtend;
    /**
     * C'tor.
     *
     * @param string $file
     */
    public function __construct($file)
    {
        parent::__construct($file);
        $this->extendWithAdapter();
    }
}

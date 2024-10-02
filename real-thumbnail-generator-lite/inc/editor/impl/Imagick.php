<?php

namespace DevOwl\RealThumbnailGenerator\editor\impl;

use DevOwl\RealThumbnailGenerator\editor\Extend;
use DevOwl\RealThumbnailGenerator\editor\ImagickExtend;
use WP_Image_Editor_Imagick;
// @codeCoverageIgnoreStart
\defined('ABSPATH') or die('No script kiddies please!');
// Avoid direct file request
// @codeCoverageIgnoreEnd
/** @internal */
class Imagick extends WP_Image_Editor_Imagick
{
    use Extend;
    use ImagickExtend;
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

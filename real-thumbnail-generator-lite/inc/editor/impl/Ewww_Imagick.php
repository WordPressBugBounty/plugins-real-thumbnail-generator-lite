<?php

namespace DevOwl\RealThumbnailGenerator\editor\impl;

use DevOwl\RealThumbnailGenerator\editor\Extend;
use DevOwl\RealThumbnailGenerator\editor\ImagickExtend;
use EWWWIO_Imagick_Editor;
// @codeCoverageIgnoreStart
\defined('ABSPATH') or die('No script kiddies please!');
// Avoid direct file request
// @codeCoverageIgnoreEnd
/** @internal */
class Ewww_Imagick extends EWWWIO_Imagick_Editor
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

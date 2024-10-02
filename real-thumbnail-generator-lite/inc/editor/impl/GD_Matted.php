<?php

namespace DevOwl\RealThumbnailGenerator\editor\impl;

use DevOwl\RealThumbnailGenerator\editor\Extend;
use DevOwl\RealThumbnailGenerator\editor\GDExtend;
use WP_Image_Editor_GD_Matted;
// @codeCoverageIgnoreStart
\defined('ABSPATH') or die('No script kiddies please!');
// Avoid direct file request
// @codeCoverageIgnoreEnd
/** @internal */
class GD_Matted extends WP_Image_Editor_GD_Matted
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

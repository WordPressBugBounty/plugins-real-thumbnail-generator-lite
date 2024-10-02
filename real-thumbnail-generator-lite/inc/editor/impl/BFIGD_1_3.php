<?php

namespace DevOwl\RealThumbnailGenerator\editor\impl;

use DevOwl\RealThumbnailGenerator\editor\Extend;
use DevOwl\RealThumbnailGenerator\editor\GDExtend;
use BFI_Image_Editor_GD_1_3;
// @codeCoverageIgnoreStart
\defined('ABSPATH') or die('No script kiddies please!');
// Avoid direct file request
// @codeCoverageIgnoreEnd
/** @internal */
class BFIGD_1_3 extends BFI_Image_Editor_GD_1_3
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

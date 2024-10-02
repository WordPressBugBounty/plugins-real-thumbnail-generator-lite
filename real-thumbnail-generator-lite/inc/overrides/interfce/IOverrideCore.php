<?php

namespace DevOwl\RealThumbnailGenerator\overrides\interfce;

use DevOwl\RealThumbnailGenerator\Vendor\DevOwl\Freemium\ICore;
// @codeCoverageIgnoreStart
\defined('ABSPATH') or die('No script kiddies please!');
// Avoid direct file request
// @codeCoverageIgnoreEnd
/** @internal */
interface IOverrideCore extends ICore
{
    /**
     * Additional c'tor.
     */
    public function overrideConstruct();
    /**
     * Additional init action.
     */
    public function overrideInit();
}

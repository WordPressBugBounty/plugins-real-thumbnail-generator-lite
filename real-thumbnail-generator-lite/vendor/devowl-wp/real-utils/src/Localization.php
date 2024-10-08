<?php

namespace DevOwl\RealThumbnailGenerator\Vendor\DevOwl\RealUtils;

use DevOwl\RealThumbnailGenerator\Vendor\MatthiasWeb\Utils\PackageLocalization;
// @codeCoverageIgnoreStart
\defined('ABSPATH') or die('No script kiddies please!');
// Avoid direct file request
// @codeCoverageIgnoreEnd
/**
 * Package localization for `real-utils` package.
 * @internal
 */
class Localization extends PackageLocalization
{
    /**
     * C'tor.
     */
    protected function __construct()
    {
        parent::__construct(REAL_UTILS_ROOT_SLUG, \dirname(__DIR__));
    }
    /**
     * Create instance.
     *
     * @codeCoverageIgnore
     */
    public static function instanceThis()
    {
        return new Localization();
    }
}

<?php

namespace DevOwl\RealThumbnailGenerator;

use DevOwl\RealThumbnailGenerator\Vendor\MatthiasWeb\Utils\Constants;
use DevOwl\RealThumbnailGenerator\Vendor\MatthiasWeb\Utils\Localization as UtilsLocalization;
use DevOwl\RealThumbnailGenerator\base\UtilsProvider;
// @codeCoverageIgnoreStart
\defined('ABSPATH') or die('No script kiddies please!');
// Avoid direct file request
// @codeCoverageIgnoreEnd
/**
 * i18n management for backend and frontend.
 * @internal
 */
class Localization
{
    use UtilsProvider;
    use UtilsLocalization;
    /**
     * Get the directory where the languages folder exists.
     *
     * @param string $type
     * @return string[]
     */
    protected function getPackageInfo($type)
    {
        if ($type === Constants::LOCALIZATION_BACKEND) {
            return [RTG_PATH . '/languages', RTG_TD];
        } else {
            return [RTG_PATH . '/' . Constants::LOCALIZATION_PUBLIC_JSON_I18N, RTG_TD];
        }
    }
}

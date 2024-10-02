<?php

namespace DevOwl\RealThumbnailGenerator;

use DevOwl\RealThumbnailGenerator\base\UtilsProvider;
// @codeCoverageIgnoreStart
\defined('ABSPATH') or die('No script kiddies please!');
// Avoid direct file request
// @codeCoverageIgnoreEnd
/** @internal */
class Util
{
    use UtilsProvider;
    private static $me = null;
    /**
     * Checks if a string starts with.
     *
     * @param string $haystack The string
     * @param string $needle Starts with
     */
    public function startsWith($haystack, $needle)
    {
        $length = \strlen($needle);
        return \substr($haystack, 0, $length) === $needle;
    }
    /**
     * Remove dir recursively.
     *
     * @param string $path
     */
    public function rmdirRecursively($path)
    {
        $empty = \true;
        foreach (\glob($path . \DIRECTORY_SEPARATOR . '*') as $file) {
            $empty &= \is_dir($file) && $this->rmdirRecursively($file);
        }
        return $empty && \rmdir($path);
    }
    /**
     * Get singleton instance.
     *
     * @return Thumbnail
     */
    public static function getInstance()
    {
        return self::$me === null ? self::$me = new \DevOwl\RealThumbnailGenerator\Util() : self::$me;
    }
}

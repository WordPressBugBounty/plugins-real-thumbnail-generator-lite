<?php

namespace DevOwl\RealThumbnailGenerator\editor;

// @codeCoverageIgnoreStart
\defined('ABSPATH') or die('No script kiddies please!');
// Avoid direct file request
// @codeCoverageIgnoreEnd
/** @internal */
trait GDExtend
{
    /**
     * Override "_resize" of editor handler.
     *
     * @param int $max_w
     * @param int $max_h
     * @param boolean $crop
     */
    protected function _resize($max_w, $max_h, $crop = \false)
    {
        $this->adapter->max_w = $max_w;
        $this->adapter->max_h = $max_h;
        $this->adapter->crop = $crop;
        $resized = $this->adapter->checkResize($max_w, $max_h, $crop);
        if (\is_wp_error($resized)) {
            return $resized;
        }
        return \is_array($resized) ? \wp_imagecreatetruecolor(1, 1) : parent::_resize($max_w, $max_h, $crop);
    }
}

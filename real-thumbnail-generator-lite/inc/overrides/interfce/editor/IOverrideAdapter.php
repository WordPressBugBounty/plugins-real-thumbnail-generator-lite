<?php

namespace DevOwl\RealThumbnailGenerator\overrides\interfce\editor;

// @codeCoverageIgnoreStart
\defined('ABSPATH') or die('No script kiddies please!');
// Avoid direct file request
// @codeCoverageIgnoreEnd
/** @internal */
interface IOverrideAdapter
{
    /**
     * Additional c'tor.
     */
    public function overrideConstruct();
    /**
     * This function must be called in the resize method of the editor. It checks,
     * if the given thumbnail size should be skipped.
     *
     * @param int $max_w
     * @param int $max_h
     * @param boolean|array $crop
     * @return array
     */
    public function checkResize($max_w, $max_h, $crop);
    /**
     * Builds an output filename based on current file, and adding proper suffix
     *
     * @param string $size
     * @param string $dest_path
     * @param string $extension
     * @return string
     */
    public function generate_filename($size, $dest_path = null, $extension = null);
}

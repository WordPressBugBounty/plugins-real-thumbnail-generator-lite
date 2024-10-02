<?php

namespace DevOwl\RealThumbnailGenerator\lite\editor;

// @codeCoverageIgnoreStart
\defined('ABSPATH') or die('No script kiddies please!');
// Avoid direct file request
// @codeCoverageIgnoreEnd
/** @internal */
trait Adapter
{
    // Documented in IOverrideAdapter
    public function overrideConstruct()
    {
        // Silence is golden.
    }
    // Documented in IOverrideAdapter
    public function checkResize($max_w, $max_h, $crop)
    {
        return \true;
        // Use Standard
    }
    // Documented in IOverrideAdapter
    public function generate_filename($size, $dest_path = null, $extension = null)
    {
        return \false;
        // Use standard
    }
}

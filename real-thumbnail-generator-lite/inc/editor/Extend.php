<?php

namespace DevOwl\RealThumbnailGenerator\editor;

// @codeCoverageIgnoreStart
\defined('ABSPATH') or die('No script kiddies please!');
// Avoid direct file request
// @codeCoverageIgnoreEnd
/**
 * Use this trait in your extended class for the below methods.
 * You have to redefine the resize / _resize method yourself or use GDExtend / ImagickExtend.
 * @internal
 */
trait Extend
{
    /**
     * Adapter instance.
     *
     * @var Adapter
     */
    private $adapter;
    /**
     * Extend the current instance with an own adapter.
     */
    public function extendWithAdapter()
    {
        $this->adapter = new \DevOwl\RealThumbnailGenerator\editor\Adapter($this, $this->file);
    }
    /**
     * Generate a file name for the current image thumbnail.
     *
     * @param string $suffix
     * @param string $dest_path
     * @param string $extension
     * @return string
     */
    public function generate_filename($suffix = null, $dest_path = null, $extension = null)
    {
        $result = $this->adapter->generate_filename($this->size, $dest_path, $extension);
        if ($result === \false) {
            // There was an error, use default WP naming
            $result = parent::generate_filename($suffix, $dest_path, $extension);
        }
        return $result;
    }
    /**
     * Override multi_resize of editor handler. This is used for example when editing an image in media library (cropping, flip, ...).
     *
     * @param string[] $sizes
     */
    public function multi_resize($sizes)
    {
        $sizes = parent::multi_resize($sizes);
        $temporaryMetadata = $this->adapter->applyTemporaryMetadata([], $sizes);
        // Apply temporary metadata to each size so Adapter::wp_generate_attachment_metadata can work with
        foreach ($sizes as &$size) {
            $size = \array_merge($size, $temporaryMetadata);
        }
        return $sizes;
    }
    /**
     * Create an image sub-size and return the image meta data value for it.
     *
     * @since 5.3.0
     *
     * @param array $size_data
     * @return array|WP_Error
     * @see https://developer.wordpress.org/reference/classes/wp_image_editor_imagick/make_subsize/
     */
    public function make_subsize($size_data)
    {
        $saved = parent::make_subsize($size_data);
        return $this->adapter->applyTemporaryMetadata($saved);
    }
    /**
     * Override _save of editor handler.
     *
     * @param string $image
     * @param string $filename
     * @param string $mime_type
     */
    protected function _save($image, $filename = null, $mime_type = null)
    {
        $resized = $this->adapter->checkSave();
        if ($resized === \false) {
            $resized = parent::_save($image, $filename, $mime_type);
            // Call the original function to save the image to disk
        } else {
            $this->generate_filename();
            // We must generate the filename to fill the data
        }
        return $this->adapter->_save($resized, $image, $filename, $mime_type);
    }
}

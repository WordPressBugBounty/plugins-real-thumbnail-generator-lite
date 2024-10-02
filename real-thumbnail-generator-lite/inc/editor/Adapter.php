<?php

namespace DevOwl\RealThumbnailGenerator\editor;

use DevOwl\RealThumbnailGenerator\attachment\Thumbnail;
use DevOwl\RealThumbnailGenerator\base\UtilsProvider;
use DevOwl\RealThumbnailGenerator\lite\editor\Adapter as EditorAdapter;
use DevOwl\RealThumbnailGenerator\overrides\interfce\editor\IOverrideAdapter;
use DevOwl\RealThumbnailGenerator\Util;
use WP_Error;
// @codeCoverageIgnoreStart
\defined('ABSPATH') or die('No script kiddies please!');
// Avoid direct file request
// @codeCoverageIgnoreEnd
/**
 * Adapter for GD and Imagick
 * @internal
 */
class Adapter implements IOverrideAdapter
{
    use UtilsProvider;
    use EditorAdapter;
    public static $lastIsPdf = \false;
    public static $pdfJpgRegex = '/-pdf(-\\d+)?\\.jpg$/m';
    public static $lastFilePathes = [];
    // @see this::generate_filename() The last generated filenames relative to the file (size => file)
    public static $lastAttachmentId = null;
    public $file;
    public $fileInfo;
    /**
     * Current registered size
     */
    public $max_w;
    public $max_h;
    public $crop;
    public $imageEditor;
    public $rtgEditor;
    public $rtgThumbnail;
    public $check;
    // @see attachment\Thumbnail::check result
    public $isForceNew;
    public $isRegenerate;
    public $schemas;
    public $usePdfSizes;
    public $doResizeForThisThumbnail;
    // True when we do the resize, array when it should be skipped
    /**
     * General checks for this generation
     *
     * @param mixed $imageEditor
     * @param string $file
     */
    public function __construct($imageEditor, $file)
    {
        $this->file = $file;
        $this->fileInfo = \pathinfo($file);
        // Check if PDF
        $isPdf = $this->fileInfo['extension'] === 'pdf';
        $this->usePdfSizes = self::$lastIsPdf && \preg_match_all(self::$pdfJpgRegex, $file, $matches, \PREG_SET_ORDER, 0) > 0;
        self::$lastIsPdf = $isPdf;
        $this->imageEditor = $imageEditor;
        $this->rtgEditor = \DevOwl\RealThumbnailGenerator\editor\Editor::getInstance();
        $this->rtgThumbnail = Thumbnail::getInstance();
        $this->check = $this->rtgEditor->activeRegenerationCheck;
        $this->isForceNew = $this->rtgEditor->activeRegenerationForceNew;
        $this->isRegenerate = \is_array($this->check);
        $this->schemas = $this->getSchemas();
        if ($isPdf && isset($this->check)) {
            $this->unlinkPdfPreview($file);
        }
        $this->overrideConstruct();
        $this->doResizeForThisThumbnail = \true;
    }
    /**
     * Delete PDF thumbnail.
     *
     * @param string $file
     */
    private function unlinkPdfPreview($file)
    {
        // Check if a previous JPG of the PDF exists
        if (isset($this->check['available']['full'])) {
            \unlink(\dirname($this->check['attachedFile'] . '/' . $this->check['available']['full']['file']));
        }
        // Check if preview JPG of the PDF exists (without subfolders)
        $dirname = \dirname($file) . '/';
        $ext = '.' . \pathinfo($file, \PATHINFO_EXTENSION);
        $preview_file = $dirname . \wp_basename($file, $ext) . '-pdf.jpg';
        \file_exists($preview_file) && \unlink($preview_file);
    }
    /**
     * Hook into "_save" of editor.
     *
     * @param array $resized Result of this::imageEditor->_save
     * @param resource $image
     * @param string|null $filename
     * @param string|null $mime_type
     * @return WP_Error|array
     */
    public function _save($resized, $image, $filename = null, $mime_type = null)
    {
        if (\is_array($resized)) {
            list($schema_folder, $schema_filename) = $this->schemas;
            // If PDF allow the generation for the preview file
            /*if (self::$lastIsPdf) {
            			// Get width and height
            			$wh = explode("x", $this->imageEditor->get_suffix());
            			$this->generate_filename(["width" => $wh[0], "height" => $wh[1]]);
            		}*/
            $generate = $this->rtgEditor->generate($schema_folder);
            if (!$this->rtgEditor->isValid($generate)) {
                return $resized;
            }
            $resultFile = \ltrim(\trailingslashit($generate) . $resized['file'], '/');
            $resized['file'] = $resultFile;
        }
        return $resized;
    }
    /**
     * This function must be called in the save method of the editor. It checks,
     * if the given thumbnail size is.
     *
     * @return array|false Row for metadata or false when parent save method is needed
     */
    public function checkSave()
    {
        // The file resize was skipped
        if (\is_array($this->doResizeForThisThumbnail)) {
            // Create path attribute
            $path = \dirname($this->file) . '/' . $this->doResizeForThisThumbnail['file'];
            $resized = $this->doResizeForThisThumbnail;
            $resized = \array_merge(['path' => $path], $resized);
            $resized['file'] = \basename($resized['file']);
            // Reset the filename to basename, because the _save function creates the dirname
            // We must generate the filename to fill the data
            // CALL THIS IN YOUR METHOD $this->generate_filename();
            return $resized;
        } else {
            return \false;
        }
    }
    /**
     * Get schemas from from current processing image.
     *
     * @return string[] 0 => Schema folder, 1 => Schema filename
     */
    private function getSchemas()
    {
        // Check if regenerate or not
        if ($this->isForceNew === \true) {
            return [$this->rtgThumbnail->getSchemaFolder(), $this->rtgThumbnail->getSchemaFilename()];
        } elseif ($this->isRegenerate) {
            return [$this->check['schemaf'], $this->check['schema']];
        } else {
            return [$this->rtgThumbnail->getSchemaFolder(), $this->rtgThumbnail->getSchemaFilename()];
        }
    }
    /**
     * While regenerating save temporary data to the metadata array that helps:
     *
     * - Remove the old thumbnails if given.
     * - Save used schema in metadata
     *
     * Since WordPress 5.3 (#3upazm) multi_resize is no longer used, there are now
     * two ways of generating thumbnails:
     *
     * - multi_resize
     * - make_subsize
     *
     * See wp_generate_attachment_metadata() below for more information.
     *
     * @param array $arr
     * @param array $sizes
     */
    public function applyTemporaryMetadata($arr, $sizes = null)
    {
        if (\is_array($arr)) {
            $arr['___schemas'] = $this->schemas;
            $arr['___forceDelete'] = [];
            // Check for deletable when forcing the new schema
            if (isset($this->check) && \is_array($this->check['available']) && \count($this->check['available']) > 0 && $this->isForceNew) {
                $old = $this->check['available'];
                // Since WP 5.3 each sizes is iteratively updated so we need to get the metadata again from cache to obtain sizes
                if ($sizes === null) {
                    $sizes = \wp_get_attachment_metadata($this->check['id'])['sizes'];
                }
                foreach ($old as $key => $value) {
                    // Search in the new for this key
                    if (isset($sizes[$key])) {
                        $new = $sizes[$key];
                        $oldFile = \trim(\trim($value['file'], '/'));
                        $newFile = \trim(\trim($new['file'], '/'));
                        if (\strcmp(\strtolower($oldFile), \strtolower($newFile)) !== 0) {
                            $arr['___forceDelete'][] = $oldFile;
                        }
                    }
                }
            }
        }
        return $arr;
    }
    /**
     * Save latest inserted attachment post ID for Adapter::generate_filename.
     *
     * @param int $postId
     */
    public static function add_attachment($postId)
    {
        self::$lastAttachmentId = $postId;
    }
    /**
     * Save the metadata of the schema. We can assume that the schema is the
     * same for all thumbnails of an image.
     *
     * @param array $metadata
     * @param int $attachment_id
     */
    public static function wp_generate_attachment_metadata($metadata, $attachment_id)
    {
        if (!\is_array($metadata) || !isset($metadata['sizes'])) {
            return $metadata;
        }
        $prepareArray = null;
        // Find ___schemas and ___forceDelete in array
        if (isset($metadata['sizes']['___schemas']) && \is_array($metadata['sizes']['___schemas'])) {
            // Legacy way WP < 5.3 with multi_resize (it is directly saved to first level metadata)
            $prepareArray = $metadata['sizes'];
            unset($metadata['sizes']['___schemas']);
            unset($metadata['sizes']['___forceDelete']);
        } else {
            // New way since WP 5.3 with make_subsize (it is saved in each size, we need to pick one and clear the rest)
            foreach ($metadata['sizes'] as $key => $size) {
                if (isset($size['___schemas']) && \is_array($size['___schemas'])) {
                    $prepareArray = $size;
                    // do not break, because we need the last entry (it has the most ___forceDelete entries)
                    unset($metadata['sizes'][$key]['___schemas']);
                    unset($metadata['sizes'][$key]['___forceDelete']);
                }
            }
        }
        if ($prepareArray !== null) {
            // Extract temporary data and clear from metadata
            list($schema_folder, $schema_filename) = $prepareArray['___schemas'];
            $forceDelete = $prepareArray['___forceDelete'];
            // Save the schema in the database metadata
            $metadata['schemaf'] = $schema_folder;
            $metadata['schema'] = $schema_filename;
            // Get the main dir of the attachment
            $hasMetaFile = isset($metadata['file']);
            $file = $hasMetaFile ? $metadata['file'] : \get_attached_file($attachment_id);
            if (isset($file)) {
                $fileDir = \trim(\dirname($file), '/');
                $uploads = \wp_upload_dir();
                $baseDir = $uploads['basedir'];
                // Check when it is the attached file it must be the same uploads folder
                if (!$hasMetaFile && Util::getInstance()->startsWith($file, $baseDir)) {
                    $fileDir = \trim(\dirname(\substr($file, (\strlen($file) - \strlen($baseDir)) * -1)), '/');
                }
                // Iterate deletable files
                $deletableFolders = [];
                foreach ($forceDelete as $toDelete) {
                    $pathToDelete = \trailingslashit(\trailingslashit($baseDir) . $fileDir) . $toDelete;
                    if (\file_exists($pathToDelete)) {
                        @\unlink($pathToDelete);
                        // Add it to the deletable folders
                        $dirname = \pathinfo($toDelete)['dirname'];
                        if ($dirname !== '.' && $dirname !== '..' && ($dirname = \explode('/', $dirname))) {
                            // phpcs:disable Generic.PHP.ForbiddenFunctions
                            $deletableFolders[] = \realpath(\path_join(\path_join($baseDir, $fileDir), $dirname[0]));
                            // phpcs:enable Generic.PHP.ForbiddenFunctions
                        }
                    }
                }
                // Delete thumbnails folder
                $dirname = \array_unique($deletableFolders);
                \array_walk($dirname, [Util::getInstance(), 'rmdirRecursively']);
            }
        }
        return $metadata;
    }
    /**
     * Generate filename for a given file.
     *
     * @param string $file
     * @param int $max_w
     * @param int $max_h
     * @param boolean|array $crop
     * @param string $size
     * @param string $dest_path
     * @param string $extension
     * @example \DevOwl\RealThumbnailGenerator\editor\Adapter::getFullPath($file, $w, $h, $crop, ['width' => $w, 'height' => $h])
     */
    public static function getFullPath($file, $max_w, $max_h, $crop, $size, $dest_path = null, $extension = null)
    {
        $adapter = new \DevOwl\RealThumbnailGenerator\editor\Adapter(null, $file);
        $adapter->max_w = $max_w;
        $adapter->max_h = $max_h;
        $adapter->crop = $crop;
        return $adapter->generate_filename($size, $dest_path, $extension);
    }
    /**
     * Compatibility.
     *
     * @param string $destfilename
     * @param string $file
     * @param int $w
     * @param int $h
     * @param boolean|array $crop
     * @see https://github.com/vollyimnetz/crop-thumbnails
     */
    public static function crop_thumbnails_filename($destfilename, $file, $w, $h, $crop)
    {
        return self::getFullPath($file, $w, $h, $crop, ['width' => $w, 'height' => $h]);
    }
    /**
     * Compatibility.
     *
     * @param array $metadata
     */
    public static function crop_thumbnails_before_update_metadata($metadata)
    {
        foreach ($metadata['sizes'] as $key => &$value) {
            if (isset(self::$lastFilePathes[$key])) {
                $value['file'] = self::$lastFilePathes[$key];
            }
        }
        return $metadata;
    }
}

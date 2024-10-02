<?php

namespace DevOwl\RealThumbnailGenerator\editor;

use DevOwl\RealThumbnailGenerator\attachment\Thumbnail;
use DevOwl\RealThumbnailGenerator\base\UtilsProvider;
use DevOwl\RealThumbnailGenerator\editor\impl\BFIGD_1_3;
use DevOwl\RealThumbnailGenerator\editor\impl\BFIImagick_1_3;
use DevOwl\RealThumbnailGenerator\editor\impl\Ewww_GD;
use DevOwl\RealThumbnailGenerator\editor\impl\Ewww_Imagick;
use DevOwl\RealThumbnailGenerator\editor\impl\GD;
use DevOwl\RealThumbnailGenerator\editor\impl\GD_Matted;
use DevOwl\RealThumbnailGenerator\editor\impl\GOPP_GS;
use DevOwl\RealThumbnailGenerator\editor\impl\Imagick;
use DevOwl\RealThumbnailGenerator\editor\impl\Thumb_Image_Editor_GD;
use DevOwl\RealThumbnailGenerator\editor\impl\Thumb_Image_Editor_Imagick;
// @codeCoverageIgnoreStart
\defined('ABSPATH') or die('No script kiddies please!');
// Avoid direct file request
// @codeCoverageIgnoreEnd
/**
 * Editor handler.
 * @internal
 */
class Editor
{
    use UtilsProvider;
    private static $me = null;
    /**
     * Provide two demo data to generate a test subfolder.
     * Here you can also see which variables are available within
     * the two percentage placeholders: %name% for example.
     */
    public $demo = ['id' => [90, 100, 203, 303, 1004, 998], 'size-identifier' => ['thumbnail', 'thumbnail', 'medium', 'large', 'custom', 'custom2'], 'identifier-width' => ['150', '150', '300', '1024', '300', '800'], 'identifier-height' => ['150', '150', '300', '1024', '1024', '940'], 'name' => ['bike', 'car', 'car', 'car', 'car', 'car'], 'extension' => ['jpg', 'jpg', 'jpg', 'jpg', 'jpg', 'jpg'], 'image-width' => ['135', '135', '250', '1000', '250', '640'], 'image-height' => ['135', '135', '250', '1000', '640', '800']];
    public $wrapping = ['id' => '<span>%id%</span>', 'size-identifier' => '<span>%size-identifier%</span>', 'identifier-width' => '<span>%identifier-width%</span>', 'identifier-height' => '<span>%identifier-height%</span>', 'name' => '<span>%name%</span>', 'extension' => '<span>%extension%</span>', 'image-width' => '<span>%image-width%</span>', 'image-height' => '<span>%image-height%</span>'];
    public $unknownLabel = '_UNKNOWN_';
    /**
     * Save the active regenerating check to determine, if the post
     * already exist.
     */
    public $activeRegenerationCheck = null;
    /**
     * Save the force new boolean if we want to regenerate a thumbnail.
     */
    public $activeRegenerationForceNew = null;
    private $setDemo = \false;
    /**
     * The used data for the replacement, see this::wrapping for example
     */
    public $data = [];
    /**
     * C'tor.
     */
    private function __construct()
    {
        // Silence is golden.
    }
    /**
     * Add extended editors to the WordPress core
     * to handle the media library.
     *
     * @param array $implementations
     */
    public function wp_image_editors($implementations)
    {
        $replace = [
            'WP_Image_Editor_Imagick' => Imagick::class,
            'WP_Image_Editor_GD' => GD::class,
            // Custom editors with lazy loading
            'WP_Image_Editor_GD_Matted' => ['replace' => GD_Matted::class],
            'BFI_Image_Editor_Imagick_1_3' => ['replace' => BFIImagick_1_3::class],
            'BFI_Image_Editor_GD_1_3' => ['replace' => BFIGD_1_3::class],
            'WP_Thumb_Image_Editor_Imagick' => ['replace' => Thumb_Image_Editor_Imagick::class],
            'WP_Thumb_Image_Editor_GD' => ['replace' => Thumb_Image_Editor_GD::class],
            'EWWWIO_Imagick_Editor' => ['replace' => Ewww_Imagick::class],
            'EWWWIO_GD_Editor' => ['replace' => Ewww_GD::class],
            'GOPP_Image_Editor_GS' => ['replace' => GOPP_GS::class],
        ];
        // Replace implementations
        foreach ($replace as $key => $value) {
            if (($idx = \array_search($key, $implementations, \true)) !== \false) {
                $implementations[$idx] = \is_array($value) ? $value['replace'] : $value;
            }
        }
        return \array_values($implementations);
    }
    /**
     * Validate the thumbnail configuration.
     *
     * @param string $patternSubfolder The string with placeholders %size-identifier%/%identifier-width%x%identifier-height%
     * @param string $patternFilename The string with placeholders %name%-%size-identifier%.%extension%
     */
    public function testThumbnailPath($patternSubfolder, $patternFilename)
    {
        $patternSubfolder = empty($patternSubfolder) ? '' : $patternSubfolder;
        $patternFilename = empty($patternFilename) ? Thumbnail::DEFAULT_SCHEMA_FILENAME : $patternFilename;
        $folders = [];
        $filenames = [];
        $pathes = [];
        $errors = [];
        $this->data = $this->demo;
        for ($i = 1; $i <= \count($this->demo['id']); $i++) {
            // Generate data
            $folder = $this->generate($patternSubfolder, $i);
            $filename = $this->generate($patternFilename, $i, 'replace_filename');
            // Catch errors
            if (!empty($filename) && empty($filename)) {
                $errors['filename_valid'] = \__('The filename is not valid', RTG_TD);
            }
            if (!empty($filename) && \strpos($filename, '.jpg') === \false) {
                $errors['filename_ext'] = \__('The filename has no extension', RTG_TD);
            }
            $join = \trailingslashit($folder) . $filename;
            if (!$this->isValid($join)) {
                $errors['filename_ext'] = \__('The pattern is invalid', RTG_TD);
            }
            $folders[] = $folder;
            $filenames[] = $filename;
            $pathes[] = $join;
        }
        // The filenames must be unique!
        if (\count($pathes) !== \count(\array_unique($pathes))) {
            $errors['filename_unique'] = 'The filename pattern results not in an explicitness';
        }
        // Send result, is always successfully, but can contain errors
        return ['folders' => $folders, 'filenames' => $filenames, 'pathes' => $pathes, 'errors' => \array_unique($errors)];
    }
    /**
     * Generate a subfolder/filename string from a given mode.
     *
     * The following placeholders are possible:
     *
     * $callback = "replace_subfolder"
     *  %size-identifier%: The name of the size ("medium", "full", "large", ...)
     *  %identifier-width%: The width of the identifier
     *  %identifier-height%: The height of the identifier
     *
     * $callback = "replace_filename"
     *  Same as above
     *  %name%: Name of the file
     *  %extension%: The extension of the file ("jpg", "png", ...)
     *  %image-width%: The width of the identifier
     *  %image-height%: The height of the identifier
     *
     * @param string $string The string with placeholders
     * @param boolean|array $mode Defines if it is the demo array or a custom array where we can replace
     * @param callable $callback The replace callback of this class
     */
    public function generate($string, $mode = \false, $callback = 'replace_subfolder')
    {
        $this->setDemo($mode);
        $result = \preg_replace_callback('/\\%(.*)\\%/U', [$this, $callback], $string);
        if (\is_string($result)) {
            return \trim($result, '/');
        } else {
            return '';
        }
    }
    /**
     * Wrap the placeholders with <span></span> tags.
     *
     * @param string $string
     */
    public function wrapSpan($string)
    {
        $this->data = $this->wrapping;
        return $this->generate($string);
    }
    /**
     * The main replacer for the subfolder.
     *
     * @param array $match
     */
    public function replace_subfolder($match)
    {
        $id = \trim(\strtolower($match[1]));
        // Demo data
        if (!\is_object($this->setDemo) && isset($this->data[$id])) {
            $useData = $this->data[$id];
            if (\is_array($useData)) {
                return $useData[$this->setDemo - 1];
            } else {
                return $useData;
            }
        }
        return $this->unknownLabel;
    }
    /**
     * The main replacer for the filename.
     *
     * @param array $match
     */
    public function replace_filename($match)
    {
        // Use variables from subfolder
        $fromSubfolder = $this->replace_subfolder($match);
        if ($fromSubfolder !== $this->unknownLabel) {
            return $fromSubfolder;
        }
        // Use variables from the filename
        $match = \trim(\strtolower($match[1]));
        if (!\is_object($this->setDemo) && isset($this->data[$match])) {
            $useData = $this->data[$match];
            if (\is_array($useData)) {
                return $useData[$this->setDemo - 1];
            } else {
                return $useData;
            }
        }
        return $this->unknownLabel;
    }
    /**
     * Check if the generated scheme path is valid.
     *
     * @param string $path
     */
    public function isValid($path)
    {
        return \strpos($path, $this->unknownLabel) === \false;
    }
    /**
     * Set demo content instead of using current image.
     *
     * @param array|boolean $demo
     */
    public function setDemo($demo = null)
    {
        $old = $this->setDemo;
        if ($demo !== null) {
            $this->setDemo = $demo;
        }
        return $old;
    }
    /**
     * Get singleton instance.
     *
     * @return Editor
     */
    public static function getInstance()
    {
        return self::$me === null ? self::$me = new \DevOwl\RealThumbnailGenerator\editor\Editor() : self::$me;
    }
}

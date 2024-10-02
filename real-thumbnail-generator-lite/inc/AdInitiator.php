<?php

namespace DevOwl\RealThumbnailGenerator;

use DevOwl\RealThumbnailGenerator\Vendor\DevOwl\RealUtils\AbstractInitiator;
use DevOwl\RealThumbnailGenerator\Vendor\DevOwl\RealUtils\WelcomePage;
use DevOwl\RealThumbnailGenerator\base\UtilsProvider;
use DevOwl\RealThumbnailGenerator\view\View;
// @codeCoverageIgnoreStart
\defined('ABSPATH') or die('No script kiddies please!');
// Avoid direct file request
// @codeCoverageIgnoreEnd
/**
 * Initiate real-utils functionality.
 * @internal
 */
class AdInitiator extends AbstractInitiator
{
    use UtilsProvider;
    /**
     * Documented in AbstractInitiator.
     *
     * @codeCoverageIgnore
     */
    public function getPluginBase()
    {
        return $this;
    }
    /**
     * Documented in AbstractInitiator.
     *
     * @codeCoverageIgnore
     */
    public function getPluginAssets()
    {
        return $this->getCore()->getAssets();
    }
    /**
     * Documented in AbstractInitiator.
     *
     * @codeCoverageIgnore
     */
    public function getRateLink()
    {
        return $this->isPro() ? 'https://devowl.io/go/codecanyon/real-thumbnail-generator/rate' : 'https://devowl.io/go/wordpress-org/real-thumbnail-generator/rate';
    }
    /**
     * Documented in AbstractInitiator.
     *
     * @codeCoverageIgnore
     */
    public function getKeyFeatures()
    {
        $isPro = $this->isPro();
        return [['image' => $this->getAssetsUrl('feature-bulk.jpg'), 'title' => \__('Regenerate all your media in bulk', RTG_TD), 'description' => \__('Navigate to your media library and press the "Regenerate Thumbnails" button. A new dialog will open where you can regenerate your all thumbnails in media library with one click. Fast and efficient!', RTG_TD), 'available_in' => $isPro ? null : [['Lite', WelcomePage::COLOR_BADGE_LITE], ['Pro', WelcomePage::COLOR_BADGE_PRO]]], ['image' => $this->getAssetsUrl('feature-physical.gif'), 'title' => \__('Custom thumbnail upload structure', RTG_TD), 'description' => \__('Have you ever looked at the URL paths of your media uploads? Not really expressive. But this is exactly what is important to ensure that your images and the pages on which they are used get a good ranking in search engines. Improve your ranking with physically reordered uploads!', RTG_TD), 'available_in' => $isPro ? null : [['Pro', WelcomePage::COLOR_BADGE_PRO]]], ['image' => $this->getAssetsUrl('feature-delete-unused.gif'), 'title' => \__('Rich meta data and detect unused files', RTG_TD), 'description' => \__('When you open a single media file, you can view a list of all registered thumbnail sizes.', RTG_TD), 'available_in' => $isPro ? null : [['Lite', WelcomePage::COLOR_BADGE_LITE], ['Pro', WelcomePage::COLOR_BADGE_PRO]], 'highlight_badge' => $isPro ? null : ['Pro', WelcomePage::COLOR_BADGE_PRO, \__('In the PRO version you can also delete unused thumbnail sizes - this can be done for one or all files with one click.', RTG_TD)]]];
    }
    /**
     * Documented in AbstractInitiator.
     *
     * @codeCoverageIgnore
     */
    public function getHeroButton()
    {
        return [\__('Regenerate Thumbnails', RTG_TD), View::getInstance()->getThumbnailsPageUrl()];
    }
    /**
     * Documented in AbstractInitiator.
     *
     * @param boolean $isFirstTime
     * @codeCoverageIgnore
     */
    public function getNextRatingPopup($isFirstTime)
    {
        return $isFirstTime ? 0 : ($this->isPro() ? \strtotime('+90 days') : parent::getNextRatingPopup($isFirstTime));
    }
}

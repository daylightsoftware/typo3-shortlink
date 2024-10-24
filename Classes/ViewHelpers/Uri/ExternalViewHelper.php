<?php


namespace SUDHAUS7\Shortcutlink\ViewHelpers\Uri;

use SUDHAUS7\Shortcutlink\Service\ShortlinkService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

class ExternalViewHelper extends AbstractViewHelper
{
    /**
     * Initialize arguments
     */
    public function initializeArguments()
    {
        $this->registerArgument('uri', 'string', 'target URI', true);
        $this->registerArgument('defaultScheme', 'string', 'scheme the href attribute will be prefixed with if specified $uri does not contain a scheme already', false, 'https');

        $this->registerArgument('chainToUserid', 'int', 'Only this user is allowed to use this shortlink', false, 0);
    }

    /**
     * @param array $arguments
     * @param \Closure $renderChildrenClosure
     * @param RenderingContextInterface $renderingContext
     * @return string
     */
    public static function renderStatic(array $arguments, \Closure $renderChildrenClosure, RenderingContextInterface $renderingContext)
    {
        $uri = parent::renderStatic($arguments, $renderChildrenClosure, $renderingContext);
        /** @var ShortlinkService $shortener */
        $shortener = GeneralUtility::makeInstance(ShortlinkService::class);

        $shortener->setUrl($uri);
        $shortener->setFeuser($arguments['chainToUserid']);

        return $shortener->getShorturlWithDomain();
    }
}

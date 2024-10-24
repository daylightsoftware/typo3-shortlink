<?php


namespace SUDHAUS7\Shortcutlink\ViewHelpers\Link;

use SUDHAUS7\Shortcutlink\Service\ShortlinkService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractTagBasedViewHelper;

class ExternalViewHelper extends AbstractTagBasedViewHelper
{
    protected $tagName = 'a';
    /**
     * Initialize arguments
     */
    public function initializeArguments()
    {
        parent::initializeArguments();
        $this->registerArgument('uri', 'string', 'The URI that will be put in the href attribute of the rendered link tag', true);
        $this->registerArgument('defaultScheme', 'string', 'Scheme the href attribute will be prefixed with if specified $uri does not contain a scheme already', false, 'https');
        $this->registerUniversalTagAttributes();
        $this->registerTagAttribute('name', 'string', 'Specifies the name of an anchor');
        $this->registerTagAttribute('rel', 'string', 'Specifies the relationship between the current document and the linked document');
        $this->registerTagAttribute('rev', 'string', 'Specifies the relationship between the linked document and the current document');
        $this->registerTagAttribute('target', 'string', 'Specifies where to open the linked document');

        $this->registerArgument('chainToUserid', 'int', 'Only this user is allowed to use this shortlink', false, 0);
    }

    /**
     * @return string Rendered link
     */
    public function render()
    {
        $uri = $this->arguments['uri'];
        $defaultScheme = $this->arguments['defaultScheme'];

        $scheme = parse_url($uri, PHP_URL_SCHEME);
        if ($scheme === null && $defaultScheme !== '') {
            $uri = $defaultScheme.'://'.$uri;
        }
        /** @var ShortlinkService $shortener */
        $shortener = GeneralUtility::makeInstance(ShortlinkService::class);

        $shortener->setUrl($uri);
        $shortener->setFeuser($this->arguments['chainToUserid']);

        $this->tag->addAttribute('href', $shortener->getShorturlWithDomain());
        $this->tag->setContent($this->renderChildren());
        $this->tag->forceClosingTag(true);

        return $this->tag->render();
    }
}

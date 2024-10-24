<?php


namespace SUDHAUS7\Shortcutlink\ViewHelpers\Uri;

use Psr\Http\Message\ServerRequestInterface;
use SUDHAUS7\Shortcutlink\Service\ShortlinkService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\HttpUtility;
use TYPO3\CMS\Extbase\Mvc\RequestInterface as ExtbaseRequestInterface;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder as ExtbaseUriBuilder;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Typolink\LinkFactory;
use TYPO3\CMS\Frontend\Typolink\UnableToLinkException;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

class ActionViewHelper extends AbstractViewHelper
{
    /**
     * Initialize arguments
     */
    public function initializeArguments()
    {
        $this->registerArgument('action', 'string', 'Target action');
        $this->registerArgument('arguments', 'array', 'Arguments', false, []);
        $this->registerArgument('controller', 'string', 'Target controller. If NULL current controllerName is used');
        $this->registerArgument('extensionName', 'string', 'Target Extension Name (without `tx_` prefix and no underscores). If NULL the current extension name is used');
        $this->registerArgument('pluginName', 'string', 'Target plugin. If empty, the current plugin name is used');
        $this->registerArgument('pageUid', 'int', 'Target page. See TypoLink destination');
        $this->registerArgument('pageType', 'int', 'Type of the target page. See typolink.parameter', false, 0);
        $this->registerArgument('noCache', 'bool', 'Set this to disable caching for the target page. You should not need this.', false);
        $this->registerArgument('language', 'string', 'link to a specific language - defaults to the current language, use a language ID or "current" to enforce a specific language', false);
        $this->registerArgument('section', 'string', 'The anchor to be added to the URI', false, '');
        $this->registerArgument('format', 'string', 'The requested format, e.g. ".html', false, '');
        $this->registerArgument('linkAccessRestrictedPages', 'bool', 'If set, links pointing to access restricted pages will still link to the page even though the page cannot be accessed.', false, false);
        $this->registerArgument('additionalParams', 'array', 'additional query parameters that won\'t be prefixed like $arguments (overrule $arguments)', false, []);
        $this->registerArgument('absolute', 'bool', 'If set, an absolute URI is rendered', false, false);
        $this->registerArgument('addQueryString', 'string', 'If set, the current query parameters will be kept in the URL. If set to "untrusted", then ALL query parameters will be added. Be aware, that this might lead to problems when the generated link is cached.', false, false);
        $this->registerArgument('argumentsToBeExcludedFromQueryString', 'array', 'arguments to be removed from the URI. Only active if $addQueryString = TRUE', false, []);
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
    protected static function renderFrontendLinkWithCoreContext(ServerRequestInterface $request, array $arguments, \Closure $renderChildrenClosure): string
    {
        // No support for following arguments:
        //  * format
        $pageUid = (int)($arguments['pageUid'] ?? 0);
        $pageType = (int)($arguments['pageType'] ?? 0);
        $noCache = (bool)($arguments['noCache'] ?? false);
        /** @var string|null $language */
        $language = isset($arguments['language']) ? (string)$arguments['language'] : null;
        /** @var string|null $section */
        $section = $arguments['section'] ?? null;
        $linkAccessRestrictedPages = (bool)($arguments['linkAccessRestrictedPages'] ?? false);
        /** @var array|null $additionalParams */
        $additionalParams = $arguments['additionalParams'] ?? null;
        $absolute = (bool)($arguments['absolute'] ?? false);
        /** @var bool|string $addQueryString */
        $addQueryString = $arguments['addQueryString'] ?? false;
        /** @var array|null $argumentsToBeExcludedFromQueryString */
        $argumentsToBeExcludedFromQueryString = $arguments['argumentsToBeExcludedFromQueryString'] ?? null;
        /** @var string|null $action */
        $action = $arguments['action'] ?? null;
        /** @var string|null $controller */
        $controller = $arguments['controller'] ?? null;
        /** @var string|null $extensionName */
        $extensionName = $arguments['extensionName'] ?? null;
        /** @var string|null $pluginName */
        $pluginName = $arguments['pluginName'] ?? null;
        /** @var array|null $arguments */
        $arguments = $arguments['arguments'] ?? [];

        $allExtbaseArgumentsAreSet = (
            is_string($extensionName) && $extensionName !== ''
            && is_string($pluginName) && $pluginName !== ''
            && is_string($controller) && $controller !== ''
            && is_string($action) && $action !== ''
        );
        if (!$allExtbaseArgumentsAreSet) {
            throw new \RuntimeException(
                'ViewHelper f:uri.action needs either all extbase arguments set'
                . ' ("extensionName", "pluginName", "controller", "action")'
                . ' or needs a request implementing extbase RequestInterface.',
                1639819692
            );
        }

        // Provide extbase default and custom arguments as prefixed additional params
        $extbaseArgumentNamespace = 'tx_'
            . str_replace('_', '', strtolower($extensionName))
            . '_'
            . str_replace('_', '', strtolower($pluginName));
        $additionalParams ??= [];
        $additionalParams[$extbaseArgumentNamespace] = array_replace(
            [
                'controller' => $controller,
                'action' => $action,
            ],
            $arguments
        );

        $typolinkConfiguration = [
            'parameter' => $pageUid,
        ];
        if ($pageType) {
            $typolinkConfiguration['parameter'] .= ',' . $pageType;
        }
        if ($language !== null) {
            $typolinkConfiguration['language'] = $language;
        }
        if ($noCache) {
            $typolinkConfiguration['no_cache'] = 1;
        }
        if ($section) {
            $typolinkConfiguration['section'] = $section;
        }
        if ($linkAccessRestrictedPages) {
            $typolinkConfiguration['linkAccessRestrictedPages'] = 1;
        }
        $typolinkConfiguration['additionalParams'] = HttpUtility::buildQueryString($additionalParams, '&');
        if ($absolute) {
            $typolinkConfiguration['forceAbsoluteUrl'] = true;
        }
        if ($addQueryString && $addQueryString !== 'false') {
            $typolinkConfiguration['addQueryString'] = $addQueryString;
            if ($argumentsToBeExcludedFromQueryString !== []) {
                $typolinkConfiguration['addQueryString.']['exclude'] = implode(',', $argumentsToBeExcludedFromQueryString);
            }
        }

        try {
            $cObj = GeneralUtility::makeInstance(ContentObjectRenderer::class);
            $cObj->setRequest($request);
            $linkFactory = GeneralUtility::makeInstance(LinkFactory::class);
            $linkResult = $linkFactory->create((string)$renderChildrenClosure(), $typolinkConfiguration, $cObj);
            return $linkResult->getUrl();
        } catch (UnableToLinkException) {
            return (string)$renderChildrenClosure();
        }
    }

    protected static function renderWithExtbaseContext(ExtbaseRequestInterface $request, array $arguments): string
    {
        $pageUid = (int)($arguments['pageUid'] ?? 0);
        $pageType = (int)($arguments['pageType'] ?? 0);
        $noCache = (bool)($arguments['noCache'] ?? false);
        /** @var string|null $language */
        $language = isset($arguments['language']) ? (string)$arguments['language'] : null;
        /** @var string|null $section */
        $section = $arguments['section'] ?? null;
        /** @var string|null $format */
        $format = $arguments['format'] ?? null;
        $linkAccessRestrictedPages = (bool)($arguments['linkAccessRestrictedPages'] ?? false);
        /** @var array|null $additionalParams */
        $additionalParams = $arguments['additionalParams'] ?? null;
        $absolute = (bool)($arguments['absolute'] ?? false);
        /** @var bool|string $addQueryString */
        $addQueryString = $arguments['addQueryString'] ?? false;
        /** @var array|null $argumentsToBeExcludedFromQueryString */
        $argumentsToBeExcludedFromQueryString = $arguments['argumentsToBeExcludedFromQueryString'] ?? null;
        /** @var string|null $action */
        $action = $arguments['action'] ?? null;
        /** @var string|null $controller */
        $controller = $arguments['controller'] ?? null;
        /** @var string|null $extensionName */
        $extensionName = $arguments['extensionName'] ?? null;
        /** @var string|null $pluginName */
        $pluginName = $arguments['pluginName'] ?? null;
        /** @var array|null $arguments */
        $arguments = $arguments['arguments'] ?? [];

        /** @var ExtbaseUriBuilder $uriBuilder */
        $uriBuilder = GeneralUtility::makeInstance(ExtbaseUriBuilder::class);
        $uriBuilder->reset();
        $uriBuilder->setRequest($request);

        if ($pageUid > 0) {
            $uriBuilder->setTargetPageUid($pageUid);
        }
        if ($pageType > 0) {
            $uriBuilder->setTargetPageType($pageType);
        }
        if ($noCache === true) {
            $uriBuilder->setNoCache($noCache);
        }
        if (is_string($section)) {
            $uriBuilder->setSection($section);
        }
        if (is_string($format)) {
            $uriBuilder->setFormat($format);
        }
        if (is_array($additionalParams)) {
            $uriBuilder->setArguments($additionalParams);
        }
        if ($absolute === true) {
            $uriBuilder->setCreateAbsoluteUri($absolute);
        }
        if ($addQueryString && $addQueryString !== 'false') {
            $uriBuilder->setAddQueryString($addQueryString);
        }
        if (is_array($argumentsToBeExcludedFromQueryString)) {
            $uriBuilder->setArgumentsToBeExcludedFromQueryString($argumentsToBeExcludedFromQueryString);
        }
        if ($linkAccessRestrictedPages === true) {
            $uriBuilder->setLinkAccessRestrictedPages(true);
        }

        $uriBuilder->setLanguage($language);

        return $uriBuilder->uriFor($action, $arguments, $controller, $extensionName, $pluginName);
    }

}

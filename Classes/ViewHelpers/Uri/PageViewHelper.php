<?php


namespace SUDHAUS7\Shortcutlink\ViewHelpers\Uri;

use Psr\Http\Message\ServerRequestInterface;
use SUDHAUS7\Shortcutlink\Service\ShortlinkService;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Routing\Route;
use TYPO3\CMS\Backend\Routing\UriBuilder as BackendUriBuilder;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\HttpUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Extbase\Mvc\RequestInterface as ExtbaseRequestInterface;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder as ExtbaseUriBuilder;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Typolink\LinkFactory;
use TYPO3\CMS\Frontend\Typolink\UnableToLinkException;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\Traits\CompileWithRenderStatic;

class PageViewHelper extends AbstractViewHelper
{
    use CompileWithRenderStatic;
    /**
     * Initialize arguments
     */
    public function initializeArguments()
    {
        $this->registerArgument('pageUid', 'int', 'target PID');
        $this->registerArgument('additionalParams', 'array', 'query parameters to be attached to the resulting URI', false, []);
        $this->registerArgument('pageType', 'int', 'type of the target page. See typolink.parameter', false, 0);
        $this->registerArgument('noCache', 'bool', 'set this to disable caching for the target page. You should not need this.', false, false);
        $this->registerArgument('language', 'string', 'link to a specific language - defaults to the current language, use a language ID or "current" to enforce a specific language', false);
        $this->registerArgument('section', 'string', 'the anchor to be added to the URI', false, '');
        $this->registerArgument('linkAccessRestrictedPages', 'bool', 'If set, links pointing to access restricted pages will still link to the page even though the page cannot be accessed.', false, false);
        $this->registerArgument('absolute', 'bool', 'If set, the URI of the rendered link is absolute', false, false);
        $this->registerArgument('addQueryString', 'string', 'If set, the current query parameters will be kept in the URL. If set to "untrusted", then ALL query parameters will be added. Be aware, that this might lead to problems when the generated link is cached.', false, false);
        $this->registerArgument('argumentsToBeExcludedFromQueryString', 'array', 'arguments to be removed from the URI. Only active if $addQueryString = TRUE', false, []);

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

    protected static function renderBackendLinkWithCoreContext(ServerRequestInterface $request, array $arguments): string
    {
        $pageUid = isset($arguments['pageUid']) ? (int)$arguments['pageUid'] : null;
        $section = isset($arguments['section']) ? (string)$arguments['section'] : '';
        $additionalParams = isset($arguments['additionalParams']) ? (array)$arguments['additionalParams'] : [];
        $absolute = isset($arguments['absolute']) && (bool)$arguments['absolute'];
        $addQueryString = $arguments['addQueryString'] ?? false;
        $argumentsToBeExcludedFromQueryString = isset($arguments['argumentsToBeExcludedFromQueryString']) ? (array)$arguments['argumentsToBeExcludedFromQueryString'] : [];

        $arguments = [];
        if ($addQueryString && $addQueryString !== 'false') {
            $arguments = $request->getQueryParams();
            foreach ($argumentsToBeExcludedFromQueryString as $argumentToBeExcluded) {
                $argumentArrayToBeExcluded = [];
                parse_str($argumentToBeExcluded, $argumentArrayToBeExcluded);
                $arguments = ArrayUtility::arrayDiffKeyRecursive($arguments, $argumentArrayToBeExcluded);
            }
        }

        $id = $pageUid ?? $request->getQueryParams()['id'] ?? null;
        if ($id !== null) {
            $arguments['id'] = $id;
        }
        if (!isset($arguments['route']) && ($route = $request->getAttribute('route')) instanceof Route) {
            $arguments['route'] = $route->getOption('_identifier');
        }
        $arguments = array_replace_recursive($arguments, $additionalParams);
        $routeName = $arguments['route'] ?? null;
        unset($arguments['route'], $arguments['token']);
        $backendUriBuilder = GeneralUtility::makeInstance(BackendUriBuilder::class);
        try {
            if ($absolute) {
                $uri = (string)$backendUriBuilder->buildUriFromRoute($routeName, $arguments, BackendUriBuilder::ABSOLUTE_URL);
            } else {
                $uri = (string)$backendUriBuilder->buildUriFromRoute($routeName, $arguments, BackendUriBuilder::ABSOLUTE_PATH);
            }
        } catch (RouteNotFoundException) {
            $uri = '';
        }
        if ($section !== '') {
            $uri .= '#' . $section;
        }
        return $uri;
    }

    protected static function renderFrontendLinkWithCoreContext(ServerRequestInterface $request, array $arguments, \Closure $renderChildrenClosure): string
    {
        $pageUid = isset($arguments['pageUid']) ? (int)$arguments['pageUid'] : 'current';
        $pageType = isset($arguments['pageType']) ? (int)$arguments['pageType'] : 0;
        $noCache = isset($arguments['noCache']) && (bool)$arguments['noCache'];
        $section = isset($arguments['section']) ? (string)$arguments['section'] : '';
        $language = isset($arguments['language']) ? (string)$arguments['language'] : null;
        $linkAccessRestrictedPages = isset($arguments['linkAccessRestrictedPages']) && (bool)$arguments['linkAccessRestrictedPages'];
        $additionalParams = isset($arguments['additionalParams']) ? (array)$arguments['additionalParams'] : [];
        $absolute = isset($arguments['absolute']) && (bool)$arguments['absolute'];
        $addQueryString = $arguments['addQueryString'] ?? false;
        $argumentsToBeExcludedFromQueryString = isset($arguments['argumentsToBeExcludedFromQueryString']) ? (array)$arguments['argumentsToBeExcludedFromQueryString'] : [];

        $typolinkConfiguration = [
            'parameter' => $pageUid,
        ];
        if ($pageType) {
            $typolinkConfiguration['parameter'] .= ',' . $pageType;
        }
        if ($noCache) {
            $typolinkConfiguration['no_cache'] = 1;
        }
        if ($language !== null) {
            $typolinkConfiguration['language'] = $language;
        }
        if ($section) {
            $typolinkConfiguration['section'] = $section;
        }
        if ($linkAccessRestrictedPages) {
            $typolinkConfiguration['linkAccessRestrictedPages'] = 1;
        }
        if ($additionalParams) {
            $typolinkConfiguration['additionalParams'] = HttpUtility::buildQueryString($additionalParams, '&');
        }
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
        $pageUid = $arguments['pageUid'];
        $additionalParams = $arguments['additionalParams'];
        $pageType = (int)($arguments['pageType'] ?? 0);
        $noCache = $arguments['noCache'];
        $section = $arguments['section'];
        $language = isset($arguments['language']) ? (string)$arguments['language'] : null;
        $linkAccessRestrictedPages = $arguments['linkAccessRestrictedPages'];
        $absolute = $arguments['absolute'];
        $addQueryString = $arguments['addQueryString'] ?? false;
        $argumentsToBeExcludedFromQueryString = $arguments['argumentsToBeExcludedFromQueryString'];

        $uriBuilder = GeneralUtility::makeInstance(ExtbaseUriBuilder::class);
        $uri = $uriBuilder
            ->reset()
            ->setRequest($request)
            ->setTargetPageType($pageType)
            ->setNoCache($noCache)
            ->setSection($section)
            ->setLanguage($language)
            ->setLinkAccessRestrictedPages($linkAccessRestrictedPages)
            ->setArguments($additionalParams)
            ->setCreateAbsoluteUri($absolute)
            ->setAddQueryString($addQueryString)
            ->setArgumentsToBeExcludedFromQueryString($argumentsToBeExcludedFromQueryString);

        if (MathUtility::canBeInterpretedAsInteger($pageUid)) {
            $uriBuilder->setTargetPageUid((int)$pageUid);
        }

        return $uri->build();
    }

}

<?php
declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace Causal\IgLdapSsoAuth\ViewHelpers;

use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\RequestInterface;
use TYPO3\CMS\Extbase\Service\ExtensionService;
use TYPO3\CMS\Fluid\Core\Rendering\RenderingContext;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\Traits\CompileWithRenderStatic;

/**
 * Render a conf* View helper which renders the flash messages (if there are any).
 *
 * Largely inspired by @see \TYPO3\CMS\Fluid\ViewHelpers\FlashMessagesViewHelper
 *
 * @author     Xavier Perseguers <xavier@causal.ch>
 * @package    TYPO3
 * @subpackage ig_ldap_sso_auth
 */
class FlashMessagesViewHelper extends AbstractViewHelper
{
    use CompileWithRenderStatic;

    /**
     * ViewHelper outputs HTML therefore output escaping has to be disabled
     *
     * @var bool
     */
    protected $escapeOutput = false;

    protected static $classes = [
        -2 /* notice  */ => 'notice',
        -1 /* info    */ => 'info',
         0 /* ok      */ => 'success',
         1 /* warning */ => 'warning',
         2 /* error   */ => 'danger'
    ];

    protected static $icons = [
        -2 /* notice  */ => 'actions-lightbulb',
        -1 /* info    */ => 'actions-info-circle',
         0 /* ok      */ => 'actions-check-circle',
         1 /* warning */ => 'actions-exclamation-triangle',
         2 /* error   */ => 'actions-exclamation-circle'
    ];

    protected static $iconsV11 = [
        -2 /* notice  */ => 'fa-lightbulb-o',
        -1 /* info    */ => 'fa-info',
         0 /* ok      */ => 'fa-check',
         1 /* warning */ => 'fa-exclamation',
         2 /* error   */ => 'fa-times'
    ];

    /**
     * Initialize arguments
     */
    public function initializeArguments()
    {
        $this->registerArgument('queueIdentifier', 'string', 'Flash-message queue to use');
        $this->registerArgument('as', 'string', 'The name of the current flashMessage variable for rendering inside');
    }

    /**
     * Renders FlashMessages and flushes the FlashMessage queue
     * Note: This disables the current page cache in order to prevent FlashMessage output
     * from being cached.
     *
     * @see \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController::no_cache
     * @param array $arguments
     * @param \Closure $renderChildrenClosure
     * @param RenderingContextInterface $renderingContext
     * @return mixed
     */
    public static function renderStatic(array $arguments, \Closure $renderChildrenClosure, RenderingContextInterface $renderingContext)
    {
        $as = $arguments['as'];
        $queueIdentifier = $arguments['queueIdentifier'] ?? null;

        if ($queueIdentifier === null) {
            /** @var RenderingContext $renderingContext */
            $request = $renderingContext->getRequest();
            if (!$request instanceof RequestInterface) {
                // Throw if not an extbase request
                throw new \RuntimeException(
                    'ViewHelper f:flashMessages needs an extbase Request object to resolve the Queue identifier magically.'
                    . ' When not in extbase context, set attribute "queueIdentifier".',
                    1639821269
                );
            }
            $extensionService = GeneralUtility::makeInstance(ExtensionService::class);
            $pluginNamespace = $extensionService->getPluginNamespace($request->getControllerExtensionName(), $request->getPluginName());
            $queueIdentifier = 'extbase.flashmessages.' . $pluginNamespace;
        }

        $flashMessageQueue = GeneralUtility::makeInstance(FlashMessageService::class)
            ->getMessageQueueByIdentifier($queueIdentifier);

        $flashMessages = $flashMessageQueue->getAllMessagesAndFlush();
        if (count($flashMessages) === 0) {
            return '';
        }

        if ($as === null) {
            $out = [];
            foreach ($flashMessages as $flashMessage) {
                $out[] = static::renderFlashMessage($flashMessage);
            }
            return implode(LF, $out);
        }
        $templateVariableContainer = $renderingContext->getVariableProvider();
        $templateVariableContainer->add($as, $flashMessages);
        $content = $renderChildrenClosure();
        $templateVariableContainer->remove($as);

        return $content;
    }

    /**
     * @param FlashMessage $flashMessage
     * @return string
     */
    protected static function renderFlashMessage(FlashMessage $flashMessage): string
    {
        if ((new \TYPO3\CMS\Core\Information\Typo3Version())->getMajorVersion() >= 12) {
            $severity = $flashMessage->getSeverity()->value;
        } else {
            $severity = $flashMessage->getSeverity();
        }

        $className = 'alert-' . static::$classes[$severity];

        $messageTitle = $flashMessage->getTitle();
        $markup = [];
        $markup[] = '<div class="alert ' . $className . '">';
        $markup[] = '    <div class="media">';
        $markup[] = '        <div class="media-left">';

        $typo3Version = (new \TYPO3\CMS\Core\Information\Typo3Version())->getMajorVersion();
        if ($typo3Version >= 12) {
            $iconFactory = GeneralUtility::makeInstance(IconFactory::class);
            $icon = $iconFactory->getIcon(
                static::$icons[$severity],
                $typo3Version >= 13
                    ? \TYPO3\CMS\Core\Imaging\IconSize::MEDIUM
                    : \TYPO3\CMS\Core\Imaging\Icon::SIZE_MEDIUM
            );
            $markup[] = '            ' . $icon;
        } else {
            $iconName = static::$iconsV11[$severity];
            $markup[] = '            <span class="fa-stack fa-lg">';
            $markup[] = '                <i class="fa fa-circle fa-stack-2x"></i>';
            $markup[] = '                <i class="fa ' . $iconName . ' fa-stack-1x"></i>';
            $markup[] = '            </span>';
        }

        $markup[] = '        </div>';
        $markup[] = '        <div class="media-body">';
        if (!empty($messageTitle)) {
            $markup[] = '            <h4 class="alert-title">' . htmlspecialchars($messageTitle) . '</h4>';
        }
        $markup[] = '            <p class="alert-message">' . $flashMessage->getMessage() . '</p>';
        $markup[] = '        </div>';
        $markup[] = '    </div>';
        $markup[] = '</div>';

        return implode('', $markup);
    }
}

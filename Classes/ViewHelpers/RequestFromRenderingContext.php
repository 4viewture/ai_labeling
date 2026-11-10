<?php

declare(strict_types=1);

namespace FourViewture\AiLabeling\ViewHelpers;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

/**
 * Request of the rendering context:
 * - TYPO3 13.4 / 14.3: attribute ServerRequestInterface (14.3 has no getRequest() anymore)
 * - TYPO3 12.4: RenderingContext::getRequest()
 * null -> FrontendContext falls back to $GLOBALS['TYPO3_REQUEST'].
 */
trait RequestFromRenderingContext
{
    private static function requestFromContext(RenderingContextInterface $context): ?ServerRequestInterface
    {
        if (method_exists($context, 'hasAttribute') && $context->hasAttribute(ServerRequestInterface::class)) {
            $request = $context->getAttribute(ServerRequestInterface::class);
            return $request instanceof ServerRequestInterface ? $request : null;
        }
        if (method_exists($context, 'getRequest')) {
            $request = $context->getRequest();
            return $request instanceof ServerRequestInterface ? $request : null;
        }
        return null;
    }
}

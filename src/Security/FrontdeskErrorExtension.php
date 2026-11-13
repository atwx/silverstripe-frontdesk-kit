<?php

namespace Atwx\SilverstripeFrontdeskKit\Security;

use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\HTTPResponse_Exception;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Extension;
use SilverStripe\Model\ArrayData;
use SilverStripe\View\SSViewer;

/**
 * Error pages (404, 403, 500 …) of controllers in the frontdesk layout
 * (templates/FrontdeskErrorPage.ss) instead of a plain text body. Only for
 * browser page requests: Ajax and requests that do not accept HTML keep the
 * plain response, and controllers listed in excluded_controllers (the CMS)
 * are left alone.
 *
 * @extends Extension<Controller>
 */
class FrontdeskErrorExtension extends Extension
{
    private static bool $enabled = true;

    /**
     * @var array<int, string>
     */
    private static array $excluded_controllers = [
        'SilverStripe\\Admin\\LeftAndMain',
    ];

    protected function onBeforeHTTPError(int $code, ?HTTPRequest $request, ?string $message = null): void
    {
        if (!Config::inst()->get(self::class, 'enabled') || !$request || !$this->wantsPage($request)) {
            return;
        }
        foreach ((array) Config::inst()->get(self::class, 'excluded_controllers') as $class) {
            if (is_a($this->getOwner(), (string) $class)) {
                return;
            }
        }
        $response = HTTPResponse::create(self::render($code, (string) $message), $code);
        $response->addHeader('Content-Type', 'text/html; charset=utf-8');
        throw new HTTPResponse_Exception($response);
    }

    /**
     * The error page as HTML.
     */
    public static function render(int $code, string $message = ''): string
    {
        [$title, $text] = match (true) {
            $code === 404 => [
                _t(self::class . '.NOT_FOUND', 'Page not found'),
                _t(self::class . '.NOT_FOUND_TEXT', 'This page does not exist (any more). Please check the link.'),
            ],
            $code === 403 || $code === 401 => [
                _t(self::class . '.FORBIDDEN', 'No access'),
                _t(self::class . '.FORBIDDEN_TEXT', 'You are not allowed to see this page.'),
            ],
            $code >= 500 => [
                _t(self::class . '.SERVER_ERROR', 'An error occurred'),
                _t(
                    self::class . '.SERVER_ERROR_TEXT',
                    'Please try again later. If the error persists, please contact us.'
                ),
            ],
            default => [
                _t(self::class . '.ERROR', 'The request could not be processed'),
                _t(self::class . '.ERROR_TEXT', 'Please go back and try again.'),
            ],
        };
        $data = ArrayData::create([
            'Title' => $title,
            'Text' => $text,
            'Code' => $code,
            // Messages of httpError() are technical and often English: only in dev
            'Detail' => Director::isDev() ? $message : '',
        ]);
        return (string) SSViewer::create('FrontdeskErrorPage')->process($data);
    }

    private function wantsPage(HTTPRequest $request): bool
    {
        if ($request->isAjax() || $request->getHeader('HX-Request')) {
            return false;
        }
        $accept = strtolower((string) $request->getHeader('Accept'));
        return str_contains($accept, 'text/html');
    }
}

<?php

namespace Atwx\SilverstripeFrontdeskKit\Security;

use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Extension;
use SilverStripe\Security\Security;
use SilverStripe\View\SSViewer;

/**
 * Shows the Security pages (log in, MFA, lost and change password, log out,
 * permission denied) in the frontdesk layout: templates/Security.ss, which
 * projects override like any template.
 *
 * silverstripe/mfa requires silverstripe/login-forms, which switches these
 * pages to its own theme; with replace_login_forms the site's themes are used
 * instead.
 *
 * @extends Extension<Security>
 */
class FrontdeskSecurityExtension extends Extension
{
    /**
     * Use the site's themes instead of the login-forms theme.
     */
    private static bool $replace_login_forms = true;

    /**
     * Where a "Log in via Silvergate" button links to (the Silvergate manager),
     * shown on the login page when atwx/silverstripe-gate-client is installed.
     * The environment variable FRONTDESK_SILVERGATE_URL takes precedence.
     */
    private static string $silvergate_login_url = '';

    private const string LOGIN_FORMS = 'SilverStripe\\LoginForms\\EnablerExtension';

    private const string GATE_CLIENT = 'Atwx\\SilverGateClient\\Controllers\\LoginController';

    protected function onAfterInit(): void
    {
        if (Config::inst()->get(self::class, 'replace_login_forms') && class_exists(self::LOGIN_FORMS)) {
            Config::modify()->set(self::LOGIN_FORMS, 'login_themes', SSViewer::get_themes());
        }
    }

    /**
     * DaisyUI alert class for the message type of the Security controller.
     */
    public function FrontdeskAlertClass(?string $type = null): string
    {
        return match ((string) $type) {
            'good' => 'alert-success',
            'bad', 'error', 'required', 'validation' => 'alert-error',
            'warning' => 'alert-warning',
            default => 'alert-info',
        };
    }

    /**
     * The Silvergate link on the plain login page, or null.
     */
    public function SilvergateLoginLink(): ?string
    {
        if (!class_exists(self::GATE_CLIENT)) {
            return null;
        }
        $url = (string) (Environment::getEnv('FRONTDESK_SILVERGATE_URL')
            ?: Config::inst()->get(self::class, 'silvergate_login_url'));
        $request = $this->getOwner()->getRequest();
        if ($url === '' || strtolower((string) $this->getOwner()->getAction()) !== 'login' || $request->param('ID')) {
            return null;
        }
        return $url;
    }
}

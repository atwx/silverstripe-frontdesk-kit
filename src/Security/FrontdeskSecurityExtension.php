<?php

namespace Atwx\SilverstripeFrontdeskKit\Security;

use SilverStripe\Control\Director;
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
     * Where a "Log in via Silvergate" button links to, shown on the login page
     * when atwx/silverstripe-gate-client is installed: the Silvergate manager.
     * The button goes straight to the manager's login for this site's domain
     * ({host} is replaced by it); a plain manager URL gets the path
     * silvergate_login_path appended. The environment variable
     * FRONTDESK_SILVERGATE_URL takes precedence; an empty value hides the button.
     */
    private static string $silvergate_login_url = 'https://intra.atw.io';

    /**
     * Path of the manager's direct login for a domain, appended to a
     * silvergate_login_url without {host}.
     */
    private static string $silvergate_login_path = '/websites/website-login?domain={host}';

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
        if (!str_contains($url, '{host}')) {
            $url = rtrim($url, '/') . (string) Config::inst()->get(self::class, 'silvergate_login_path');
        }
        $host = (string) parse_url(Director::absoluteBaseURL(), PHP_URL_HOST);
        return str_replace('{host}', rawurlencode($host), $url);
    }
}

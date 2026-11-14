<?php

namespace Atwx\SilverstripeFrontdeskKitTests\Unit;

use Atwx\SilverstripeFrontdeskKit\Security\FrontdeskErrorExtension;
use Atwx\SilverstripeFrontdeskKit\Security\FrontdeskSecurityExtension;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Dev\TestOnly;
use SilverStripe\Security\Security;

class ErrorPageController extends Controller implements TestOnly
{
    private static string $url_segment = 'fdk-error-test';

    private static array $allowed_actions = ['missing'];

    public function missing(): void
    {
        $this->httpError(404, 'Record 7 not found');
    }
}

class SecurityPagesTest extends FunctionalTest
{
    protected $usesDatabase = true;

    protected function setUp(): void
    {
        parent::setUp();
        Config::modify()->merge('SilverStripe\\Control\\Director', 'rules', [
            'fdk-error-test//$Action' => ErrorPageController::class,
        ]);
    }

    public function testLoginPageInTheFrontdeskLayout(): void
    {
        $response = $this->get('Security/login');
        $this->assertSame(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        $this->assertStringContainsString('fdk-app fdk-security', $body);
        $this->assertStringContainsString('fdk-security-card', $body);
        $this->assertStringContainsString('name="Password"', $body);
        $this->assertStringNotContainsString('login-forms', $body, 'not the login-forms theme');
        $this->assertStringNotContainsString('menu-horizontal', $body, 'no navigation');

        $lost = (string) $this->get('Security/lostpassword')->getBody();
        $this->assertStringContainsString('fdk-security-card', $lost);
    }

    public function testAlertClassesAndSilvergateLink(): void
    {
        $extension = new FrontdeskSecurityExtension();
        $this->assertSame('alert-success', $extension->FrontdeskAlertClass('good'));
        $this->assertSame('alert-error', $extension->FrontdeskAlertClass('bad'));
        $this->assertSame('alert-warning', $extension->FrontdeskAlertClass('warning'));
        $this->assertSame('alert-info', $extension->FrontdeskAlertClass(null));
        $host = rawurlencode((string) parse_url(Director::absoluteBaseURL(), PHP_URL_HOST));

        $this->assertStringContainsString(
            class_exists('Atwx\\SilverGateClient\\Controllers\\LoginController')
                ? 'href="https://intra.atw.io/websites/website-login?domain=' . $host . '"'
                : 'fdk-security',
            (string) $this->get('Security/login')->getBody(),
            'default: the manager\'s login for this domain'
        );
        Config::modify()->set(FrontdeskSecurityExtension::class, 'silvergate_login_url', 'https://gate.example.org/');
        $body = (string) $this->get('Security/login')->getBody();
        if (class_exists('Atwx\\SilverGateClient\\Controllers\\LoginController')) {
            $this->assertStringContainsString(
                'href="https://gate.example.org/websites/website-login?domain=' . $host . '"',
                $body
            );
            Config::modify()->set(
                FrontdeskSecurityExtension::class,
                'silvergate_login_url',
                'https://gate.example.org/in/{host}'
            );
            $this->assertStringContainsString(
                'href="https://gate.example.org/in/' . $host . '"',
                (string) $this->get('Security/login')->getBody(),
                'own URL pattern'
            );
            $lost = (string) $this->get('Security/lostpassword')->getBody();
            $this->assertStringNotContainsString('gate.example.org', $lost, 'only on the login page');
        } else {
            $this->assertStringNotContainsString('gate.example.org', $body, 'no gate client');
        }
        Config::modify()->set(FrontdeskSecurityExtension::class, 'silvergate_login_url', '');
        $this->assertStringNotContainsString('Silvergate', (string) $this->get('Security/login')->getBody(), 'off');
    }

    public function testErrorPagesForBrowsersOnly(): void
    {
        $page = $this->get('fdk-error-test/missing', null, ['Accept' => 'text/html,application/xhtml+xml']);
        $this->assertSame(404, $page->getStatusCode());
        $this->assertStringContainsString('fdk-error', (string) $page->getBody());
        $this->assertStringContainsString('404', (string) $page->getBody());

        $json = $this->get('fdk-error-test/missing', null, ['Accept' => 'application/json']);
        $this->assertSame(404, $json->getStatusCode());
        $this->assertStringNotContainsString('fdk-error', (string) $json->getBody());

        $htmx = $this->get('fdk-error-test/missing', null, ['Accept' => 'text/html', 'HX-Request' => 'true']);
        $this->assertStringNotContainsString('fdk-error', (string) $htmx->getBody());

        Config::modify()->set(FrontdeskErrorExtension::class, 'enabled', false);
        $off = $this->get('fdk-error-test/missing', null, ['Accept' => 'text/html']);
        $this->assertStringNotContainsString('fdk-error', (string) $off->getBody());
    }

    public function testErrorTexts(): void
    {
        $this->assertStringContainsString('403', FrontdeskErrorExtension::render(403));
        $html = FrontdeskErrorExtension::render(500, 'secret detail');
        $this->assertStringContainsString('fdk-error', $html);
        $this->assertInstanceOf(Security::class, Security::singleton());
    }
}

<?php

namespace Atwx\SilverstripeFrontdeskKitTests\Unit;

use Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController;
use Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskTemplateProvider;
use Atwx\SilverstripeFrontdeskKit\Navigation\FrontdeskNavigation;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Dev\TestOnly;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;
use SilverStripe\View\SSViewer;

class NavigationTarget extends FrontdeskController implements TestOnly
{
    public static bool $allowed = true;

    public function canView($member = null): bool
    {
        return self::$allowed;
    }
}

class NavigationBadges implements TestOnly
{
    public static int $count = 3;

    public static function open(): int
    {
        return self::$count;
    }
}

class NavigationTest extends SapphireTest
{
    protected $usesDatabase = true;

    protected function setUp(): void
    {
        parent::setUp();
        NavigationTarget::$allowed = true;
        NavigationBadges::$count = 3;
        Config::modify()->set(FrontdeskController::class, 'main_navigation', [
            'students' => ['title' => 'Schüler', 'segment' => 'students', 'controller' => NavigationTarget::class,
                'default' => true],
            'followups' => ['title' => 'Wiedervorlagen', 'segment' => 'followups', 'link' => 'followups?view=mine-due',
                'badge' => NavigationBadges::class . '::open'],
            'admin' => ['title' => 'Bereinigung', 'segment' => 'retention', 'permission' => 'ADMIN'],
            'gone' => ['title' => 'Weg', 'segment' => 'gone', 'controller' => 'App\\DoesNotExist'],
        ]);
        Config::modify()->set(FrontdeskController::class, 'secondary_navigation', [
            'flow' => ['title' => 'Abläufe', 'link' => 'admin/flow', 'permission' => ['CMS_ACCESS_LeftAndMain']],
            'help' => ['title' => 'Hilfe', 'link' => 'https://example.org/help'],
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function entries(string $which, string $url): array
    {
        $navigation = FrontdeskNavigation::singleton();
        $config = (array) FrontdeskController::config()->get($which);
        return array_map(fn ($entry) => $entry->toMap(), $navigation->entries($config, $url)->toArray());
    }

    public function testNothingWhenLoggedOut(): void
    {
        $this->logOut();
        $this->assertSame([], $this->entries('main_navigation', ''));
    }

    public function testLinksBadgesPermissionsAndActiveEntry(): void
    {
        $this->logInWithPermission('SOMETHING');
        $main = $this->entries('main_navigation', 'followups/view/3');
        $this->assertSame(['Schüler', 'Wiedervorlagen'], array_column($main, 'Title'), 'no ADMIN, unknown class');
        $this->assertSame('followups?view=mine-due', $main[1]['Link']);
        $this->assertTrue($main[1]['Active'], 'active by segment, link with query');
        $this->assertFalse($main[0]['Active']);
        $this->assertSame('3', $main[1]['Badge']);
        $this->assertSame('', $main[0]['Badge']);

        $this->assertTrue($this->entries('main_navigation', '')[0]['Active'], 'default entry on the start page');

        NavigationBadges::$count = 0;
        NavigationTarget::$allowed = false;
        $main = $this->entries('main_navigation', 'students');
        $this->assertSame(['Wiedervorlagen'], array_column($main, 'Title'), 'canView of the controller');
        $this->assertSame('', $main[0]['Badge'], 'no badge for 0');

        $secondary = $this->entries('secondary_navigation', 'admin/flow');
        $this->assertSame(['Hilfe'], array_column($secondary, 'Title'), 'permission list');
        $this->assertTrue($secondary[0]['External']);
        $this->assertFalse($secondary[0]['Active']);
    }

    public function testAdminSeesEverythingAndCMSLinkIsActive(): void
    {
        $this->logInWithPermission('ADMIN');
        $this->assertSame(
            ['Schüler', 'Wiedervorlagen', 'Bereinigung'],
            array_column($this->entries('main_navigation', 'retention'), 'Title')
        );
        $secondary = $this->entries('secondary_navigation', 'admin/flow/edit');
        $this->assertTrue($secondary[0]['Active'], 'active by the path of the link');
    }

    public function testHeaderRendersBurgerAndUserMenu(): void
    {
        $member = Member::create(['FirstName' => 'Ida', 'Surname' => 'Lenz', 'Email' => 'ida@example.org']);
        $member->write();
        $this->logInAs($member);
        $this->assertSame('IL', FrontdeskTemplateProvider::FrontdeskUserInitials());
        $this->assertSame('Ida Lenz', FrontdeskTemplateProvider::FrontdeskUserName());

        $html = (string) SSViewer::create('Atwx\\SilverstripeFrontdeskKit\\Includes\\FrontdeskHeader')
            ->process(Security::singleton());
        $this->assertStringContainsString('lg:hidden', $html, 'burger below lg');
        $this->assertStringContainsString('menu-horizontal', $html);
        $this->assertSame(2, substr_count($html, 'href="followups?view=mine-due"'), 'burger and horizontal');
        $this->assertStringContainsString('<span class="badge badge-sm badge-primary">3</span>', $html);
        $this->assertStringContainsString('href="https://example.org/help" target="_blank"', $html);
        $this->assertStringContainsString('class="max-lg:hidden"><a href="https://example.org/help"', $html, 'user menu');
        $this->assertStringContainsString('Security/logout', $html);
    }
}

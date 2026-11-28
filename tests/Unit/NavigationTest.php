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

    public static function basket(): int
    {
        return 7;
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

    public function testGroupsWithChildren(): void
    {
        Config::modify()->set(FrontdeskController::class, 'main_navigation', [
            'people' => ['title' => 'Personen', 'children' => [
                'students' => ['title' => 'Schüler', 'segment' => 'students', 'controller' => NavigationTarget::class],
                'followups' => ['title' => 'Wiedervorlagen', 'segment' => 'followups',
                    'badge' => NavigationBadges::class . '::open'],
            ]],
            'admin' => ['title' => 'Verwaltung', 'children' => [
                'retention' => ['title' => 'Bereinigung', 'segment' => 'retention', 'permission' => 'ADMIN'],
            ]],
        ]);
        $this->logInWithPermission('SOMETHING');
        $main = $this->entries('main_navigation', 'followups/view/3');
        $this->assertSame(['Personen'], array_column($main, 'Title'), 'group without visible children hidden');
        $this->assertTrue($main[0]['Active'], 'active through a child');
        $this->assertSame('3', $main[0]['Badge'], 'sum of the child badges');
        $children = $main[0]['Children']->toArray();
        $this->assertSame(['Schüler', 'Wiedervorlagen'], array_column(
            array_map(fn ($c) => $c->toMap(), $children),
            'Title'
        ));
        $this->assertFalse($children[0]->Active);

        NavigationTarget::$allowed = false;
        $main = $this->entries('main_navigation', 'students');
        $this->assertFalse($main[0]['Active']);
        $this->assertCount(1, $main[0]['Children']);

        $html = (string) SSViewer::create('Atwx\\SilverstripeFrontdeskKit\\Includes\\FrontdeskHeader')
            ->process(Security::singleton());
        $this->assertStringContainsString('<details class="fdk-nav-group" data-fdk-nav-group><summary>Personen', $html);
        $this->assertStringContainsString('<span class="menu-title">Personen</span>', $html, 'section in the burger');
        $this->assertSame(2, substr_count($html, 'href="followups"'), 'burger and dropdown');
        $this->assertStringNotContainsString('Verwaltung', $html);
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

    public function testEntriesAlignedToTheEnd(): void
    {
        Config::modify()->set(FrontdeskController::class, 'main_navigation', [
            'students' => ['title' => 'Schüler', 'segment' => 'students'],
            'search' => ['title' => 'Suche', 'segment' => 'search', 'align' => 'end'],
            'families' => ['title' => 'Familien', 'segment' => 'families'],
            'todos' => ['title' => 'To-do', 'segment' => 'todos', 'align' => 'end'],
        ]);
        $this->logInWithPermission('SOMETHING');
        $this->assertSame(['Schüler', 'Familien'], FrontdeskTemplateProvider::FrontdeskMainNavigationStart()->column('Title'));
        $this->assertSame(['Suche', 'To-do'], FrontdeskTemplateProvider::FrontdeskMainNavigationEnd()->column('Title'));

        $html = (string) SSViewer::create('Atwx\\SilverstripeFrontdeskKit\\Includes\\FrontdeskHeader')
            ->process(Security::singleton());
        $centerAt = (int) strpos($html, 'navbar-center');
        $endAt = (int) strpos($html, 'navbar-end');
        $end = substr($html, $endAt);
        $center = substr($html, $centerAt, $endAt - $centerAt);
        $this->assertStringContainsString('href="families"', $center);
        $this->assertStringNotContainsString('href="todos"', $center);
        $this->assertLessThan(strpos($end, 'href="todos"'), strpos($end, 'href="search"'), 'order of the config');
        $this->assertLessThan(strpos($end, 'Security/logout'), strpos($end, 'href="todos"'), 'before the user menu');
        $burger = substr($html, 0, (int) strpos($html, 'navbar-center'));
        foreach (['students', 'search', 'families', 'todos'] as $link) {
            $this->assertStringContainsString('href="' . $link . '"', $burger, 'burger menu unchanged');
        }
    }

    public function testIconOnlyEntryWithSecondBadge(): void
    {
        Config::modify()->set(FrontdeskController::class, 'main_navigation', [
            'todos' => ['title' => 'To-dos', 'segment' => 'followups', 'icon' => 'square-check', 'icon_only' => true,
                'badge' => NavigationBadges::class . '::open', 'badge_secondary' => NavigationBadges::class . '::basket'],
        ]);
        $this->logInWithPermission('SOMETHING');
        $main = $this->entries('main_navigation', '');
        $this->assertSame('square-check', $main[0]['Icon']);
        $this->assertTrue($main[0]['IconOnly']);
        $this->assertSame('3', $main[0]['Badge']);
        $this->assertSame('7', $main[0]['BadgeSecondary']);

        $html = (string) SSViewer::create('Atwx\\SilverstripeFrontdeskKit\\Includes\\FrontdeskHeader')
            ->process(Security::singleton());
        $this->assertStringContainsString('title="To-dos"', $html);
        $this->assertStringContainsString('<i data-lucide="square-check"', $html);
        $this->assertStringContainsString('<span class="sr-only">To-dos</span>', $html, 'horizontal: icon only');
        $this->assertMatchesRegularExpression('#</i>To-dos <span class="badge#', $html, 'burger: with title');
        $this->assertStringContainsString('badge-neutral fdk-badge-secondary">7</span>', $html);
    }
}

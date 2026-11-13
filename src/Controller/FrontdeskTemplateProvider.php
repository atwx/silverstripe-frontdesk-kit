<?php

namespace Atwx\SilverstripeFrontdeskKit\Controller;

use Atwx\SilverstripeFrontdeskKit\Model\RecentView;
use SilverStripe\Core\Manifest\ModuleResourceLoader;
use Atwx\SilverstripeFrontdeskKit\Navigation\FrontdeskNavigation;
use SilverStripe\i18n\i18n;
use SilverStripe\Security\Security;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\View\TemplateGlobalProvider;

class FrontdeskTemplateProvider implements TemplateGlobalProvider
{
    /**
     * @return array<int, string>
     */
    public static function get_template_global_variables(): array
    {
        return [
            'FrontdeskLogo',
            'FrontdeskRecentViews',
            'FrontdeskLang',
            'FrontdeskMainNavigation',
            'FrontdeskSecondaryNavigation',
            'FrontdeskUserInitials',
            'FrontdeskUserName',
        ];
    }

    public static function FrontdeskLogo(): ?string
    {
        $logo = FrontdeskController::config()->get('logo');
        if ($logo) {
            return ModuleResourceLoader::resourceURL($logo);
        }
        return null;
    }

    /**
     * Header entries (FrontdeskController.main_navigation), see FrontdeskNavigation.
     *
     * @return ArrayList<\SilverStripe\Model\ArrayData>
     */
    public static function FrontdeskMainNavigation(): ArrayList
    {
        return FrontdeskNavigation::singleton()->main();
    }

    /**
     * @return ArrayList<\SilverStripe\Model\ArrayData>
     */
    public static function FrontdeskSecondaryNavigation(): ArrayList
    {
        return FrontdeskNavigation::singleton()->secondary();
    }

    /**
     * Initials of the current member for the avatar in the header.
     */
    public static function FrontdeskUserInitials(): string
    {
        $member = Security::getCurrentUser();
        if (!$member) {
            return '';
        }
        $initials = mb_strtoupper(mb_substr((string) $member->FirstName, 0, 1) . mb_substr((string) $member->Surname, 0, 1));
        return $initials ?: mb_strtoupper(mb_substr((string) $member->Email, 0, 1));
    }

    public static function FrontdeskUserName(): string
    {
        $member = Security::getCurrentUser();
        if (!$member) {
            return '';
        }
        $name = trim($member->FirstName . ' ' . $member->Surname);
        return $name !== '' ? $name : (string) $member->Email;
    }

    /**
     * The lang attribute of a page, e.g. "de-DE".
     */
    public static function FrontdeskLang(): string
    {
        return i18n::convert_rfc1766(i18n::get_locale());
    }

    /**
     * The current member's recently viewed records across all types (see
     * RecentView::recent()), for a start page:
     * <% include Atwx\SilverstripeFrontdeskKit\Includes\RecentViews %>
     *
     * @return ArrayList<\SilverStripe\Model\ArrayData>
     */
    public static function FrontdeskRecentViews(int $limit = 0): ArrayList
    {
        return RecentView::recent($limit);
    }
}

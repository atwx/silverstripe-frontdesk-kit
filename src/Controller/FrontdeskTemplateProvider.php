<?php

namespace Atwx\SilverstripeFrontdeskKit\Controller;

use Atwx\SilverstripeFrontdeskKit\Model\RecentView;
use SilverStripe\Core\Manifest\ModuleResourceLoader;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\View\TemplateGlobalProvider;

class FrontdeskTemplateProvider implements TemplateGlobalProvider
{
    public static function get_template_global_variables(): array
    {
        return ['FrontdeskLogo', 'FrontdeskRecentViews'];
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

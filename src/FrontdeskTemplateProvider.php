<?php

namespace Atwx\SilverstripeFrontdeskKit;

use SilverStripe\Core\Manifest\ModuleResourceLoader;
use SilverStripe\View\TemplateGlobalProvider;

class FrontdeskTemplateProvider implements TemplateGlobalProvider
{
    public static function get_template_global_variables(): array
    {
        return ['FrontdeskLogo'];
    }

    public static function FrontdeskLogo(): ?string
    {
        $logo = FrontdeskController::config()->get('logo');
        if ($logo) {
            return ModuleResourceLoader::resourceURL($logo);
        }
        return null;
    }
}

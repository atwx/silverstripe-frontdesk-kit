<?php

namespace Atwx\SilverstripeFrontdeskKit\Controller;

use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Model\ArrayData;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;

/**
 * Page "Reports" (route reports): the reports of all lists the member may
 * see (ListView::report() and saved views, own and shared), by list and
 * group, each a link into its list. Lists leave with config reports: false.
 * Add it to the navigation like any list (segment 'reports').
 */
class ReportsController extends FrontdeskController
{
    private static string $managed_model = Member::class;

    private static string $url_segment = 'reports';

    private static string $title = 'Berichte';

    private static array $allowed_actions = ['index'];

    public function init()
    {
        parent::init();
        $this->templates['index'] = [static::class, 'Page'];
    }

    public function canView($member = null): bool
    {
        return (bool) ($member ?: Security::getCurrentUser());
    }

    public function canEdit($member = null): bool
    {
        return false;
    }

    public function Link($action = null): string
    {
        return Controller::join_links('reports', $action);
    }

    /**
     * @return array<string, mixed>
     */
    public function index(HTTPRequest $request)
    {
        return [
            'Title' => _t(FrontdeskController::class . '.REPORTS', 'Reports'),
            'Lists' => $this->reportLists(),
            'Actions' => false,
        ];
    }

    /**
     * Lists with reports: Title, Link, Groups (Title, Reports).
     *
     * @return ArrayList<ArrayData>
     */
    public function reportLists(): ArrayList
    {
        $lists = ArrayList::create();
        foreach ($this->listControllers() as $controller) {
            $groups = $controller->ListReports();
            if ($groups->count()) {
                $lists->push(ArrayData::create([
                    'Title' => $controller->Title(),
                    'Link' => $controller->Link(),
                    'Groups' => $groups,
                ]));
            }
        }
        return $lists;
    }

    /**
     * The lists (FrontdeskControllers with a url_segment) the member may see;
     * of controllers for the same model, the most general one.
     *
     * @return array<int, FrontdeskController>
     */
    protected function listControllers(): array
    {
        $classes = [];
        foreach (ClassInfo::subclassesFor(FrontdeskController::class, false) as $class) {
            if (is_a($class, FrontdeskSubController::class, true) || is_a($class, self::class, true)
                || (new \ReflectionClass($class))->isAbstract()) {
                continue;
            }
            if (!$class::config()->get('url_segment') || $class::config()->get('reports') === false) {
                continue;
            }
            $classes[] = $class;
        }
        $controllers = [];
        foreach ($classes as $class) {
            foreach ($classes as $other) {
                if ($class !== $other && is_subclass_of($class, $other)
                    && $class::config()->get('managed_model') === $other::config()->get('managed_model')) {
                    continue 2;
                }
            }
            /** @var FrontdeskController $controller */
            $controller = $class::create();
            $controller->setRequest(new HTTPRequest('GET', (string) $class::config()->get('url_segment')));
            $controller->getRequest()->setSession($this->getRequest()->getSession());
            if ($controller->canView()) {
                $controllers[] = $controller;
            }
        }
        usort($controllers, fn ($a, $b) => strcmp((string) $a->Title(), (string) $b->Title()));
        return $controllers;
    }
}

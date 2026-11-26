<?php

namespace Atwx\SilverstripeFrontdeskKit\Search;

use Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController;
use Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskSubController;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Session;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Security\Security;

/**
 * Search across lists: every frontdesk controller with a search
 * (defineSearch()) that the member may view is one group, with its best hits
 * and the number of all matches. Only data – the endpoint (SearchController)
 * renders it as HTML or JSON, the overlay shows it.
 *
 * Groups are ordered by the controllers' config global_search_sort; a
 * controller leaves with global_search: false. Of controllers for the same
 * model only the most general one takes part.
 */
class GlobalSearch
{
    use Injectable;
    use Configurable;

    /**
     * Hits per group.
     */
    private static int $limit = 5;

    /**
     * Groups for the search text: key, type title, count, link to the list
     * with the search, hits (title, text, link, image, icon), templates.
     *
     * @return array<int, array<string, mixed>>
     */
    public function search(string $text, ?int $limit = null, ?HTTPRequest $request = null): array
    {
        if (!Security::getCurrentUser() || !SearchDefinition::words($text)) {
            return [];
        }
        $limit ??= (int) static::config()->get('limit');
        $groups = [];
        foreach ($this->controllers($request) as $controller) {
            $result = $controller->globalSearchResult($text, $limit);
            if ($result['count'] > 0) {
                $groups[] = $result;
            }
        }
        return $groups;
    }

    /**
     * The controllers taking part, for the current member, in their order.
     *
     * @return array<int, FrontdeskController>
     */
    public function controllers(?HTTPRequest $request = null): array
    {
        $request ??= Controller::curr()?->getRequest() ?? new HTTPRequest('GET', '/');
        if (!$request->getSession()) {
            $request->setSession(new Session([]));
        }
        $candidates = [];
        foreach (ClassInfo::subclassesFor(FrontdeskController::class, false) as $class) {
            if (is_a($class, FrontdeskSubController::class, true) || (new \ReflectionClass($class))->isAbstract()) {
                continue;
            }
            if (!$class::config()->get('global_search') || !$class::config()->get('url_segment')) {
                continue;
            }
            /** @var FrontdeskController $controller */
            $controller = $class::create();
            $controller->setRequest($request);
            if (!$controller->hasSearch()) {
                continue;
            }
            $candidates[$class] = $controller;
        }
        // Of controllers for the same model, keep the most general one.
        foreach (array_keys($candidates) as $class) {
            foreach (array_keys($candidates) as $other) {
                if ($class !== $other && is_subclass_of($class, $other)
                    && $class::config()->get('managed_model') === $other::config()->get('managed_model')) {
                    unset($candidates[$class]);
                    break;
                }
            }
        }
        $controllers = array_values(array_filter($candidates, fn (FrontdeskController $c) => $c->canView()));
        usort($controllers, fn ($a, $b) => [(int) $a->config()->get('global_search_sort'), $a::class]
            <=> [(int) $b->config()->get('global_search_sort'), $b::class]);
        return $controllers;
    }
}

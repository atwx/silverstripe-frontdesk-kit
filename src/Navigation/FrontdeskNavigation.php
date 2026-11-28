<?php

namespace Atwx\SilverstripeFrontdeskKit\Navigation;

use Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Model\ArrayData;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;

/**
 * The entries of FrontdeskController.main_navigation and
 * .secondary_navigation for the header (Includes/FrontdeskHeader), on any
 * controller. Each entry, keyed by an identifier:
 *   - title: label
 *   - segment: URL path, the link and the active match
 *   - link: optional link instead of the segment, with a query string
 *     ("followups?view=mine-due"), a CMS path ("admin/flow") or an absolute URL
 *   - controller: optional; the entry is shown only if its canView() allows it
 *   - permission: optional permission code (or a list, any of them)
 *   - badge: optional callable "Class::method" (or [Class, method]) returning
 *     a count or short text; 0 and empty values show no badge
 *   - badge_secondary: optional second counter (same form), shown next to
 *     the badge in another color (e.g. the to-do basket next to own to-dos)
 *   - icon: optional Lucide icon name (kebab case, see the kit's icon set)
 *   - open_search: true opens the overlay of the global search instead of
 *     a link (config FrontdeskController.search_overlay)
 *   - icon_only: true shows only the icon in the horizontal navigation (the
 *     title becomes its tooltip and label; the burger menu shows the title)
 *   - align: 'end' shows the entry (main navigation only) on the right from
 *     lg up, before the user menu, in the order of the config; the burger
 *     menu is unchanged
 *   - default: active on the start page
 *   - children: optional entries of a submenu (one level, same keys); the
 *     group is a dropdown in the horizontal navigation and a titled section
 *     in the burger menu. It is hidden when none of its children is visible,
 *     active when one of them is, and its badge is the sum of their numeric
 *     badges unless it has a badge of its own.
 * Only logged-in members see entries.
 */
class FrontdeskNavigation
{
    use Injectable;

    /**
     * @return ArrayList<ArrayData>
     */
    public function main(): ArrayList
    {
        // Built once per member and page (header and burger menu use it several times; badges query)
        $config = (array) FrontdeskController::config()->get('main_navigation');
        $key = (int) Security::getCurrentUser()?->ID . ' ' . $this->currentURL() . ' ' . md5(serialize($config));
        if ($this->mainCache === null || $this->mainCache[0] !== $key) {
            $this->mainCache = [$key, $this->entries($config)];
        }
        return $this->mainCache[1];
    }

    /**
     * @var array{0: string, 1: ArrayList<ArrayData>}|null
     */
    private ?array $mainCache = null;

    /**
     * Entries of the main navigation on the left (false) or right (true).
     *
     * @return ArrayList<ArrayData>
     */
    public function mainAligned(bool $end): ArrayList
    {
        return $this->main()->filterByCallback(fn (ArrayData $entry) => (bool) $entry->AlignEnd === $end);
    }

    /**
     * @return ArrayList<ArrayData>
     */
    public function secondary(): ArrayList
    {
        return $this->entries((array) FrontdeskController::config()->get('secondary_navigation'));
    }

    /**
     * @param array<mixed> $entries
     * @return ArrayList<ArrayData>
     */
    public function entries(array $entries, ?string $url = null): ArrayList
    {
        $navigation = ArrayList::create();
        $member = Security::getCurrentUser();
        if (!$member) {
            return $navigation;
        }
        $url ??= $this->currentURL();
        foreach ($entries as $entry) {
            if (!is_array($entry) || !$this->allowed($entry)) {
                continue;
            }
            if (isset($entry['children'])) {
                $group = $this->group($entry, $url);
                if ($group) {
                    $navigation->push($group);
                }
                continue;
            }
            $segment = trim((string) ($entry['segment'] ?? ''), '/');
            $link = (string) ($entry['link'] ?? $segment);
            $navigation->push(ArrayData::create([
                'Title' => (string) ($entry['title'] ?? $segment),
                'Link' => $link,
                'Active' => $this->isActive($entry, $segment, $link, $url),
                'External' => (bool) preg_match('#^[a-z][a-z0-9+.-]*://#i', $link),
                'Badge' => $this->badge($entry['badge'] ?? null),
                'BadgeSecondary' => $this->badge($entry['badge_secondary'] ?? null),
                'Icon' => (string) ($entry['icon'] ?? ''),
                'IconOnly' => !empty($entry['icon_only']) && !empty($entry['icon']),
                'OpenSearch' => !empty($entry['open_search']),
                'AlignEnd' => ($entry['align'] ?? '') === 'end',
                'Children' => null,
            ]));
        }
        return $navigation;
    }

    /**
     * A submenu: null if none of its children is visible. Nested children
     * deeper than one level are ignored.
     *
     * @param array<string, mixed> $entry
     */
    private function group(array $entry, string $url): ?ArrayData
    {
        $children = array_filter(
            (array) $entry['children'],
            fn ($child) => is_array($child) && !isset($child['children'])
        );
        $items = $this->entries($children, $url);
        if (!$items->count()) {
            return null;
        }
        $badge = $this->badge($entry['badge'] ?? null);
        if ($badge === '') {
            $sum = 0;
            foreach ($items as $item) {
                $sum += is_numeric($item->Badge) ? (int) $item->Badge : 0;
            }
            $badge = $sum > 0 ? (string) $sum : '';
        }
        return ArrayData::create([
            'Title' => (string) ($entry['title'] ?? ''),
            'Link' => '',
            'Active' => (bool) $items->find('Active', true),
            'External' => false,
            'Badge' => $badge,
            'BadgeSecondary' => $this->badge($entry['badge_secondary'] ?? null),
            'Icon' => (string) ($entry['icon'] ?? ''),
            'IconOnly' => !empty($entry['icon_only']) && !empty($entry['icon']),
            'AlignEnd' => ($entry['align'] ?? '') === 'end',
            'Children' => $items,
        ]);
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function allowed(array $entry): bool
    {
        $class = $entry['controller'] ?? null;
        if ($class) {
            if (!is_string($class) || !class_exists($class) || !singleton($class)->canView()) {
                return false;
            }
        }
        $codes = $entry['permission'] ?? null;
        if ($codes) {
            return Permission::check(array_values((array) $codes), 'any');
        }
        return true;
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function isActive(array $entry, string $segment, string $link, string $url): bool
    {
        $path = $segment !== '' ? $segment : trim((string) parse_url($link, PHP_URL_PATH), '/');
        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $link) && $segment === '') {
            return false;
        }
        if ($path !== '' && ($url === $path || str_starts_with($url, $path . '/'))) {
            return true;
        }
        return !empty($entry['default']) && $url === '';
    }

    private function badge(mixed $callable): string
    {
        if (is_string($callable) && str_contains($callable, '::')) {
            $callable = explode('::', $callable, 2);
        }
        if (!is_callable($callable)) {
            return '';
        }
        $value = $callable();
        return $value === null || $value === 0 || $value === '0' || $value === false ? '' : (string) $value;
    }

    private function currentURL(): string
    {
        $request = Controller::curr()?->getRequest();
        return $request ? trim((string) $request->getURL(), '/') : '';
    }
}

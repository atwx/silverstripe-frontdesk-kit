<?php

namespace Atwx\SilverstripeFrontdeskKit\Table;

use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\DataList;

/**
 * An additional export of a list: its own button, file name and columns,
 * optionally only in some list views (FrontdeskController::defineExports()).
 * It exports the list as filtered on screen.
 *
 *   Export::create('fees', 'Fees ' . $year)
 *       ->fileName('fees-' . $year)
 *       ->columns(fn () => ColumnCollection::create()->make(...)->end())   // or ['Title', 'City']
 *       ->query(fn (DataList $list) => $list->filter('Active', 1))
 *       ->views(['active']);                                               // only in these views
 */
class Export
{
    use Injectable;

    /**
     * @var array<int, string>|callable|null
     */
    private $columns = null;

    /**
     * @var callable|null
     */
    private $query = null;

    private ?string $fileName = null;

    /**
     * @var array<int, string>|null
     */
    private ?array $views = null;

    public function __construct(private string $key, private string $title)
    {
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * File name without extension (".xlsx" is added); default: the key and
     * the date.
     */
    public function fileName(string $fileName): static
    {
        $this->fileName = $fileName;
        return $this;
    }

    public function getFileName(): string
    {
        $name = $this->fileName ?? ('export-' . $this->key . '-' . date('Y-m-d'));
        return (string) preg_replace('/[^\w.\- ]+/u', '_', $name) . '.xlsx';
    }

    /**
     * Column names of the list's columns, or a callable returning a
     * ColumnCollection (e.g. with a column per year or per chosen option).
     *
     * @param array<int, string>|callable $columns
     */
    public function columns(array|callable $columns): static
    {
        $this->columns = $columns;
        return $this;
    }

    /**
     * @return array<int, string>|callable|null
     */
    public function getColumns(): array|callable|null
    {
        return $this->columns;
    }

    /**
     * Narrows the filtered list further: fn (DataList $list): DataList.
     */
    public function query(callable $query): static
    {
        $this->query = $query;
        return $this;
    }

    /**
     * @param DataList<\SilverStripe\ORM\DataObject> $list
     * @return DataList<\SilverStripe\ORM\DataObject>
     */
    public function applyTo(DataList $list): DataList
    {
        return $this->query ? ($this->query)($list) : $list;
    }

    /**
     * Only offered in these list views (keys; '' = no view chosen).
     *
     * @param array<int, string> $views
     */
    public function views(array $views): static
    {
        $this->views = $views;
        return $this;
    }

    public function isAvailableIn(?string $viewKey): bool
    {
        return $this->views === null || in_array((string) $viewKey, $this->views, true);
    }
}

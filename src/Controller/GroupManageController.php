<?php

namespace Atwx\SilverstripeFrontdeskKit\Controller;

use Atwx\SilverstripeFrontdeskKit\Filter\FilterCollection;
use Atwx\SilverstripeFrontdeskKit\Filter\TextFilter;
use Atwx\SilverstripeFrontdeskKit\Table\ColumnCollection;
use Atwx\SilverstripeFrontdeskKit\Table\RowAction;
use SilverStripe\Forms\CheckboxSetField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Group;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;

class GroupManageController extends FrontdeskController
{
    private static $managed_model = Group::class;
    private static $url_segment = 'groups';
    private static $title = 'Gruppen';
    private static $page_length = 30;

    protected function defineColumns(): ColumnCollection
    {
        return ColumnCollection::create()
            ->make('Title', 'Name')->link('edit/{ID}')->end()
            ->make('MemberCount', 'Mitglieder')
                ->format(fn($v, $r) => (int) $r->DirectMembers()->count())
                ->end()
            ->make('PermissionCount', 'Rechte')
                ->format(fn($v, $r) => (int) Permission::get()->filter('GroupID', $r->ID)->count())
                ->end();
    }

    protected function defineFilters(): FilterCollection
    {
        return FilterCollection::create()
            ->add(
                TextFilter::create('q', 'Suche')
                    ->apply(fn($list, $v) => $list->filter('Title:PartialMatch', $v))
            );
    }

    protected function defineRowActions(DataObject $record): array
    {
        $actions = [];
        if ($this->canEdit()) {
            $actions[] = RowAction::htmx('Bearbeiten', $this->Link('edit/' . $record->ID));
            $actions[] = RowAction::delete($this->Link('delete/' . $record->ID));
        }
        return $actions;
    }

    protected function formFields(FieldList $fields): FieldList
    {
        $id = (int) $this->getRequest()->param('ID');
        $group = $id ? Group::get()->byID($id) : null;

        $memberOptions = Member::get()->sort('Surname', 'ASC')->map('ID', 'Name')->toArray();

        $permissionOptions = [];
        foreach (Permission::get_codes(true) as $category => $codes) {
            foreach ($codes as $code => $details) {
                $label = $details['name'] ?? $code;
                $permissionOptions[$code] = "[$category] $label";
            }
        }
        ksort($permissionOptions);

        $permissionField = CheckboxSetField::create('PermissionCodes', 'Rechte', $permissionOptions);
        if ($group && $group->exists()) {
            $permissionField->setValue(
                Permission::get()->filter('GroupID', $group->ID)->column('Code')
            );
        }

        $fields = FieldList::create(
            TextField::create('Title', 'Name'),
            CheckboxSetField::create('DirectMembers', 'Mitglieder', $memberOptions),
            $permissionField,
        );

        $this->extend('updateFormFields', $fields, $group);

        return $fields;
    }

    public function save($data, Form $form)
    {
        return $this->persist($data, $form, redirectToEdit: false);
    }

    public function savecontinue($data, Form $form)
    {
        return $this->persist($data, $form, redirectToEdit: true);
    }

    protected function persist(array $data, Form $form, bool $redirectToEdit)
    {
        $id = !empty($data['ID']) ? (int) $data['ID'] : 0;
        $group = $id ? Group::get()->byID($id) : Group::create();
        if ($id && !$group) {
            return $this->httpError(404);
        }

        $form->saveInto($group);
        $group->write();

        $this->reconcilePermissions($group, $data['PermissionCodes'] ?? []);

        $this->extend('onAfterSaveGroup', $group, $data);

        if ($redirectToEdit) {
            return $this->redirect($this->Link('edit/' . $group->ID));
        }
        $backURL = $data['BackURL'] ?? $this->Link();
        return $this->redirect($backURL ?: $this->Link());
    }

    protected function reconcilePermissions(Group $group, array $codes): void
    {
        $codes = array_values(array_filter(array_map('strval', $codes)));
        $existing = Permission::get()->filter('GroupID', $group->ID);
        $existingByCode = [];
        foreach ($existing as $perm) {
            $existingByCode[$perm->Code] = $perm;
        }

        foreach ($codes as $code) {
            if (!isset($existingByCode[$code])) {
                $perm = Permission::create();
                $perm->Code = $code;
                $perm->GroupID = $group->ID;
                $perm->write();
            }
        }

        foreach ($existingByCode as $code => $perm) {
            if (!in_array($code, $codes, true)) {
                $perm->delete();
            }
        }
    }
}

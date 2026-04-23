<?php

namespace Atwx\SilverstripeFrontdeskKit\Controller;

use Atwx\SilverstripeFrontdeskKit\Filter\FilterCollection;
use Atwx\SilverstripeFrontdeskKit\Filter\TextFilter;
use Atwx\SilverstripeFrontdeskKit\Table\ColumnCollection;
use Atwx\SilverstripeFrontdeskKit\Table\RowAction;
use SilverStripe\Forms\CheckboxSetField;
use SilverStripe\Forms\ConfirmedPasswordField;
use SilverStripe\Forms\EmailField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Group;
use SilverStripe\Security\Member;

/**
 * Generic member management. Apps can extend fields/save behaviour via the
 * extension hooks updateFormFields() and onAfterSaveMember().
 */
class UserManageController extends FrontdeskController
{
    private static $managed_model = Member::class;
    private static $url_segment = 'users';
    private static $title = 'Benutzer';
    private static $page_length = 30;

    protected function defineColumns(): ColumnCollection
    {
        return ColumnCollection::create()
            ->make('FirstName', 'Vorname')->link('edit/{ID}')->end()
            ->make('Surname', 'Nachname')->end()
            ->make('Email', 'E-Mail')->end()
            ->make('Groups', 'Gruppen')
                ->format(fn($v, $r) => htmlspecialchars(implode(', ', $r->DirectGroups()->column('Title'))))
                ->type('html')
                ->end();
    }

    protected function defineFilters(): FilterCollection
    {
        return FilterCollection::create()
            ->add(
                TextFilter::create('q', 'Suche')
                    ->apply(fn($list, $v) => $list->filterAny([
                        'FirstName:PartialMatch' => $v,
                        'Surname:PartialMatch' => $v,
                        'Email:PartialMatch' => $v,
                    ]))
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
        $member = $id ? Member::get()->byID($id) : null;

        $passwordField = ConfirmedPasswordField::create('Password', 'Passwort');
        $passwordField->setCanBeEmpty(true);

        $groupOptions = Group::get()->sort('Title')->map('ID', 'Title')->toArray();

        $fields = FieldList::create(
            TextField::create('FirstName', 'Vorname'),
            TextField::create('Surname', 'Nachname'),
            EmailField::create('Email', 'E-Mail'),
            $passwordField,
            CheckboxSetField::create('DirectGroups', 'Gruppen', $groupOptions),
        );

        $this->extend('updateFormFields', $fields, $member);

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
        $member = $id ? Member::get()->byID($id) : Member::create();
        if ($id && !$member) {
            return $this->httpError(404);
        }

        $form->saveInto($member);
        $member->write();

        $this->extend('onAfterSaveMember', $member, $data);

        if ($redirectToEdit) {
            return $this->redirect($this->Link('edit/' . $member->ID));
        }
        $backURL = $data['BackURL'] ?? $this->Link();
        return $this->redirect($backURL ?: $this->Link());
    }
}

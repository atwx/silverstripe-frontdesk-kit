<?php

namespace Atwx\SilverstripeFrontdeskKit\Controller;

use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Forms\ConfirmedPasswordField;
use SilverStripe\Forms\EmailField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\TextField;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;

class ProfileController extends FrontdeskController
{
    private static $managed_model = Member::class;
    private static $url_segment = 'profile';
    private static $title = 'Profil';

    private static $allowed_actions = [
        'index',
        'EditForm',
        'save',
    ];

    public function init()
    {
        parent::init();
        $this->templates['index'] = [
            static::class,
            FrontdeskController::class . '_edit',
            FrontdeskController::class,
            'Page',
        ];
    }

    public function canView($member = null): bool
    {
        return (bool) ($member ?: Security::getCurrentUser());
    }

    public function canEdit($member = null): bool
    {
        return (bool) ($member ?: Security::getCurrentUser());
    }

    public function Link($action = null): string
    {
        return Controller::join_links('profile', $action);
    }

    public function index(HTTPRequest $request)
    {
        $me = Security::getCurrentUser();
        $form = $this->EditForm();
        if ($me) {
            $form->loadDataFrom($me);
        }
        return [
            'Form' => $form,
            'Item' => $me,
            'Title' => 'Mein Profil',
            'Actions' => false,
        ];
    }

    public function EditForm(): Form
    {
        $me = Security::getCurrentUser();
        $passwordField = ConfirmedPasswordField::create('Password', 'Passwort');
        $passwordField->setCanBeEmpty(true);

        $fields = FieldList::create(
            TextField::create('FirstName', 'Vorname'),
            TextField::create('Surname', 'Nachname'),
            EmailField::create('Email', 'E-Mail'),
            $passwordField,
            HiddenField::create('ID', 'ID', $me?->ID),
        );

        return Form::create($this, 'EditForm', $fields, FieldList::create(
            FormAction::create('save', 'Speichern'),
            LiteralField::create('Cancel', '<a href="/" class="btn">Abbrechen</a>')
        ));
    }

    public function save($data, Form $form)
    {
        $me = Security::getCurrentUser();
        if (!$me) {
            return $this->httpError(403);
        }
        $form->saveInto($me);
        $me->write();
        $form->sessionMessage('Profil gespeichert.', 'good');
        return $this->redirect($this->Link());
    }
}

<?php

namespace Atwx\SilverstripeFrontdeskKit\Controller;

use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;
use SilverStripe\SiteConfig\SiteConfig;

/**
 * Edit SiteConfig from inside the Frontdesk UI. Shows every leaf field that
 * SiteConfig (and its extensions) exposes via getCMSFields(). Admin-only.
 */
class SiteConfigController extends FrontdeskController
{
    private static $url_segment = 'site-config';
    private static $title = 'Einstellungen';

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
        $member = $member ?: Security::getCurrentUser();
        return $member && Permission::checkMember($member, 'ADMIN');
    }

    public function canEdit($member = null): bool
    {
        return $this->canView($member);
    }

    public function Link($action = null): string
    {
        return Controller::join_links('site-config', $action);
    }

    public function index(HTTPRequest $request)
    {
        $form = $this->EditForm();
        $form->loadDataFrom(SiteConfig::current_site_config());
        return [
            'Form'    => $form,
            'Item'    => SiteConfig::current_site_config(),
            'Title'   => $this->config()->get('title'),
            'Actions' => false,
        ];
    }

    public function EditForm(): Form
    {
        $siteConfig = SiteConfig::current_site_config();
        $cmsFields = $siteConfig->getCMSFields();

        // Whitelist tabs we want to expose. The "Access" tab manages CMS
        // permissions and doesn't belong in a settings editor.
        $skipTabs = ['Root.Access'];

        $fields = FieldList::create();
        foreach ($cmsFields->dataFields() ?? [] as $field) {
            $skip = false;
            foreach ($skipTabs as $tabName) {
                if ($cmsFields->fieldByName($tabName) && $cmsFields->fieldByName($tabName)->fieldByName($field->getName())) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) {
                continue;
            }
            $fields->push($field);
        }
        $fields->push(HiddenField::create('ID', 'ID', $siteConfig->ID));

        return Form::create($this, 'EditForm', $fields, FieldList::create(
            FormAction::create('save', 'Speichern'),
            LiteralField::create('Cancel', '<a href="/" class="btn">Abbrechen</a>')
        ));
    }

    public function save($data, Form $form)
    {
        if (!$this->canEdit()) {
            return $this->httpError(403);
        }
        $siteConfig = SiteConfig::current_site_config();
        $form->saveInto($siteConfig);
        $siteConfig->write();
        $form->sessionMessage('Einstellungen gespeichert.', 'good');
        return $this->redirect($this->Link());
    }
}

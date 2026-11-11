<?php

namespace Atwx\SilverstripeFrontdeskKit\Controller;

use Atwx\SilverstripeFrontdeskKit\Table\ActionException;
use Atwx\SilverstripeFrontdeskKit\Table\RecordAction;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Model\ArrayData;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\SecurityToken;

/**
 * Record actions and one-time flash messages, shared by FrontdeskController
 * and StandaloneRecordController.
 *
 * The using class provides defineRecordActions() and recordActionUrl().
 */
trait RecordActionsTrait
{
    /** Flash message, read once per request (false = not read yet). */
    private ArrayData|null|false $flashMessage = false;

    /**
     * @return RecordAction[]
     */
    abstract protected function defineRecordActions(DataObject $record): array;

    /**
     * URL the record action dialogs of the record post to.
     */
    abstract protected function recordActionUrl(DataObject $record): string;

    /**
     * Run the posted record action on the record (POST with CSRF token), then
     * redirect to BackURL or $returnUrl.
     */
    protected function runRecordAction(HTTPRequest $request, DataObject $record, string $returnUrl): HTTPResponse
    {
        if (!$request->isPOST() || !SecurityToken::inst()->checkRequest($request)) {
            $this->httpError(400);
        }
        $action = null;
        foreach ($this->defineRecordActions($record) as $candidate) {
            if ($candidate->getName() === (string) $request->postVar('RecordAction')) {
                $action = $candidate;
            }
        }
        if (!$action) {
            $this->httpError(400);
        }

        $option = $request->postVar('Option');
        try {
            $result = $action->run(
                $record,
                $option === null ? null : (string) $option,
                (string) $request->postVar('Comment'),
                (array) $request->postVar('Select')
            );
        } catch (ActionException $e) {
            $this->flash($e->getMessage(), 'error');
            $result = null;
        }
        if ($result instanceof HTTPResponse) {
            return $result;
        }
        if (is_string($result) && $result !== '') {
            $this->flash($result);
        }
        $back = (string) $request->postVar('BackURL');
        return $this->redirect($back !== '' && Director::is_site_url($back) ? $back : $returnUrl);
    }

    /**
     * Template data for the record actions of a record.
     */
    protected function recordActionsFor(DataObject $record): ArrayList
    {
        $result = ArrayList::create();
        foreach ($this->defineRecordActions($record) as $action) {
            $options = ArrayList::create();
            foreach ($action->getOptions() as $value => $label) {
                $options->push(ArrayData::create(['Value' => $value, 'Label' => $label]));
            }
            $selects = ArrayList::create();
            foreach ($action->getSelects() as $name => $select) {
                $selectOptions = ArrayList::create();
                foreach ($select['options'] as $value => $label) {
                    // Numeric option keys become integers in PHP arrays.
                    $selectOptions->push(ArrayData::create([
                        'Value' => (string) $value,
                        'Label' => $label,
                        'Selected' => (string) $value === $select['value'],
                    ]));
                }
                $selects->push(ArrayData::create([
                    'Name' => $name,
                    'Label' => $select['label'],
                    'EmptyLabel' => $select['empty'],
                    'Options' => $selectOptions,
                ]));
            }
            $result->push(ArrayData::create([
                'Selects'         => $selects,
                'Name'            => $action->getName(),
                'Label'           => $action->getLabel(),
                'Description'     => $action->getDescription(),
                'Options'         => $options,
                'HasOptions'      => $action->hasOptions(),
                'CommentLabel'    => $action->getCommentLabel(),
                'CommentRequired' => $action->isCommentRequired(),
                'SubmitLabel'     => $action->getSubmitLabel(),
                'Url'             => $this->recordActionUrl($record),
                'RecordID'        => $record->ID,
            ]));
        }
        return $result;
    }

    /**
     * Message shown once on the next page (type: success, error, info).
     */
    protected function flash(string $message, string $type = 'success'): void
    {
        $this->getRequest()->getSession()->set('FrontdeskFlash', ['Text' => $message, 'Type' => $type]);
    }

    public function FlashMessage(): ?ArrayData
    {
        if ($this->flashMessage === false) {
            $session = $this->getRequest()->getSession();
            $data = $session->get('FrontdeskFlash');
            $session->clear('FrontdeskFlash');
            $this->flashMessage = is_array($data) ? ArrayData::create($data) : null;
        }
        return $this->flashMessage;
    }

    public function SecurityTokenValue(): string
    {
        // Null when CSRF protection is disabled (e.g. in tests).
        return (string) SecurityToken::inst()->getValue();
    }
}

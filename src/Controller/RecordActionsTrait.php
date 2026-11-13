<?php

namespace Atwx\SilverstripeFrontdeskKit\Controller;

use Atwx\SilverstripeFrontdeskKit\Table\ActionException;
use Atwx\SilverstripeFrontdeskKit\Table\ActionResult;
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
                array_merge((array) $request->postVar('Input'), (array) $request->postVar('Select'))
            );
        } catch (ActionException $e) {
            $this->flash($e->getMessage(), 'error');
            $result = null;
        }
        if ($result instanceof HTTPResponse) {
            return $result;
        }
        if ($result instanceof ActionResult || (is_string($result) && $result !== '')) {
            $this->flash($result);
        }
        $back = (string) $request->postVar('BackURL');
        return $this->redirect($back !== '' && Director::is_site_url($back) ? $back : $returnUrl);
    }

    /**
     * Template data for the record actions of a record; with a record page
     * only the actions of that page (RecordAction::pages() or the config
     * record_action_pages).
     */
    protected function recordActionsFor(DataObject $record, ?string $page = null): ArrayList
    {
        $result = ArrayList::create();
        $configured = (array) static::config()->get('record_action_pages');
        foreach ($this->defineRecordActions($record) as $action) {
            if ($page !== null && !$action->getPages() && isset($configured[$action->getName()])) {
                $action->pages(...array_map('strval', (array) $configured[$action->getName()]));
            }
            if (!$action->isOnPage($page)) {
                continue;
            }
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
            $multiSelects = ArrayList::create();
            foreach ($action->getMultiSelects() as $name => $multi) {
                $multiOptions = ArrayList::create();
                foreach ($multi['options'] as $value => $label) {
                    $multiOptions->push(ArrayData::create([
                        'Value' => (string) $value,
                        'Label' => $label,
                        'Checked' => in_array((string) $value, $multi['values'], true),
                    ]));
                }
                $multiSelects->push(ArrayData::create([
                    'Name' => $name,
                    'Label' => $multi['label'],
                    'Options' => $multiOptions,
                ]));
            }
            $inputs = ArrayList::create();
            foreach ($action->getInputs() as $name => $input) {
                $inputs->push(ArrayData::create([
                    'Name' => $name,
                    'Label' => $input['label'],
                    'Type' => $input['type'],
                    'Value' => $input['value'],
                    'Required' => $input['required'],
                ]));
            }
            $result->push(ArrayData::create([
                'MultiSelects'    => $multiSelects,
                'Inputs'          => $inputs,
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
    protected function flash(string|ActionResult $message, string $type = 'success'): void
    {
        $data = $message instanceof ActionResult
            ? ['Text' => $message->getText(), 'Type' => $message->getType(), 'Links' => $message->getLinks()]
            : ['Text' => $message, 'Type' => $type, 'Links' => []];
        $this->getRequest()->getSession()->set('FrontdeskFlash', $data);
    }

    public function FlashMessage(): ?ArrayData
    {
        if ($this->flashMessage === false) {
            $session = $this->getRequest()->getSession();
            $data = $session->get('FrontdeskFlash');
            $session->clear('FrontdeskFlash');
            if (is_array($data)) {
                $data['Links'] = ArrayList::create(array_map(
                    fn ($link) => ArrayData::create($link),
                    is_array($data['Links'] ?? null) ? $data['Links'] : []
                ));
            }
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

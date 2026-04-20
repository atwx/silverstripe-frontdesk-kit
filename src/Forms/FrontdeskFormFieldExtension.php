<?php

namespace Atwx\SilverstripeFrontdeskKit\Forms;

use Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Extension;
use SilverStripe\Security\Security;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\CurrencyField;
use SilverStripe\Forms\DateField;
use SilverStripe\Forms\DatetimeField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\EmailField;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\FormField;
use SilverStripe\Forms\GroupedDropdownField;
use SilverStripe\Forms\NumericField;
use SilverStripe\Forms\PasswordField;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\UrlField;

/**
 * Applies DaisyUI utility classes to form fields, but only when the request is
 * being handled by a FrontdeskController. Keeps the SilverStripe CMS admin
 * unaffected.
 *
 * @extends Extension<FormField>
 */
class FrontdeskFormFieldExtension extends Extension
{
    private const FIELD_CLASS_MAP = [
        TextareaField::class => 'textarea w-full',
        DropdownField::class => 'select w-full',
        GroupedDropdownField::class => 'select w-full',
        CheckboxField::class => 'checkbox',
        TextField::class => 'input w-full',
        EmailField::class => 'input w-full',
        PasswordField::class => 'input w-full',
        NumericField::class => 'input w-full',
        UrlField::class => 'input w-full',
        CurrencyField::class => 'input w-full',
        DateField::class => 'input w-full',
        DatetimeField::class => 'input w-full',
    ];

    private const BTN_VARIANT_CLASSES = [
        'btn-ghost',
        'btn-link',
        'btn-outline',
        'btn-soft',
        'btn-error',
        'btn-warning',
        'btn-success',
        'btn-info',
        'btn-secondary',
        'btn-accent',
        'btn-neutral',
    ];

    protected function onBeforeRender($context, array &$properties): void
    {
        $this->applyDefaultClasses();
    }

    protected function onBeforeRenderHolder($context, array &$properties): void
    {
        $this->applyDefaultClasses();
    }

    private function applyDefaultClasses(): void
    {
        if (!$this->isFrontdeskContext()) {
            return;
        }

        $owner = $this->getOwner();

        if ($owner instanceof FormAction) {
            $owner->addExtraClass('btn');
            if (!$this->hasAnyClass($owner, self::BTN_VARIANT_CLASSES)) {
                $owner->addExtraClass('btn-primary');
            }
            return;
        }

        foreach (self::FIELD_CLASS_MAP as $class => $classes) {
            if ($owner instanceof $class) {
                $owner->addExtraClass($classes);
                return;
            }
        }
    }

    private function isFrontdeskContext(): bool
    {
        $controller = Controller::curr();
        return $controller instanceof FrontdeskController
            || $controller instanceof Security;
    }

    /**
     * @param string[] $classes
     */
    private function hasAnyClass(FormField $field, array $classes): bool
    {
        foreach ($classes as $class) {
            if ($field->hasExtraClass($class)) {
                return true;
            }
        }
        return false;
    }
}

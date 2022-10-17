<?php

namespace wcf\acp\form;

use wcf\data\minecraft\MinecraftAction;
use wcf\data\minecraft\MinecraftList;
use wcf\form\AbstractFormBuilderForm;
use wcf\system\form\builder\container\FormContainer;
use wcf\system\form\builder\data\processor\CustomFormDataProcessor;
use wcf\system\form\builder\field\PasswordFormField;
use wcf\system\form\builder\field\TextFormField;
use wcf\system\form\builder\field\TitleFormField;
use wcf\system\form\builder\field\validation\FormFieldValidationError;
use wcf\system\form\builder\field\validation\FormFieldValidator;
use wcf\system\form\builder\IFormDocument;
use wcf\system\user\authentication\password\algorithm\Bcrypt;
use wcf\system\user\authentication\password\IPasswordAlgorithm;
use wcf\system\user\authentication\password\PasswordAlgorithmManager;
use wcf\system\user\multifactor\BackupMultifactorMethod;

/**
 * MinecraftAdd Form class
 *
 * @author   xXSchrandXx
 * @license  Creative Commons Zero v1.0 Universal (http://creativecommons.org/publicdomain/zero/1.0/)
 * @package  WoltLabSuite\Core\Acp\Form
 */
class MinecraftAddForm extends AbstractFormBuilderForm
{
    /**
     * @inheritDoc
     */
    public $neededPermissions = ['admin.minecraft.canManageConnection'];

    /**
     * @inheritDoc
     */
    public $activeMenuItem = 'wcf.acp.menu.link.configuration.minecraft.minecraftList.add';

    /**
     * @inheritDoc
     */
    public $objectActionClass = MinecraftAction::class;

    /**
     * @var \wcf\data\minecraft\Minecraft
     */
    public $formObject;

    /**
     * @inheritDoc
     */
    protected function createForm()
    {
        parent::createForm();

        $this->form->appendChild(
            FormContainer::create('data')
                ->appendChildren([
                    TitleFormField::create()
                        ->value('Default')
                        ->maximumLength(20)
                        ->required(),
                    TextFormField::create('user')
                        ->label('wcf.acp.form.minecraftAdd.user')
                        ->required()
                        ->addValidator(new FormFieldValidator('duplicate', function (TextFormField $field) {
                            if ($this->formAction === 'edit' && $this->formObject->getUser() === $field->getValue()) {
                                return;
                            }
                            $minecraftList = new MinecraftList();
                            $minecraftList->getConditionBuilder()->add('user = ?', [$field->getValue()]);
                            if ($minecraftList->countObjects() !== 0) {
                                $field->addValidationError(
                                    new FormFieldValidationError(
                                        'duplicate',
                                        'wcf.acp.form.minecraftAdd.user.error.duplicate'
                                    )
                                );
                            }
                        })),
                    PasswordFormField::create('password')
                        ->label('wcf.acp.form.minecraftAdd.password')
                        ->placeholder(($this->formAction == 'edit') ? 'wcf.acp.updateServer.loginPassword.noChange' : '')
                        ->required($this->formAction !== 'edit')
                ])
        );
    }

    /**
     * @inheritDoc
     */
    public function save()
    {
        if ($this->formAction == 'create') {
            $this->additionalFields['creationDate'] = TIME_NOW;
        }

        if (!empty($this->form->getData()['data']['password'])) {
            /**
             * @var IPasswordAlgorithm
             */
            $algorithm = new Bcrypt(9);
            $algorithmName = PasswordAlgorithmManager::getInstance()->getNameFromAlgorithm($algorithm);
            $this->form->getData()['data']['password'] = $algorithmName . ':' . $algorithm->hash($this->form->getData()['data']['password']);
        }

        parent::save();
    }
}

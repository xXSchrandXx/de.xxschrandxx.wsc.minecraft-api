<?php

namespace wcf\acp\form;

use wcf\data\minecraft\MinecraftAction;
use wcf\form\AbstractFormBuilderForm;
use wcf\system\exception\MinecraftException;
use wcf\system\form\builder\container\FormContainer;
use wcf\system\form\builder\field\IntegerFormField;
use wcf\system\form\builder\field\PasswordFormField;
use wcf\system\form\builder\field\SingleSelectionFormField;
use wcf\system\form\builder\field\TextFormField;
use wcf\system\form\builder\field\validation\FormFieldValidationError;
use wcf\system\form\builder\field\validation\FormFieldValidator;
use wcf\system\minecraft\MinecraftConnectionHandler;

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
     * @inheritDoc
     */
    protected function createForm()
    {
        parent::createForm();

        $this->form->appendChild(
            FormContainer::create('data')
                ->appendChildren([
                    TextFormField::create('connectionName')
                        ->label('wcf.page.minecraftAdd.connectionName')
                        ->description('wcf.page.minecraftAdd.connectionName.description')
                        ->value('Default')
                        ->maximumLength(20)
                        ->required(),
                    TextFormField::create('hostname')
                        ->label('wcf.page.minecraftAdd.hostname')
                        ->description('wcf.page.minecraftAdd.hostname.description')
                        ->value('localhost')
                        ->maximumLength(50)
                        ->required()
                        ->addValidator(new FormFieldValidator('hostnameCheck', function (TextFormField $field) {
                            /** @var IntegerFormField $rconPortField */
                            $rconPortField = $field->getDocument()->getNodeById('rconPort');
                            /** @var PasswordFormField $passwordField */
                            $passwordField = $field->getDocument()->getNodeById('password');

                            $password = $passwordField->getSaveValue();
                            if ($this->formAction == 'edit' && empty($password)) {
                                $password = $this->formObject->password;
                            }
                            try {
                                new MinecraftConnectionHandler($field->getSaveValue(), $rconPortField->getSaveValue(), $password);
                            } catch (MinecraftException $e) {
                                if (\ENABLE_DEBUG_MODE) {
                                    \wcf\functions\exception\logThrowable($e);
                                }
                                switch ($e->getCode()) {
                                    case 100:
                                        $field->addValidationError(
                                            new FormFieldValidationError('proxyError', 'wcf.page.minecraftAdd.proxyErrorDynamic', ['msg' => $e->getMessage()])
                                        );
                                        break;
                                    default:
                                        $field->addValidationError(
                                            new FormFieldValidationError('cantConnect', 'wcf.page.minecraftAdd.cantConnectDynamic', ['msg' => $e->getMessage()])
                                        );
                                        break;
                                }
                            } catch (\Exception $e) {
                                if (\ENABLE_DEBUG_MODE) {
                                    \wcf\functions\exception\logThrowable($e);
                                }
                                $field->addValidationError(
                                    new FormFieldValidationError('cantConnect', 'wcf.page.minecraftAdd.cantConnect')
                                );
                            }
                        })),
                    SingleSelectionFormField::create('type')
                        ->label('wcf.page.minecraftAdd.type')
                        ->description('wcf.page.minecraftAdd.type.description')
                        ->options(['vanilla' => 'Vanilla', 'spigot' => 'Spigot', 'bungee' => 'Bungee'], false, false)
                        ->value('vanilla')
                        ->required(),
                    IntegerFormField::create('rconPort')
                        ->label('wcf.page.minecraftAdd.rconPort')
                        ->description('wcf.page.minecraftAdd.rconPort.description')
                        ->minimum(1)
                        ->maximum(65535)
                        ->value(25575)
                        ->required(),
                    PasswordFormField::create('password')
                        ->label('wcf.page.minecraftAdd.password')
                        ->placeholder(($this->formAction == 'edit') ? 'wcf.acp.updateServer.loginPassword.noChange' : '')
                        ->required(($this->formAction == 'edit') ? false : true),
                ])
        );
    }

    /**
     * @inheritDoc
     */
    public function save()
    {
        if ($this->formAction == 'create') {
            $this->additionalFields['creationDate'] = \TIME_NOW;
        }

        parent::save();
    }
}

<?php

namespace FluentFormMailPoet\Integrations;

use FluentForm\App\Services\Integrations\IntegrationManager;
use FluentForm\Framework\Foundation\Application;
use FluentForm\Framework\Helpers\ArrayHelper as Arr;
use MailPoet\Models\CustomField;

class Bootstrap extends IntegrationManager
{
    public $hasGlobalMenu = false;

    public $disableGlobalSettings = 'yes';

    public function __construct(Application $app)
    {
        parent::__construct(
            $app,
            'MailPoet',
            'mailpoet',
            '_fluentform_mailpoet_settings',
            'mailpoet_feeds',
            99
        );

        $this->logo = FFMAILPOET_URL . 'assets/mailpoet.png';

        $this->description = 'Connect MailPoet with WP Fluent Forms and subscribe a contact when a form is submitted.';

        $this->registerAdminHooks();

       // add_filter('fluentform/notifying_async_mailpoet', '__return_false');
    }

    public function pushIntegration($integrations, $formId)
    {
        $integrations[$this->integrationKey] = [
            'title' => $this->title . ' Integration',
            'logo' => $this->logo,
            'is_active' => $this->isConfigured(),
            'configure_title' => 'Configuration required!',
            'global_configure_url' => '#',
            'configure_message' => 'MailPoet is not configured yet! Please configure your MailPoet api first',
            'configure_button_text' => 'Set MailPoet'
        ];
        return $integrations;
    }

    public function getIntegrationDefaults($settings, $formId)
    {
        return [
            'name' => '',
            'first_name' => '',
            'last_name' => '',
            'email' => '',
            'other_fields' => [
                [
                    'label' => '',
                    'item_value' => ''
                ]
            ],
            'list_id' => '',
            'send_confirmation_email' => false,
            'conditionals' => [
                'conditions' => [],
                'status' => false,
                'type' => 'all'
            ],
            'enabled' => true
        ];
    }

    public function getSettingsFields($settings, $formId)
    {
        return [
            'fields' => [
                [
                    'key' => 'name',
                    'label' => 'Feed Name',
                    'required' => true,
                    'placeholder' => 'Your Feed Name',
                    'component' => 'text'
                ],
                [
                    'key' => 'list_id',
                    'label' => 'MailPoet List',
                    'placeholder' => 'Select MailPoet List',
                    'tips' => 'Select the MailPoet List you would like to add your contacts to.',
                    'component' => 'select',
                    'required' => true,
                    'options' => $this->getLists(),
                ],
                [
                    'key' => 'CustomFields',
                    'require_list' => false,
                    'label' => 'Primary Fields',
                    'tips' => 'Associate your MailPoet merge tags to the appropriate Fluent Form fields by selecting the appropriate form field from the list.',
                    'component' => 'map_fields',
                    'field_label_remote' => 'MailPoet Field',
                    'field_label_local' => 'Form Field',
                    'primary_fileds' => [
                        [
                            'key' => 'email',
                            'label' => 'Email Address',
                            'required' => true,
                            'input_options' => 'emails'
                        ],
                        [
                            'key' => 'first_name',
                            'label' => 'First Name'
                        ],
                        [
                            'key' => 'last_name',
                            'label' => 'Last Name'
                        ]
                    ]
                ],
                [
                    'key' => 'other_fields',
                    'require_list' => false,
                    'label' => 'Custom Fields',
                    'tips' => 'Select which Fluent Form fields pair with their<br /> respective MailPoet fields.',
                    'component' => 'dropdown_many_fields',
                    'field_label_remote' => 'MailPoet Field',
                    'field_label_local' => 'Form Field',
                    'options' => $this->getCustomFields()
                ],
                [
                    'key' => 'send_confirmation_email',
                    'require_list' => false,
                    'checkbox_label' => 'Send Confirmation Email',
                    'component' => 'checkbox-single'
                ],
                [
                    'require_list' => false,
                    'key' => 'conditionals',
                    'label' => 'Conditional Logics',
                    'tips' => 'Allow MailPoet integration conditionally based on your submission values',
                    'component' => 'conditional_block'
                ],
                [
                    'require_list' => false,
                    'key' => 'enabled',
                    'label' => 'Status',
                    'component' => 'checkbox-single',
                    'checkbox_label' => 'Enable This feed'
                ]
            ],
            'button_require_list' => false,
            'integration_title' => $this->title
        ];
    }

    public function getMergeFields($list, $listId, $formId)
    {
        return [];
    }

    protected function getCustomFields()
    {
        $customFields = CustomField::selectMany(['id', 'name', 'type', 'params'])->findMany();

        $fields = [];
        foreach ($customFields as $customField) {
            $fields['cf_' . $customField->id] = $customField->name;
        }
        return $fields;
    }


    protected function getLists()
    {
        $api = $this->getApi();
        $lists = $api->getLists();
        $formattedLists = [];
        foreach ($lists as $list) {
            $formattedLists[$list['id']] = $list['name'];
        }

        return $formattedLists;
    }


    /*
     * Form Submission Hooks Here
     */
    public function notify($feed, $formData, $entry, $form)
    {
        $data = $feed['processedValues'];
        $contact = Arr::only($data, ['first_name', 'last_name', 'email']);

        if (!is_email($contact['email'])) {
            $contact['email'] = Arr::get($formData, $contact['email']);
        }

        foreach (Arr::get($data, 'other_fields') as $field) {
            if ($field['item_value']) {
                $contact[$field['label']] = $field['item_value'];
            }
        }

        if (!is_email($contact['email'])) {
            do_action_deprecated(
                'ff_integration_action_result',
                [
                    $feed,
                    'info',
                    'MailPoet API called skipped because no valid email available'
                ],
                FLUENTFORM_FRAMEWORK_UPGRADE,
                'fluentform/integration_action_result',
                'Use fluentform/integration_action_result instead of ff_integration_action_result.'
            );
            do_action('fluentform/integration_action_result', $feed, 'info', 'MailPoet API called skipped because no valid email available');
            return;
        }


        $api = $this->getApi();

        try {
            $subscriber = $api->getSubscriber($contact['email']);
            if ($subscriber) {
                do_action_deprecated(
                    'ff_integration_action_result',
                    [
                        $feed,
                        'info',
                        'Contact creation has been skipped because contact already exist at MailPoet'
                    ],
                    FLUENTFORM_FRAMEWORK_UPGRADE,
                    'fluentform/integration_action_result',
                    'Use fluentform/integration_action_result instead of ff_integration_action_result.'
                );
                do_action('fluentform/integration_action_result', $feed, 'info', 'Contact creation has been skipped because contact already exist at MailPoet');
                return;
            }
        } catch (\Exception $exception) {

        }

        try {
            $options = [
                'skip_subscriber_notification' => true,
                'send_confirmation_email' => Arr::isTrue($data, 'send_confirmation_email')
            ];

            $subscriber = $api->addSubscriber($contact, [
                Arr::get($data, 'list_id')
            ], $options);

            do_action_deprecated(
                'ff_integration_action_result',
                [
                    $feed,
                    'success',
                    'Contact has been created in MailPoet. Contact ID: ' . $subscriber['id']
                ],
                FLUENTFORM_FRAMEWORK_UPGRADE,
                'fluentform/integration_action_result',
                'Use fluentform/integration_action_result instead of ff_integration_action_result.'
            );

            do_action('fluentform/integration_action_result', $feed, 'success', 'Contact has been created in MailPoet. Contact ID: ' . $subscriber['id']);
        } catch (\Exception $exception) {
            do_action_deprecated(
                'ff_integration_action_result',
                [
                    $feed,
                    'failed',
                    $exception->getMessage()
                ],
                FLUENTFORM_FRAMEWORK_UPGRADE,
                'fluentform/integration_action_result',
                'Use fluentform/integration_action_result instead of ff_integration_action_result.'
            );

            do_action('fluentform/integration_action_result', $feed, 'failed', $exception->getMessage());
        }
    }

    protected function getApi()
    {
        return \MailPoet\API\API::MP('v1');
    }


    public function isConfigured()
    {
        return true;
    }

    public function isEnabled()
    {
        return true;
    }

    protected function addLog($title, $status, $description, $formId, $entryId)
    {
        $logData = [
            'title' => $title,
            'status' => $status,
            'description' => $description,
            'parent_source_id' => $formId,
            'source_id' => $entryId,
            'component' => $this->integrationKey,
            'source_type' => 'submission_item'
        ];
        do_action_deprecated(
            'ff_log_data',
            [
                $logData
            ],
            FLUENTFORM_FRAMEWORK_UPGRADE,
            'fluentform/log_data',
            'Use fluentform/log_data instead of ff_log_data.'
        );

        do_action('fluentform/log_data', $logData);
    }
}

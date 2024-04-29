<?php

namespace FluentFormMailPoet\Integrations;

use FluentForm\App\Http\Controllers\IntegrationManagerController;
use FluentForm\Framework\Foundation\Application;
use FluentForm\Framework\Helpers\ArrayHelper as Arr;

class Bootstrap extends IntegrationManagerController
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

        $this->description = __('Connect MailPoet with WP Fluent Forms and subscribe a contact when a form is submitted.', 'ffmailpoet');

        $this->registerAdminHooks();

       // add_filter('fluentform/notifying_async_mailpoet', '__return_false');
    }

    public function pushIntegration($integrations, $formId)
    {
        $integrations[$this->integrationKey] = [
            'title' => $this->title . ' Integration',
            'logo' => $this->logo,
            'is_active' => $this->isConfigured(),
            'configure_title' => __('Configuration required!', 'ffmailpoet'),
            'global_configure_url' => '#',
            'configure_message' => __('MailPoet is not configured yet! Please configure your MailPoet first', 'ffmailpoet'),
            'configure_button_text' => __('Set MailPoet', 'ffmailpoet')
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
                    'label' => __('Feed Name', 'ffmailpoet'),
                    'required' => true,
                    'placeholder' => __('Your Feed Name', 'ffmailpoet'),
                    'component' => 'text'
                ],
                [
                    'key' => 'list_id',
                    'label' => __('MailPoet List', 'ffmailpoet'),
                    'placeholder' => __('Select MailPoet List', 'ffmailpoet'),
                    'tips' => __('Select the MailPoet List you would like to add your contacts to.', 'ffmailpoet'),
                    'component' => 'select',
                    'required' => true,
                    'options' => $this->getLists(),
                ],
                [
                    'key' => 'CustomFields',
                    'require_list' => false,
                    'label' => __('Primary Fields', 'ffmailpoet'),
                    'tips' => __('Associate your MailPoet merge tags to the appropriate Fluent Form fields by selecting the appropriate form field from the list.', 'ffmailpoet'),
                    'component' => 'map_fields',
                    'field_label_remote' => __('MailPoet Field', 'ffmailpoet'),
                    'field_label_local' => __('Form Field', 'ffmailpoet'),
                    'primary_fileds' => [
                        [
                            'key' => 'email',
                            'label' => __('Email Address', 'ffmailpoet'),
                            'required' => true,
                            'input_options' => 'emails'
                        ],
                        [
                            'key' => 'first_name',
                            'label' => __('First Name', 'ffmailpoet')
                        ],
                        [
                            'key' => 'last_name',
                            'label' => __('Last Name', 'ffmailpoet')
                        ]
                    ]
                ],
                [
                    'key' => 'other_fields',
                    'require_list' => false,
                    'label' => __('Custom Fields', 'ffmailpoet'),
                    'tips' => __('Select which Fluent Form fields pair with their respective MailPoet fields. Checkbox fields must contain true/false value like Terms&Condition/GDPR field. Date fields supports values for year_month_day: MM/DD/YYYY, DD/MM/YYYY, YYYY/MM/DD, for year_month: YYYY/M, MM/YY, for year: YYYY, for month: MM types', 'ffmailpoet'),
                    'component' => 'dropdown_many_fields',
                    'field_label_remote' => __('MailPoet Field', 'ffmailpoet'),
                    'field_label_local' => __('Form Field', 'ffmailpoet'),
                    'options' => $this->getCustomFields()
                ],
                [
                    'key' => 'send_confirmation_email',
                    'require_list' => false,
                    'tips' => __('User needed to log out to send confirmation email feature to work','ffmailpoet'),
                    'checkbox_label' => __('Send Confirmation Email', 'ffmailpoet'),
                    'component' => 'checkbox-single'
                ],
                [
                    'require_list' => false,
                    'key' => 'conditionals',
                    'label' => __('Conditional Logics', 'ffmailpoet'),
                    'tips' => __('Allow MailPoet integration conditionally based on your submission values', 'ffmailpoet'),
                    'component' => 'conditional_block'
                ],
                [
                    'require_list' => false,
                    'key' => 'enabled',
                    'label' => __('Status', 'ffmailpoet'),
                    'component' => 'checkbox-single',
                    'checkbox_label' => __('Enable This feed', 'ffmailpoet')
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
        $api = $this->getApi();
        $customFields = $api->getSubscriberFields();

        $fields = [];
        foreach ($customFields as $customField) {
            $id = Arr::get($customField, 'id');
            if (strpos($id, 'cf') !== false) {
                $name = Arr::get($customField, 'name');
                $type = Arr::get($customField, 'type');
                if ($type) {
                    $type = '_**_' . $type;
                }
                if ($id && $name && $type) {
                    $fields[$customField['id'] . $type] = $customField['name'];
                }
            }
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
                if (strpos($field['label'], '_**_') !== false) {
                    $fieldDetails = explode('_**_', $field['label']);
                    $fieldId = $fieldDetails[0];
                    $fieldType = $fieldDetails[1];
                    $value = $field['item_value'];
                    if ($fieldType == 'checkbox') {
                        if ($value == 'yes' || $value == 'on' || $value == 'true' || $value == '1' || $value == 'Accepted') {
                            $value = true;
                        }
                    }
                    $contact[$fieldId] = $value;
                }
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
                $message = __('Contact creation has been skipped because contact already exist at MailPoet.', 'ffmailpoet');
                do_action_deprecated(
                    'ff_integration_action_result',
                    [
                        $feed,
                        'info',
                        $message
                    ],
                    FLUENTFORM_FRAMEWORK_UPGRADE,
                    'fluentform/integration_action_result',
                    'Use fluentform/integration_action_result instead of ff_integration_action_result.'
                );
                do_action('fluentform/integration_action_result', $feed, 'info', $message);
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

            $message = __('Contact has been created in MailPoet. Contact ID: ', 'ffmailpoet');

            do_action_deprecated(
                'ff_integration_action_result',
                [
                    $feed,
                    'success',
                    $message . $subscriber['id']
                ],
                FLUENTFORM_FRAMEWORK_UPGRADE,
                'fluentform/integration_action_result',
                'Use fluentform/integration_action_result instead of ff_integration_action_result.'
            );

            do_action('fluentform/integration_action_result', $feed, 'success', $message . $subscriber['id']);
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
}

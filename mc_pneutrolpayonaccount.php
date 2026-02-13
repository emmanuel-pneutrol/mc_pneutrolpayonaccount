<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Mc_Pneutrolpayonaccount extends PaymentModule
{
    private $_html = '';
    private $_postErrors = [];

    public $accountName;
    public $poNumber;
    public $extra_mail_vars;

    public function __construct()
    {
        $this->name = 'mc_pneutrolpayonaccount';
        $this->tab = 'payments_gateways';
        $this->version = '2.1.0';
        $this->author = 'PrestaShop | Extended by EI';
        $this->controllers = ['payment', 'validation'];

        $this->currencies = true;
        $this->currencies_mode = 'checkbox';

        $config = Configuration::getMultiple(['ACCOUNT_NAME', 'PO_NUMBER']);
        if (isset($config['ACCOUNT_NAME'])) {
            $this->accountName = $config['ACCOUNT_NAME'];
        }
        if (isset($config['PO_NUMBER'])) {
            $this->poNumber = $config['PO_NUMBER'];
        }

        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->trans('Payments on Account', [], 'Modules.Mc_pneutrolpayonaccount.Admin');
        $this->description = $this->trans('Display contact details blocks to make it easy for customers to pay by account on your store.', [], 'Modules.Mc_pneutrolpayonaccount.Admin');
        $this->confirmUninstall = $this->trans('Are you sure you want to delete these details?', [], 'Modules.Mc_pneutrolpayonaccount.Admin');
        $this->ps_versions_compliancy = ['min' => '1.7.6.0', 'max' => _PS_VERSION_];

        if ((!isset($this->accountName) || !isset($this->poNumber) || empty($this->accountName) || empty($this->poNumber)) && $this->active) {
            $this->warning = $this->trans('The "Account" and "PO Number" fields must be configured before using this module.', [], 'Modules.Mc_pneutrolpayonaccount.Admin');
        }
        if (!count(Currency::checkPaymentCurrencies($this->id)) && $this->active) {
            $this->warning = $this->trans('No currency has been set for this module.', [], 'Modules.Mc_pneutrolpayonaccount.Admin');
        }

        $this->extra_mail_vars = [
            '{account_name}' => Configuration::get('ACCOUNT_NAME'),
            '{po_number}' => Configuration::get('PO_NUMBER'),
            '{po_number_html}' => Tools::nl2br(Configuration::get('PO_NUMBER')),
        ];
    }

    public function install()
    {
        return parent::install()
            && $this->registerHook('paymentOptions')
            && $this->registerHook('displayPaymentReturn')
        ;
    }

    public function uninstall()
    {
        return Configuration::deleteByName('ACCOUNT_NAME')
            && Configuration::deleteByName('PO_NUMBER')
            && parent::uninstall()
        ;
    }

    private function _postValidation()
    {
        if (Tools::isSubmit('btnSubmit')) {
            if (!Tools::getValue('ACCOUNT_NAME')) {
                $this->_postErrors[] = $this->trans('The "Account Name" field is required.', [], 'Modules.Mc_pneutrolpayonaccount.Admin');
            } elseif (!Tools::getValue('PO_NUMBER')) {
                $this->_postErrors[] = $this->trans('The "PO Number Field" field is required.', [], 'Modules.Mc_pneutrolpayonaccount.Admin');
            }
        }
    }

    private function _postProcess()
    {
        if (Tools::isSubmit('btnSubmit')) {
            Configuration::updateValue('ACCOUNT_NAME', Tools::getValue('ACCOUNT_NAME'));
            Configuration::updateValue('PO_NUMBER', Tools::getValue('PO_NUMBER'));
        }
        $this->_html .= $this->displayConfirmation($this->trans('Settings updated', [], 'Admin.Notifications.Success'));
    }

    private function _displayCheck()
    {
        return $this->display(__FILE__, './views/templates/hook/infos.tpl');
    }

    public function getContent()
    {
        $this->_html = '';

        if (Tools::isSubmit('btnSubmit')) {
            $this->_postValidation();
            if (!count($this->_postErrors)) {
                $this->_postProcess();
            } else {
                foreach ($this->_postErrors as $err) {
                    $this->_html .= $this->displayError($err);
                }
            }
        }

        $this->_html .= $this->_displayCheck();
        $this->_html .= $this->renderForm();

        return $this->_html;
    }

    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }
        if (!$this->checkCurrency($params['cart'])) {
            return;
        }

        // Allowed groups (IDs)
        $allowedGroupIds = [4]; // e.g. "VIP", "B2B"

        $customerId = (int) $this->context->customer->id;

        // Not logged in? Hide it (optional rule)
        if ($customerId <= 0) {
            return;
        }

        // Customer group IDs (includes default + assigned groups)
        $customerGroupIds = Customer::getGroupsStatic($customerId);

        // If customer is in none of the allowed groups, hide payment option
        if (!array_intersect($allowedGroupIds, $customerGroupIds)) {
            return;
        }

        $this->smarty->assign(
            $this->getTemplateVars()
        );

        $newOption = new PaymentOption();
        $newOption->setModuleName($this->name)
            ->setCallToActionText($this->trans('Pay on Account', [], 'Modules.Mc_pneutrolpayonaccount.Admin'))
            ->setAction($this->context->link->getModuleLink($this->name, 'validation', [], true))
            ->setInputs([
                [
                    'type' => 'hidden',
                    'name' => 'poNumber',
                    'value' => ''
                ],
            ])
            ->setAdditionalInformation($this->fetch('module:mc_pneutrolpayonaccount/views/templates/front/payment_infos.tpl'));

        return [$newOption];
    }

    public function hookDisplayPaymentReturn($params)
    {
        if (!$this->active) {
            return;
        }

        $order = $params['order'] ?? null;
        if (!$order || !($order instanceof Order)) {
            return '';
        }

        // Only for orders paid with this module
        if (($order->module ?? '') !== $this->name) {
            return '';
        }

        $poNumber = '';

        // Read the last customer message for this order and extract "PO Number: ..."
        $messages = Message::getMessagesByOrderId((int) $order->id, true);
        if (is_array($messages)) {
            foreach ($messages as $message) {
                $content = (string) ($message['message'] ?? '');
                if (stripos($content, 'PO Number:') !== false) {
                    $poNumber = trim((string) preg_replace('/^.*PO Number:\s*/i', '', $content));
                }
            }
        }

        $customerId = $params['order']->id_customer;
        $row = $this->getCustomerCompanyRow($customerId);

        $rest_to_paid = $params['order']->getOrdersTotalPaid() - $params['order']->getTotalPaid();

        $this->smarty->assign([
            'total_to_pay' => $this->context->getCurrentLocale()->formatPrice(
                $rest_to_paid,
                (new Currency($params['order']->id_currency))->iso_code
            ),
            'shop_name' => $this->context->shop->name,
            'accountName' => $row['company_name'],
            'poNumber' =>  $poNumber !== '' ? $poNumber : '___________', //Tools::nl2br($this->poNumber)
            'status' => 'ok',
            'id_order' => $params['order']->id,
            'reference' => $params['order']->reference,
        ]);

        return $this->fetch('module:mc_pneutrolpayonaccount/views/templates/hook/payment_return.tpl');
    }

    public function checkCurrency($cart)
    {
        $currency_order = new Currency((int) ($cart->id_currency));
        $currencies_module = $this->getCurrency((int) $cart->id_currency);

        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }

        return false;
    }

    public function renderForm()
    {
        $fields_form = [
            'form' => [
                'legend' => [
                    'title' => $this->trans('Contact details', [], 'Modules.Mc_pneutrolpayonaccount.Admin'),
                    'icon' => 'icon-envelope',
                ],
                'input' => [
                    [
                        'type' => 'text',
                        'label' => $this->trans('Account Name (name)', [], 'Modules.Mc_pneutrolpayonaccount.Admin'),
                        'name' => 'ACCOUNT_NAME',
                        'required' => true,
                    ],
                    [
                        'type' => 'textarea',
                        'label' => $this->trans('PO Number', [], 'Modules.Mc_pneutrolpayonaccount.Admin'),
                        'desc' => $this->trans('PO Number where the check should be sent to.', [], 'Modules.Mc_pneutrolpayonaccount.Admin'),
                        'name' => 'PO_NUMBER',
                        'required' => true,
                    ],
                ],
                'submit' => [
                    'title' => $this->trans('Save', [], 'Admin.Actions'),
                ],
            ],
        ];

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->id = (int) Tools::getValue('id_carrier');
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'btnSubmit';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false) . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = [
            'fields_value' => $this->getConfigFieldsValues(),
        ];

        return $helper->generateForm([$fields_form]);
    }

    public function getConfigFieldsValues()
    {
        return [
            'ACCOUNT_NAME' => Tools::getValue('ACCOUNT_NAME', Configuration::get('ACCOUNT_NAME')),
            'PO_NUMBER' => Tools::getValue('PO_NUMBER', Configuration::get('PO_NUMBER')),
        ];
    }

    public function getTemplateVars()
    {
        $cart = $this->context->cart;
        $total = $this->context->getCurrentLocale()->formatPrice(
            $cart->getOrderTotal(true, Cart::BOTH),
            (new Currency($cart->id_currency))->iso_code
        );

        $taxLabel = '';
        if ($this->context->country->display_tax_label) {
            $taxLabel = $this->trans('(tax incl.)', [], 'Modules.Mc_pneutrolpayonaccount.Admin');
        }

        // Get current customer id from context
        $customerId = $cart->id_customer;

        // Get company name from the accounts
        $row = $this->getCustomerCompanyRow($customerId);

        $accountName = $row['company_name'];
        if (!$accountName) {
            $accountName = '___________';
        }

        $poNumber = trim((string) Tools::getValue('poNumber', ''));//Tools::nl2br(Configuration::get('CHEQUE_ADDRESS'));
        if (!$poNumber) {
            $poNumber = '___________';
        }

        return [
            'checkTotal' => $total,
            'checkTaxLabel' => $taxLabel,
            'accountName' => $accountName,
            'poNumber' => $poNumber,
        ];
    }

    private function getCustomerCompanyRow(int $idCustomer): ?array
    {
        $idShop = (int) ($this->context->shop->id ?? 0);
        if ($idShop <= 0) {
            $idShop = (int) Configuration::get('PS_SHOP_DEFAULT');
        }

        $sql = 'SELECT is_company, company_name, company_vat
                FROM `' . _DB_PREFIX_ . 'pneutrolaccountholder_customer`
                WHERE id_customer = ' . (int) $idCustomer . '
                AND id_shop = ' . (int) $idShop;

        $row = Db::getInstance()->getRow($sql);

        return is_array($row) ? $row : null;
    }
}

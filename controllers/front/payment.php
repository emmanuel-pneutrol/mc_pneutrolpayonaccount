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

/**
 * @since 1.5.0
 */
class Mc_PneutrolpayonaccountPaymentModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    /**
     * @see FrontController::initContent()
     */
    public function initContent()
    {
        parent::initContent();

        if (!($this->module instanceof Mc_Pneutrolpayonaccount)) {
            Tools::redirect('index.php?controller=order');

            return;
        }
        $cart = $this->context->cart;
        if (!$this->module->checkCurrency($cart)) {
            Tools::redirect('index.php?controller=order');

            return;
        }

        $poNumber = Tools::getValue('poNumber', '___________');

        $customerId = (int) $this->context->customer->id;

        $row = $this->getCustomerCompanyRow($customerId);

        $this->context->smarty->assign([
            'nbProducts' => $cart->nbProducts(),
            'cust_currency' => $cart->id_currency,
            'currencies' => $this->module->getCurrency((int) $cart->id_currency),
            'total' => $cart->getOrderTotal(true, Cart::BOTH),
            'isoCode' => $this->context->language->iso_code,
            'accountName' => $row['company_name'] ?? '',
            'poNumber' => $poNumber,
            'this_path' => $this->module->getPathUri(),
            'this_path_check' => $this->module->getPathUri(),
            'this_path_ssl' => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $this->module->name . '/',
        ]);

        $this->setTemplate('payment_execution.tpl');
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

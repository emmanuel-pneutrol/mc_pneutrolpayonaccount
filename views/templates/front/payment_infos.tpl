{**
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
 *}

<section>
  <p>{l s='Please send us your check following these rules:' d='Modules.Mc_pneutrolpayonaccount.Shop'}
    <dl>
      <dt>{l s='Amount' d='Modules.Mc_pneutrolpayonaccount.Shop'}</dt>
      <dd>{$checkTotal} {$checkTaxLabel}</dd>
      <dt>{l s='Account Name' d='Modules.Mc_pneutrolpayonaccount.Shop'}</dt>
      <dd>{$accountName}</dd>
      <dt>{l s='PO Number (if applicable)' d='Modules.Mc_pneutrolpayonaccount.Shop'}</dt>
      <dd>
{*        {$poNumber nofilter}*}
        <input
          type="text"
          name="poNumberVis"
          id="poNumberVis"
          class="form-control"
          value="{$poNumber|escape:'htmlall':'UTF-8'}"
          autocomplete="off"
        >
        <small class="form-text text-muted">
          {l s='This will be added to the order/payment details.' d='Modules.Mc_pneutrolpayonaccount.Shop'}
        </small>
      </dd>
    </dl>
  </p>
</section>
{literal}
  <script>
    (function () {
      function findHiddenPoInput() {
        // The checkout typically renders PaymentOption inputs as <input name="poNumber" ...>
        return document.querySelector('input[type="hidden"][name="poNumber"]');
      }

      function syncPoNumber() {
        var visible = document.getElementById('poNumberVis');
        if (!visible) return;

        var hidden = findHiddenPoInput();
        if (!hidden) return;

        hidden.value = visible.value || '';
      }

      document.addEventListener('DOMContentLoaded', function () {
        var visible = document.getElementById('poNumberVis');
        if (!visible) return;

        // Sync on typing
        visible.addEventListener('input', syncPoNumber);
        visible.addEventListener('change', syncPoNumber);

        // Also sync when customer selects a payment option (themes differ)
        document.addEventListener('change', function (e) {
          var t = e.target;
          if (!t) return;
          // Common selectors for payment method radios
          if (t.matches('input[type="radio"][name="payment-option"], input[type="radio"][name="payment-option-id"]')) {
            syncPoNumber();
          }
        });

        // Initial sync (in case value is prefilled)
        syncPoNumber();
      });

      // Extra safety: before any form submit, ensure hidden has latest value
      document.addEventListener('submit', function () {
        syncPoNumber();
      }, true);
    })();
  </script>
{/literal}

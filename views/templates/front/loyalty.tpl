{*
 * 2007-2018 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2018 PrestaShop SA
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 * International Registered Trademark & Property of PrestaShop SA
*}
{extends file='page.tpl'}
{block name='page_content'}
  <div id="loyalty-main">
    <h2>{l s='My loyalty points' d='Modules.Loyalty.Front'}</h2>
    {if $orders}
    <div class="block-center" id="block-history">
      {if $orders && count($orders)}
        <table id="order-list" class="table">
          <thead>
            <tr>
              <th class="first_item">{l s='Order' d='Modules.Loyalty.Front'}</th>
              <th class="item">{l s='Date' d='Modules.Loyalty.Front'}</th>
              <th class="item">{l s='Points' d='Modules.Loyalty.Front'}</th>
              <th class="last_item">{l s='Points Status' d='Modules.Loyalty.Front'}</th>
            </tr>
          </thead>
          <tfoot>
            <tr class="alternate_item">
              <td colspan="2" class="history_method bold" style="text-align:center;">{l s='Total points available:' d='Modules.Loyalty.Front'}</td>
              <td class="history_method" style="text-align:left;">{$totalPoints|intval}</td>
              <td class="history_method">&nbsp;</td>
            </tr>
          </tfoot>
          <tbody>
            {foreach from=$displayorders item='order'}
              <tr class="alternate_item">
                <td class="history_link bold">{l s='#' d='Modules.Loyalty.Front'}{$order.id|string_format:"%06d"}</td>
                <td class="history_date">{dateFormat date=$order.date full=1}</td>
                <td class="history_method">{$order.points|intval}</td>
                <td class="history_method">{$order.state}</td>
              </tr>
            {/foreach}
          </tbody>
        </table>
        <div id="block-order-detail" class="hidden">&nbsp;</div>
      {else}
        <p class="warning">{l s='You have not placed any orders.' d='Modules.Loyalty.Front'}</p>
      {/if}
    </div>

    {if $pagination}
      {include file='_partials/pagination.tpl'}
      <script type="text/javascript">
        // Immediately remove ajax pagination hooks
        [].slice.call(document.querySelectorAll('nav.pagination a')).forEach(function (element) {
          element.className = element.className.replace('js-search-link', '');
        });
      </script>
    {/if}

    <br/>
    {l s='Vouchers generated here are usable in the following categories : ' d='Modules.Loyalty.Front'}
    {if $categories}{$categories}{else}{l s='All' d='Modules.Loyalty.Front'}{/if}

    {if $transformation_allowed}
      <p style="text-align:center; margin-top:20px">
        <a href="{$link->getModuleLink('loyalty', 'default', ['process' => 'transformpoints'])}"
           onclick="return confirm('{l s='Are you sure you want to transform your points into vouchers?' d='Modules.Loyalty.Front' js=1}');"
        >{l s='Transform my points into a voucher of' d='Modules.Loyalty.Front'}
          <span class="price">{Tools::displayPrice($voucher)}</span>.
        </a>
      </p>
    {/if}
    <div style="margin-top: 20px;">
      <h2>{l s='My vouchers from loyalty points' d='Modules.Loyalty.Front'}</h2>
      {if $nbDiscounts}
        <div id="block-history">
          <table id="order-list" class="table">
            <thead>
              <tr>
                <th class="first_item">{l s='Created' d='Modules.Loyalty.Front'}</th>
                <th class="item">{l s='Value' d='Modules.Loyalty.Front'}</th>
                <th class="item">{l s='Code' d='Modules.Loyalty.Front'}</th>
                <th class="item">{l s='Valid from' d='Modules.Loyalty.Front'}</th>
                <th class="item">{l s='Valid until' d='Modules.Loyalty.Front'}</th>
                <th class="item">{l s='Status' d='Modules.Loyalty.Front'}</th>
                <th class="last_item">{l s='Details' d='Modules.Loyalty.Front'}</th>
              </tr>
            </thead>
            <tbody>
              {foreach from=$discounts item=discount name=myLoop}
                <tr class="alternate_item">
                  <td class="history_date">{dateFormat date=$discount->date_add}</td>
                  <td class="history_price"><span class="price">{if $discount->reduction_percent > 0}
              {$discount->reduction_percent}%
            {elseif $discount->reduction_amount}
              {Tools::displayPrice($discount->reduction_amount, $discount->reduction_currency)}
            {else}
              {l s='Free shipping' d='Modules.Loyalty.Front'}
            {/if}</span></td>
                  <td class="history_method bold">{$discount->code}</td>
                  <td class="history_date">{dateFormat date=$discount->date_from}</td>
                  <td class="history_date">{dateFormat date=$discount->date_to}</td>
                  <td class="history_method bold">{if $discount->quantity > 0}{l s='Ready to use' d='Modules.Loyalty.Front'}{else}{l s='Already used' d='Modules.Loyalty.Front'}{/if}</td>
                  <td class="history_method">
                    <a href="{$smarty.server.SCRIPT_NAME}"
                       onclick="return false"
                       class="tips"
                       title="{l s='Generated by these following orders' d='Modules.Loyalty.Front'}|{foreach from=$discount->orders item=myorder name=myLoop}
                    {$myorder.id_order|string_format:{l s='Order #%d' d='Modules.Loyalty.Front'}}
                    ({Tools::displayPrice($myorder.total_paid, $myorder.id_currency)}) :
                    {if $myorder.points > 0}{$myorder.points|string_format:{l s='%d points.' d='Modules.Loyalty.Front'}}{else}{l s='Cancelled' d='Modules.Loyalty.Front'}{/if}
                    {if !$smarty.foreach.myLoop.last}|{/if}{/foreach}"
                       rel="nofollow"
                    >
                      {l s='more...' d='Modules.Loyalty.Front'}
                    </a>
                  </td>
                </tr>
              {/foreach}
            </tbody>
          </table>
          <div id="block-order-detail" class="hidden">&nbsp;</div>
        </div>
      {if $minimalLoyalty > 0}<p>{l s='The minimum order amount in order to use these vouchers is:' d='Modules.Loyalty.Front'}
        {Tools::displayPrice($minimalLoyalty)}
        </p>{/if}
        <script type="text/javascript">
          {literal}
          $(document).ready(function () {
            $('a.tips').cluetip({
              showTitle: false,
              splitTitle: '|',
              arrows: false,
              fx: {
                open: 'fadeIn',
                openSpeed: 'fast'
              }
            });
          });
          {/literal}
        </script>
      {else}
        <p class="warning">{l s='No vouchers yet.' d='Modules.Loyalty.Front'}</p>
      {/if}
      {else}
      <p class="warning">{l s='No reward points yet.' d='Modules.Loyalty.Front'}</p>
      {/if}
    </div>
  </div>

  <style>
    #loyalty-main .page-list {
      box-shadow: none;
      border-top: 2px solid #f6f6f6;
      border-bottom: 2px solid #f6f6f6;
    }
  </style>
{/block}

{block name="page_footer"}
  <footer class="page-footer">
    <a href="{$urls.pages.my_account}" class="account-link">
      <i class="material-icons">keyboard_arrow_left</i>
      <span>{l s='Back to your account' d='Shop.Theme.Customeraccount'}</span>
    </a>
    <a href="{$urls.pages.index}" class="account-link">
      <i class="material-icons">home</i>
      <span>{l s='Home' d='Shop.Theme.Global'}</span>
    </a>
  </footer>
{/block}

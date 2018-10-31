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
<p id="loyalty" style="margin-top: 20px">
  <i class="material-icons">card_membership</i>
  {if $points > 0}
    {l s='By checking out this shopping cart you can collect up to' d='Modules.Loyalty.Front'}
    <strong>{if $points > 1}{l s='%d loyalty points' sprintf=[$points] d='Modules.Loyalty.Front'}{else}{l s='%d loyalty point' sprintf=[$points] d='Modules.Loyalty.Front'}{/if}</strong>
    {l s='that can be converted into a voucher of' d='Modules.Loyalty.Front'} {Tools::displayPrice($voucher)}{if isset($guest_checkout) && $guest_checkout}<sup>*</sup>{/if}.
    <br/>
    {if isset($guest_checkout) && $guest_checkout}<sup>*</sup>{l s='Not available for Instant checkout order' d='Modules.Loyalty.Front'}{/if}
  {else}
    {l s='Add some products to your shopping cart to collect some loyalty points.' d='Modules.Loyalty.Front'}
  {/if}
</p>

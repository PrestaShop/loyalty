{*
* 2007-2014 PrestaShop
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
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2014 PrestaShop SA
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*}

{extends file="helpers/form/form.tpl"}
	
{block name="field"}
	{if $input.type == 'desc'}
		<div class="alert alert-info">{$input.text}</div>
	{/if}
	{$smarty.block.parent}
{/block}

{block name="label"}
	{if $input.type == 'loyalty_blocks'}
		
	{else}
		{$smarty.block.parent}
	{/if}
{/block}

{block name="legend"}
	<h3>
		{if isset($field.image)}<img src="{$field.image}" alt="{$field.title|escape:'html':'UTF-8'}" />{/if}
		{if isset($field.icon)}<i class="{$field.icon}"></i>{/if}
		{$field.title}
		<span class="panel-heading-action">
			{foreach from=$toolbar_btn item=btn key=k}
				{if $k != 'modules-list' && $k != 'back'}
					<a id="desc-{$table}-{if isset($btn.imgclass)}{$btn.imgclass}{else}{$k}{/if}" class="list-toolbar-btn" {if isset($btn.href)}href="{$btn.href}"{/if} {if isset($btn.target) && $btn.target}target="_blank"{/if}{if isset($btn.js) && $btn.js}onclick="{$btn.js}"{/if}>
						<span title="" data-toggle="tooltip" class="label-tooltip" data-original-title="{l s=$btn.desc}" data-html="true">
							<i class="process-icon-{if isset($btn.imgclass)}{$btn.imgclass}{else}{$k}{/if} {if isset($btn.class)}{$btn.class}{/if}" ></i>
						</span>
					</a>
				{/if}
			{/foreach}
			</span>
	</h3>
{/block}

{block name="input"}

    {if $input.type == 'switch' && $smarty.const._PS_VERSION_|@addcslashes:'\'' < '1.6'}
		{foreach $input.values as $value}
			<input type="radio" name="{$input.name}" id="{$value.id}" value="{$value.value|escape:'html':'UTF-8'}"
					{if $fields_value[$input.name] == $value.value}checked="checked"{/if}
					{if isset($input.disabled) && $input.disabled}disabled="disabled"{/if} />
			<label class="t" for="{$value.id}">
			 {if isset($input.is_bool) && $input.is_bool == true}
				{if $value.value == 1}
					<img src="../img/admin/enabled.gif" alt="{$value.label}" title="{$value.label}" />
				{else}
					<img src="../img/admin/disabled.gif" alt="{$value.label}" title="{$value.label}" />
				{/if}
			 {else}
				{$value.label}
			 {/if}
			</label>
			{if isset($input.br) && $input.br}<br />{/if}
			{if isset($value.p) && $value.p}<p>{$value.p}</p>{/if}
		{/foreach}
	{else}
		{$smarty.block.parent}
    {/if}

{/block}

{block name="input_row"}

	{if $input.type == 'loyalty_blocks'}
		<div class="row">
			{assign var=loyalty_blocks value=$input.values}
			{if isset($loyalty_blocks) && count($loyalty_blocks) > 0}
				<div class="col-lg-10">
					<div class="panel">
						<table class="table tableDnD" id="loyalty_block_{$key%2}">
							<thead>
								<tr class="nodrag nodrop">
									<th>{l s='Order' mod='loyalty'}</th>
									<th>{l s='Date' mod='loyalty'}</th>
									<th>{l s='Total (without shipping)' mod='loyalty'}</th>
									<th>{l s='Points' mod='loyalty'}</th>
									<th>{l s='Points Status' mod='loyalty'}</th>
									<th></th>
								</tr>
							</thead>
							<tbody>
								{foreach $loyalty_blocks as $key => $loyalty_block}
									<tr class="{if $key%2}alt_row{else}not_alt_row{/if} row_hover" id="tr_{$key%2}_{$loyalty_block['id_loyalty']}}">
										<td>{if $loyalty_block['id'] >0} {$loyalty_block['id']} {else} {$loyalty_block['ExternalRef']} {/if}</td>
										<td>{$loyalty_block['date']}</td>
										<td>{if $loyalty_block['id'] > 0}{$loyalty_block['total_without_shipping']}{else}{$loyalty_block['loyalty_text']}{/if}</td>
										<td>{$loyalty_block['points']}</td>
										<td>{$loyalty_block['state']}</td>
										<td>
											<div class="btn-group-action">
												<div class="btn-group pull-right">
													{if $loyalty_block['id'] == 0}
														{if $loyalty_block['id_loyalty_state'] <> '4'}
															<a class="btn btn-default" href="index.php?controller=AdminLoyalty&amp;token={$tokenLoyalty}&amp;updateloyalty&amp;id_customer={(int)$loyalty_block['id_customer']}&amp;id_loyalty={(int)$loyalty_block['id_loyalty']}" title="{l s='Edit' mod='loyalty'}">
																<i class="icon-edit"></i> {l s='Edit' mod='loyalty'}
															</a>
															<button class="btn btn-default dropdown-toggle" data-toggle="dropdown">
																<i class="icon-caret-down"></i>&nbsp;
															</button>
															<ul class="dropdown-menu">
																<li>
																	<a href="index.php?controller=AdminLoyalty&amp;token={$tokenLoyalty}&amp;deleteloyalty&amp;id_customer={(int)$loyalty_block['id_customer']}&amp;id_loyalty={(int)$loyalty_block['id_loyalty']}" title="{l s='Delete' mod='loyalty'}">
																		<i class="icon-trash"></i> {l s='Delete' mod='loyalty'}
																	</a>
																</li>
															</ul>
														{/if}
													{else}
														<a class="btn btn-default" href="{$current}&amp;id_order={(int)$loyalty_block['id']}&amp;vieworder&amp;token={$token}" title="{l s='View' mod='loyalty'}">
															<i class="icon-search"></i> {l s='View' mod='loyalty'}
														</a>
													{/if}
												</div>
											</div>
										</td>
									</tr>
								{/foreach}
							</tbody>
						</table>
					</div>
				</div>
			{/if}
		</div>
	{else}
		{$smarty.block.parent}
	{/if}

{/block}
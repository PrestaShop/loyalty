<?php
/*
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
*/

/**
 * @since 1.6.0
 */

class AdminLoyaltyController extends ModuleAdminController
{
	public $bootstrap = true;
	
	//Token AdminCustomres
	public $customerToken = '';
	
	// Customer ID
	public $customerID = 0;
	
	public function __construct()
	{
		$this->className = 'LoyaltyModule';
		$this->table = 'loyalty';
		
		$this->addRowAction('edit');
		$this->addRowAction('delete');
		
		$this->bulk_actions = array(
			'delete' => array(
				'text' => $this->l('Delete selected'),
				'icon' => 'icon-trash',
				'confirm' => $this->l('Delete selected items?')
			)
		);

		$this->context = Context::getContext();
		
		$this->fields_list = array(
			'id_loyalty' => array('title' => $this->l('ID'), 'align' => 'center', 'class' => 'fixed-width-xs'),
			'id_external_ref' => array('title' => $this->l('Reference')),
			'points' => array('title' => $this->l('Points'), 'orderby' => false),
		);
		
		$this->customerToken = Tools::getAdminTokenLite('AdminCustomers');
		$this->customerID    = (int)Tools::getValue('id_customer');
		
		parent::__construct();

	}

	public function renderForm()
	{
		// loads current warehouse
		if (!($obj = $this->loadObject(true)))
			return;
			
		$this->fields_form = array(
			'legend' => array(
				'title' => $this->l('Loyalty'),
				'icon' => 'icon-gift'
			),			
			'input' => array(
				array(
					'type' => 'hidden',
					'name' => 'id_customer',
				),
				array(
					'type' => 'text',
					'label' => $this->l('Reference'),
					'name' => 'id_external_ref',
					'required' => true,
					'col' => 4,
					'hint' => $this->l('Invalid characters:').' &lt;&gt;;=#{}',
				),
				array(
					'type' => 'text',
					'label' => $this->l('Points'),
					'name' => 'points',
					'required' => true,
					'col' => 4,
					'hint' => $this->l('Invalid characters:').' &lt;&gt;;=#{}',
				),
				array(
					'type' => 'text',
					'label' => $this->l('Info Text'),
					'name' => 'loyalty_text',
					'col' => 4,
					'lang' => true,
					'hint' => $this->l('Invalid characters:').' &lt;&gt;;=#{}',
				),
				array(
					'type' => 'select',
					'label' => $this->l('State'),
					'name' => 'id_loyalty_state',
					'col' => 4,
					'options' => array(
						'query' => LoyaltyModule::getLoyaltyStates($this->context->language->id),
						'id' => 'id_loyalty_state',
						'name' => 'name'
					)
				)
			),
			'submit' => array(
				'title' => $this->l('Save'),
			)
		);
			
		$this->fields_value['id_customer'] = $this->customerID;

		$languages = Language::getLanguages(false);
		foreach ($languages as $lang)
		{
			$xtmp = LoyaltyModule::getLoyaltyText(Tools::getValue('id_loyalty'), $lang['id_lang']);
			if(!empty($xtmp)) {
				$this->fields_value['loyalty_text'][$lang['id_lang']] = $xtmp[0]['name'];
			} else {
				$this->fields_value['loyalty_text'][$lang['id_lang']] = '';
			}
		}
		
		return parent::renderForm();
	}
	
	public function postProcess()
	{
		if (!($obj = $this->loadObject(true)))
			return;
				
		if (Tools::isSubmit('submitAdd'.$this->table))
		{
			// Extra Code
			return parent::postProcess();
		}
		else if (Tools::isSubmit('delete'.$this->table))
		{
			// Delete History
			Db::getInstance()->execute('DELETE FROM `'._DB_PREFIX_.'loyalty_history` WHERE `id_loyalty`='.(int)Tools::getValue('id_loyalty'));
			Db::getInstance()->execute('DELETE FROM `'._DB_PREFIX_.'loyalty` WHERE `id_loyalty`='.(int)Tools::getValue('id_loyalty'));
			Db::getInstance()->execute('DELETE FROM `'._DB_PREFIX_.'loyalty_text_lang` WHERE `id_loyalty`='.(int)Tools::getValue('id_loyalty'));
			
			Tools::redirectAdmin('index.php?controller=AdminCustomers&id_customer='.$this->customerID.'&viewcustomer&token='.$this->customerToken);
			
		} else {
			return parent::postProcess();
		}
	}

	/**
	 * @see AdminController::afterAdd()
	*/
	public function afterAdd($object)
	{
		$id_loyalty = (int)$_POST['id_loyalty'];
		// Add History
		Db::getInstance()->execute('INSERT INTO `'._DB_PREFIX_.'loyalty_history` (`id_loyalty`, `id_loyalty_state`, `points`, `date_add`)
			VALUES ( '.$id_loyalty.', '.(int)Tools::getValue('id_loyalty_state').', '.(int)Tools::getValue('points').', NOW())');
		
		$languages = Language::getLanguages();
		foreach ($languages as $language)
		{
			Db::getInstance()->execute('INSERT INTO `'._DB_PREFIX_.'loyalty_text_lang` (`id_loyalty`, `name`, `id_lang`)
				VALUES ( '.$id_loyalty.', \''.Tools::getValue('loyalty_text_'.(int)($language['id_lang'])).'\', '.(int)($language['id_lang']).')');			
		}
		
		Tools::redirectAdmin('index.php?controller=AdminCustomers&id_customer='.$this->customerID.'&viewcustomer&token='.$this->customerToken);			
	}
	
	/**
	 * @see AdminController::afterUpdate()
	*/
	public function afterUpdate($object)
	{
		$id_loyalty = (int)$_POST['id_loyalty'];
		// Add History
		Db::getInstance()->execute('INSERT INTO `'._DB_PREFIX_.'loyalty_history` (`id_loyalty`, `id_loyalty_state`, `points`, `date_add`)
			VALUES ( '.$id_loyalty.', '.(int)Tools::getValue('id_loyalty_state').', '.(int)Tools::getValue('points').', NOW())');

		$languages = Language::getLanguages();
		foreach ($languages as $language)
		{
			Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'loyalty_text_lang` SET `name` = \''.Tools::getValue('loyalty_text_'.(int)($language['id_lang'])).'\' WHERE id_loyalty = '.$id_loyalty.' and id_lang = '.(int)($language['id_lang']));			
		}
			
		Tools::redirectAdmin('index.php?controller=AdminCustomers&id_customer='.$this->customerID.'&viewcustomer&token='.$this->customerToken);			
	}

}
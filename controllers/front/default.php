<?php
/**
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
 */

/**
 * @since 1.5.0
 */
class LoyaltyDefaultModuleFrontController extends ModuleFrontController
{
    /** @var bool $ssl */
    public $ssl = true;
    /** @var bool $display_column_left */
    public $display_column_left = false;

    /**
     * LoyaltyDefaultModuleFrontController constructor.
     *
     * @throws PrestaShopException
     */
    public function __construct()
    {
        $this->auth = true;
        parent::__construct();

        $this->context = Context::getContext();

        // Declare smarty function to render pagination link
        smartyRegisterFunction($this->context->smarty, 'function', 'summarypaginationlink', ['LoyaltyDefaultModuleFrontController', 'getSummaryPaginationLink']);
    }

    /**
     * @see FrontController::postProcess()
     */
    public function postProcess()
    {
        if (Tools::getValue('process') === 'transformpoints') {
            $this->processTransformPoints();
        }
    }

    /**
     * Transform loyalty point to a voucher
     */
    public function processTransformPoints()
    {
        $customerPoints = (int) LoyaltyModule::getPointsByCustomer((int) $this->context->customer->id);
        if ($customerPoints > 0) {
            /* Generate a voucher code */
            $voucherCode = null;
            do {
                $voucherCode = 'FID'.rand(1000, 100000);
            } while (CartRule::cartRuleExists($voucherCode));

            // Voucher creation and affectation to the customer
            $cartRule = new CartRule();
            $cartRule->code = $voucherCode;
            $cartRule->id_customer = (int) $this->context->customer->id;
            $cartRule->reduction_currency = (int) $this->context->currency->id;
            $cartRule->reduction_amount = LoyaltyModule::getVoucherValue((int) $customerPoints);
            $cartRule->quantity = 1;
            $cartRule->highlight = 1;
            $cartRule->quantity_per_user = 1;
            $cartRule->reduction_tax = (bool) Configuration::get('PS_LOYALTY_TAX');

            // If merchandise returns are allowed, the voucher musn't be usable before this max return date
            $date_from = Db::getInstance()->getValue('
			SELECT UNIX_TIMESTAMP(date_add) n
			FROM '._DB_PREFIX_.'loyalty
			WHERE id_cart_rule = 0 AND id_customer = '.(int) $this->context->cookie->id_customer.'
			ORDER BY date_add DESC');

            if (Configuration::get('PS_ORDER_RETURN')) {
                $date_from += 60 * 60 * 24 * (int) Configuration::get('PS_ORDER_RETURN_NB_DAYS');
            }

            $cartRule->date_from = date('Y-m-d H:i:s', $date_from);
            $cartRule->date_to = date('Y-m-d H:i:s', strtotime($cartRule->date_from.' +1 year'));

            $cartRule->minimum_amount = (float) Configuration::get('PS_LOYALTY_MINIMAL');
            $cartRule->minimum_amount_currency = (int) $this->context->currency->id;
            $cartRule->active = 1;

            $categories = Configuration::get('PS_LOYALTY_VOUCHER_CATEGORY');
            if ($categories != '' && $categories != 0) {
                $categories = explode(',', Configuration::get('PS_LOYALTY_VOUCHER_CATEGORY'));
            } else {
                die (Tools::displayError());
            }

            $languages = Language::getLanguages(true);
            $defaultText = Configuration::get('PS_LOYALTY_VOUCHER_DETAILS', (int) Configuration::get('PS_LANG_DEFAULT'));

            foreach ($languages as $language) {
                $text = Configuration::get('PS_LOYALTY_VOUCHER_DETAILS', (int) $language['id_lang']);
                $cartRule->name[(int) $language['id_lang']] = $text ? strval($text) : strval($defaultText);
            }


            $containsCategories = is_array($categories) && count($categories);
            if ($containsCategories) {
                $cartRule->product_restriction = 1;
            }
            $cartRule->add();

            //Restrict cartRules with categories
            if ($containsCategories) {

                //Creating rule group
                $idCartRule = (int) $cartRule->id;
                $sql = "INSERT INTO "._DB_PREFIX_."cart_rule_product_rule_group (id_cart_rule, quantity) VALUES ('$idCartRule', 1)";
                Db::getInstance()->execute($sql);
                $id_group = (int) Db::getInstance()->Insert_ID();

                //Creating product rule
                $sql = "INSERT INTO "._DB_PREFIX_."cart_rule_product_rule (id_product_rule_group, type) VALUES ('$id_group', 'categories')";
                Db::getInstance()->execute($sql);
                $idProductRule = (int) Db::getInstance()->Insert_ID();

                //Creating restrictions
                $values = [];
                foreach ($categories as $category) {
                    $category = (int) $category;
                    $values[] = "('$idProductRule', '$category')";
                }
                $values = implode(',', $values);
                $sql = "INSERT INTO "._DB_PREFIX_."cart_rule_product_rule_value (id_product_rule, id_item) VALUES $values";
                Db::getInstance()->execute($sql);
            }


            // Register order(s) which contributed to create this voucher
            if (!LoyaltyModule::registerDiscount($cartRule)) {
                $cartRule->delete();
            }
        }

        Tools::redirect($this->context->link->getModuleLink('loyalty', 'default', ['process' => 'summary']));
    }

    /**
     * @see FrontController::initContent()
     */
    public function initContent()
    {
        parent::initContent();
        $this->context->controller->addJqueryPlugin(['dimensions', 'cluetip']);

        if (Tools::getValue('process') == 'summary') {
            $this->assignSummaryExecution();
        }
    }

    /**
     * Render pagination link for summary
     *
     * @param array $params Array with to parameters p (for page number) and n (for nb of items per page)
     *
     * @return string link
     * @throws PrestaShopException
     */
    public static function getSummaryPaginationLink($params, &$smarty)
    {
        if (!isset($params['p'])) {
            $p = 1;
        } else {
            $p = $params['p'];
        }

        if (!isset($params['n'])) {
            $n = 10;
        } else {
            $n = $params['n'];
        }

        return Context::getContext()->link->getModuleLink(
            'loyalty',
            'default',
            [
                'process' => 'summary',
                'p'       => $p,
                'n'       => $n,
            ]
        );
    }

    /**
     * Assign summary template
     */
    public function assignSummaryExecution()
    {
        $customer_points = (int) LoyaltyModule::getPointsByCustomer((int) $this->context->customer->id);
        $orders = LoyaltyModule::getAllByIdCustomer((int) $this->context->customer->id, (int) $this->context->language->id);
        $displayorders = LoyaltyModule::getAllByIdCustomer(
            (int) $this->context->customer->id,
            (int) $this->context->language->id, false, true,
            ((int) Tools::getValue('n') > 0 ? (int) Tools::getValue('n') : 10),
            ((int) Tools::getValue('p') > 0 ? (int) Tools::getValue('p') : 1)
        );
        $this->context->smarty->assign([
            'orders'                 => $orders,
            'displayorders'          => $displayorders,
            'totalPoints'            => (int) $customer_points,
            'voucher'                => LoyaltyModule::getVoucherValue($customer_points, (int) $this->context->currency->id),
            'validation_id'          => LoyaltyStateModule::getValidationId(),
            'transformation_allowed' => $customer_points > 0,
            'page'                   => ((int) Tools::getValue('p') > 0 ? (int) Tools::getValue('p') : 1),
            'nbpagination'           => ((int) Tools::getValue('n') > 0 ? (int) Tools::getValue('n') : 10),
            'nArray'                 => [10, 20, 50],
            'max_page'               => floor(count($orders) / ((int) Tools::getValue('n') > 0 ? (int) Tools::getValue('n') : 10)),
            'pagination_link'        => Context::getContext()->link->getModuleLink('loyalty', 'default'),
        ]);

        /* Discounts */
        $nb_discounts = 0;
        $discounts = [];
        if ($ids_discount = LoyaltyModule::getDiscountByIdCustomer((int) $this->context->customer->id)) {
            $nb_discounts = count($ids_discount);
            foreach ($ids_discount as $key => $discount) {
                $discounts[$key] = new CartRule((int) $discount['id_cart_rule'], (int) $this->context->cookie->id_lang);
                $discounts[$key]->orders = LoyaltyModule::getOrdersByIdDiscount((int) $discount['id_cart_rule']);
            }
        }

        $all_categories = Category::getSimpleCategories((int) $this->context->cookie->id_lang);
        $voucherCategories = Configuration::get('PS_LOYALTY_VOUCHER_CATEGORY');
        if ($voucherCategories != '' && $voucherCategories != 0) {
            $voucherCategories = explode(',', Configuration::get('PS_LOYALTY_VOUCHER_CATEGORY'));
        } else {
            die(Tools::displayError());
        }

        if (count($voucherCategories) == count($all_categories)) {
            $categoriesNames = null;
        } else {
            $categoriesNames = [];
            foreach ($all_categories as $k => $all_category) {
                if (in_array($all_category['id_category'], $voucherCategories)) {
                    $categoriesNames[$all_category['id_category']] = trim($all_category['name']);
                }
            }
            if (!empty($categoriesNames)) {
                $categoriesNames = Tools::truncate(implode(', ', $categoriesNames), 100).'.';
            } else {
                $categoriesNames = null;
            }
        }
        $this->context->smarty->assign([
            'nbDiscounts'    => (int) $nb_discounts,
            'discounts'      => $discounts,
            'minimalLoyalty' => (float) Configuration::get('PS_LOYALTY_MINIMAL'),
            'categories'     => $categoriesNames,
        ]);

        $this->setTemplate('module:loyalty/views/templates/front/loyalty.tpl');
    }
}

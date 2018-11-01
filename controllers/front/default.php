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

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * @since 1.5.0
 */
class LoyaltyDefaultModuleFrontController extends ModuleFrontController
{
    const MAX_ITEMS_PER_PAGE = 10;

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

        if (Tools::getValue('process') === 'summary') {
            $this->assignSummaryExecution();
        }

        if (!$this->template) {
            // Redirect to summary page when the template is undefined
            Tools::redirectLink($this->context->link->getModuleLink($this->module->name, 'default', ['process' => 'summary'], true));
        }
    }

    /**
     * Assign summary template
     *
     * @throws PrestaShopException
     */
    public function assignSummaryExecution()
    {
        $customerPoints = (int) LoyaltyModule::getPointsByCustomer((int) $this->context->customer->id);
        $orders = LoyaltyModule::getAllByIdCustomer((int) $this->context->customer->id, (int) $this->context->language->id);
        $page = (int) Tools::getValue('p') > 0 ? (int) Tools::getValue('p') : 1;
        $numberPerPage = (int) Tools::getValue('n') > 0 ? (int) Tools::getValue('n') : static::MAX_ITEMS_PER_PAGE;
        $displayorders = LoyaltyModule::getAllByIdCustomer(
            (int) $this->context->customer->id,
            (int) $this->context->language->id, false, true,
            $numberPerPage,
            $page
        );
        $maxPage = (int) ceil(count($orders) / static::MAX_ITEMS_PER_PAGE);
        $pagination = null;
        if (count($orders) > static::MAX_ITEMS_PER_PAGE) {
            $pagination = [
                'items_shown_from'    => max((($page - 1) * $numberPerPage) + 1, 1),
                'items_shown_to'      => min($page * $numberPerPage, count($orders)),
                'total_items'         => count($orders),
                'should_be_displayed' => true,
                'pages'               => array_merge(array_filter([
                    [
                        'current'   => $page === 1,
                        'type'      => 'previous',
                        'clickable' => true,
                        'url'       => $this->context->link->getModuleLink(
                            $this->module->name,
                            'default',
                            ['process' => 'summary', 'n' => $numberPerPage, 'p' => $page - 1],
                            Tools::usingSecureMode()
                        ),
                    ],
                ], function () use ($page) {
                    return $page !== 1;
                }), array_map(function ($currentPage) use ($numberPerPage, $page) {
                    $currentPage = (int) $currentPage;
                    return [
                        'current'   => $currentPage === $page,
                        'type'      => '',
                        'page'      => $currentPage,
                        'clickable' => $currentPage !== $page,
                        'url'       => $this->context->link->getModuleLink(
                            $this->module->name,
                            'default',
                            ['process' => 'summary', 'n' => $numberPerPage, 'p' => $currentPage],
                            Tools::usingSecureMode()
                        ),
                    ];
                }, range(1, $maxPage)), array_filter([
                    [
                        'current'   => $page === $maxPage,
                        'type'      => 'next',
                        'clickable' => true,
                        'url'       => $this->context->link->getModuleLink(
                            $this->module->name,
                            'default',
                            ['process' => 'summary', 'n' => $numberPerPage, 'p' => $page + 1],
                            Tools::usingSecureMode()
                        ),
                    ],
                ], function () use ($page, $maxPage) {
                    return $page !== $maxPage;
                })),
            ];
        }

        $this->context->smarty->assign([
            'orders'                 => $orders,
            'displayorders'          => $displayorders,
            'totalPoints'            => (int) $customerPoints,
            'voucher'                => LoyaltyModule::getVoucherValue($customerPoints, (int) $this->context->currency->id),
            'validation_id'          => LoyaltyStateModule::getValidationId(),
            'transformation_allowed' => $customerPoints > 0,
            'pagination'             => $pagination,
        ]);

        /* Discounts */
        $nbDiscounts = 0;
        $discounts = [];
        if ($idsDiscount = LoyaltyModule::getDiscountByIdCustomer((int) $this->context->customer->id)) {
            $nbDiscounts = count($idsDiscount);
            foreach ($idsDiscount as $key => $discount) {
                $discounts[$key] = new CartRule((int) $discount['id_cart_rule'], (int) $this->context->cookie->id_lang);
                $discounts[$key]->orders = LoyaltyModule::getOrdersByIdDiscount((int) $discount['id_cart_rule']);
            }
        }

        $allCategories = Category::getSimpleCategories((int) $this->context->cookie->id_lang);
        $voucherCategories = Configuration::get('PS_LOYALTY_VOUCHER_CATEGORY');
        if ($voucherCategories != '' && $voucherCategories != 0) {
            $voucherCategories = explode(',', Configuration::get('PS_LOYALTY_VOUCHER_CATEGORY'));
        } else {
            die(Tools::displayError());
        }

        if (count($voucherCategories) == count($allCategories)) {
            $categoriesNames = null;
        } else {
            $categoriesNames = [];
            foreach ($allCategories as $k => $allCategory) {
                if (in_array($allCategory['id_category'], $voucherCategories)) {
                    $categoriesNames[$allCategory['id_category']] = trim($allCategory['name']);
                }
            }
            if (!empty($categoriesNames)) {
                $categoriesNames = Tools::truncate(implode(', ', $categoriesNames), 100).'.';
            } else {
                $categoriesNames = null;
            }
        }
        $this->context->smarty->assign([
            'nbDiscounts'    => (int) $nbDiscounts,
            'discounts'      => $discounts,
            'minimalLoyalty' => (float) Configuration::get('PS_LOYALTY_MINIMAL'),
            'categories'     => $categoriesNames,
        ]);

        $this->setTemplate("module:{$this->module->name}/views/templates/front/loyalty.tpl");
    }
}

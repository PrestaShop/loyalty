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
 * Class LoyaltyModule
 */
class LoyaltyModule extends ObjectModel
{
    /** @var int $id_loyalty_state */
    public $id_loyalty_state;
    /** @var int $id_customer */
    public $id_customer;
    /** @var int $id_order */
    public $id_order;
    /** @var int $id_cart_rule */
    public $id_cart_rule;
    /** @var int $points */
    public $points;
    /** @var string $date_add */
    public $date_add;
    /** @var string $date_upd */
    public $date_upd;

    /**
     * @see ObjectModel::$definition
     */
    public static $definition = [
        'table'   => 'loyalty',
        'primary' => 'id_loyalty',
        'fields'  => [
            'id_loyalty_state' => ['type' => self::TYPE_INT,  'validate' => 'isInt'],
            'id_customer'      => ['type' => self::TYPE_INT,  'validate' => 'isInt', 'required' => true],
            'id_order'         => ['type' => self::TYPE_INT,  'validate' => 'isInt'],
            'id_cart_rule'     => ['type' => self::TYPE_INT,  'validate' => 'isInt'],
            'points'           => ['type' => self::TYPE_INT,  'validate' => 'isInt', 'required' => true],
            'date_add'         => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
            'date_upd'         => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
        ],
    ];

    /**
     * @param bool $nullValues
     * @param bool $autodate
     *
     * @return bool|void
     * @throws PrestaShopException
     */
    public function save($nullValues = false, $autodate = true)
    {
        parent::save($nullValues, $autodate);

        $this->historize();
    }

    /**
     * @param int $idOrder
     *
     * @return bool
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function getByOrderId($idOrder)
    {
        if (!Validate::isUnsignedId($idOrder)) {
            return false;
        }

        $result = Db::getInstance()->getRow('
		SELECT f.id_loyalty
		FROM `'._DB_PREFIX_.'loyalty` f
		WHERE f.id_order = '.(int) ($idOrder));

        return isset($result['id_loyalty']) ? $result['id_loyalty'] : false;
    }

    /**
     * @param Order $order
     *
     * @return bool|int
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws \PrestaShop\PrestaShop\Adapter\CoreException
     */
    public static function getOrderNbPoints(Order $order)
    {
        if (!Validate::isLoadedObject($order)) {
            return false;
        }

        return self::getCartNbPoints(new Cart((int) $order->id_cart));
    }

    /**
     * @param      $cart
     * @param null $newProduct
     *
     * @return int
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function getCartNbPoints($cart, $newProduct = null)
    {
        $total = 0;
        if (Validate::isLoadedObject($cart)) {
            $currentContext = Context::getContext();
            $context = clone $currentContext;
            $context->cart = $cart;
            // if customer is logged we do not recreate it
            if (!$context->customer->isLogged(true)) {
                $context->customer = new Customer($context->cart->id_customer);
            }
            $context->language = new Language($context->cart->id_lang);
            $context->shop = new Shop($context->cart->id_shop);
            $context->currency = new Currency($context->cart->id_currency, null, $context->shop->id);

            $cartProducts = $cart->getProducts();
            $taxesEnabled = Product::getTaxCalculationMethod();
            if (isset($newProduct) AND !empty($newProduct)) {
                $cartProductsNew['id_product'] = (int) $newProduct->id;
                if ($taxesEnabled == PS_TAX_EXC) {
                    $cartProductsNew['price'] = number_format($newProduct->getPrice(false, (int) $newProduct->getIdProductAttributeMostExpensive()), 2, '.', '');
                } else {
                    $cartProductsNew['price_wt'] = number_format($newProduct->getPrice(true, (int) $newProduct->getIdProductAttributeMostExpensive()), 2, '.', '');
                }
                $cartProductsNew['cart_quantity'] = 1;
                $cartProducts[] = $cartProductsNew;
            }
            foreach ($cartProducts as $product) {
                if (!(int) (Configuration::get('PS_LOYALTY_NONE_AWARD')) && Product::isDiscounted((int) $product['id_product'])) {
                    if (isset(Context::getContext()->smarty) && is_object($newProduct) && $product['id_product'] == $newProduct->id) {
                        Context::getContext()->smarty->assign('no_pts_discounted', 1);
                    }
                    continue;
                }
                $total += ($taxesEnabled == PS_TAX_EXC ? $product['price'] : $product['price_wt']) * (int) ($product['cart_quantity']);
            }
            foreach ($cart->getCartRules(false) as $cartRule) {
                if ($taxesEnabled == PS_TAX_EXC) {
                    $total -= $cartRule['value_tax_exc'];
                } else {
                    $total -= $cartRule['value_real'];
                }
            }

        }

        return self::getNbPointsByPrice($total);
    }

    /**
     * @param int      $nbPoints
     * @param null|int $idCurrency
     *
     * @return float|int
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function getVoucherValue($nbPoints, $idCurrency = null)
    {
        $currency = $idCurrency ? new Currency($idCurrency) : Context::getContext()->currency->id;

        return (int) $nbPoints * (float) Tools::convertPrice(Configuration::get('PS_LOYALTY_POINT_VALUE'), $currency);
    }

    /**
     * @param float $price
     *
     * @return int
     */
    public static function getNbPointsByPrice($price)
    {
        if (Configuration::get('PS_CURRENCY_DEFAULT') != Context::getContext()->currency->id) {
            if (Context::getContext()->currency->conversion_rate) {
                $price = $price / Context::getContext()->currency->conversion_rate;
            }
        }

        /* Prevent division by zero */
        $points = 0;
        if ($pointRate = (float) (Configuration::get('PS_LOYALTY_POINT_RATE'))) {
            $points = floor(number_format($price, 2, '.', '') / $pointRate);
        }

        return (int) $points;
    }

    /**
     * @param int $idCustomer
     *
     * @return false|null|string
     *
     * @throws PrestaShopException
     */
    public static function getPointsByCustomer($idCustomer)
    {
        $validity_period = Configuration::get('PS_LOYALTY_VALIDITY_PERIOD');
        $sql_period = '';
        if ((int) $validity_period > 0) {
            $sql_period = ' AND datediff(NOW(),f.date_add) <= '.$validity_period;
        }

        return
            Db::getInstance()->getValue('
		SELECT SUM(f.points) points
		FROM `'._DB_PREFIX_.'loyalty` f
		WHERE f.id_customer = '.(int) $idCustomer.'
		AND f.id_loyalty_state IN ('.(int) LoyaltyStateModule::getValidationId().', '.(int) LoyaltyStateModule::getNoneAwardId().')
		'.$sql_period)
            +
            Db::getInstance()->getValue('
		SELECT SUM(f.points) points
		FROM `'._DB_PREFIX_.'loyalty` f
		WHERE f.id_customer = '.(int) $idCustomer.'
		AND f.id_loyalty_state = '.(int) LoyaltyStateModule::getCancelId().'
		AND points < 0
		'.$sql_period);
    }

    /**
     * @param int  $idCustomer
     * @param int  $idLang
     * @param bool $onlyValidate
     * @param bool $pagination
     * @param int  $nb
     * @param int  $page
     *
     * @return array|false|mysqli_result|null|PDOStatement|resource
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function getAllByIdCustomer($idCustomer, $idLang, $onlyValidate = false, $pagination = false, $nb = 10, $page = 1)
    {
        $validityPeriod = Configuration::get('PS_LOYALTY_VALIDITY_PERIOD');
        $sqlPeriod = '';
        if ((int) $validityPeriod > 0) {
            $sqlPeriod = ' AND datediff(NOW(),f.date_add) <= '.$validityPeriod;
        }

        $query = '
		SELECT f.id_order AS id, f.date_add AS date, (o.total_paid - o.total_shipping) total_without_shipping, f.points, f.id_loyalty, f.id_loyalty_state, fsl.name state
		FROM `'._DB_PREFIX_.'loyalty` f
		LEFT JOIN `'._DB_PREFIX_.'orders` o ON (f.id_order = o.id_order)
		LEFT JOIN `'._DB_PREFIX_.'loyalty_state_lang` fsl ON (f.id_loyalty_state = fsl.id_loyalty_state AND fsl.id_lang = '.(int) ($idLang).')
		WHERE f.id_customer = '.(int) ($idCustomer).$sqlPeriod;
        if ($onlyValidate === true) {
            $query .= ' AND f.id_loyalty_state = '.(int) LoyaltyStateModule::getValidationId();
        }
        $query .= ' GROUP BY f.id_loyalty '.
            ($pagination ? 'LIMIT '.(((int) ($page) - 1) * (int) ($nb)).', '.(int) ($nb) : '');

        return Db::getInstance()->executeS($query);
    }

    public static function getDiscountByIdCustomer($id_customer, $last = false)
    {
        $query = '
		SELECT f.id_cart_rule AS id_cart_rule, f.date_upd AS date_add
		FROM `'._DB_PREFIX_.'loyalty` f
		LEFT JOIN `'._DB_PREFIX_.'orders` o ON (f.`id_order` = o.`id_order`)
		INNER JOIN `'._DB_PREFIX_.'cart_rule` cr ON (cr.`id_cart_rule` = f.`id_cart_rule`)
		WHERE f.`id_customer` = '.(int) ($id_customer).'
		AND f.`id_cart_rule` > 0
		AND o.`valid` = 1
		GROUP BY f.id_cart_rule';
        if ($last) {
            $query .= ' ORDER BY f.id_loyalty DESC LIMIT 0,1';
        }

        return Db::getInstance()->executeS($query);
    }

    public static function registerDiscount($cartRule)
    {
        if (!Validate::isLoadedObject($cartRule)) {
            die(Tools::displayError('Incorrect object CartRule.'));
        }
        $items = self::getAllByIdCustomer((int) $cartRule->id_customer, null, true);
        $associated = false;
        foreach ($items AS $item) {
            $lm = new LoyaltyModule((int) $item['id_loyalty']);

            /* Check for negative points for this order */
            $negativePoints = (int) Db::getInstance()->getValue('SELECT SUM(points) points FROM '._DB_PREFIX_.'loyalty WHERE id_order = '.(int) $item['id'].' AND id_loyalty_state = '.(int) LoyaltyStateModule::getCancelId().' AND points < 0');

            if ($lm->points + $negativePoints <= 0) {
                continue;
            }

            $lm->id_cart_rule = (int) $cartRule->id;
            $lm->id_loyalty_state = (int) LoyaltyStateModule::getConvertId();
            $lm->save();
            $associated = true;
        }

        return $associated;
    }

    public static function getOrdersByIdDiscount($id_cart_rule)
    {
        $items = Db::getInstance()->executeS('
		SELECT f.id_order AS id_order, f.points AS points, f.date_upd AS date
		FROM `'._DB_PREFIX_.'loyalty` f
		WHERE f.id_cart_rule = '.(int) $id_cart_rule.' AND f.id_loyalty_state = '.(int) LoyaltyStateModule::getConvertId());

        if (!empty($items) AND is_array($items)) {
            foreach ($items AS $key => $item) {
                $order = new Order((int) $item['id_order']);
                $items[$key]['id_currency'] = (int) $order->id_currency;
                $items[$key]['id_lang'] = (int) $order->id_lang;
                $items[$key]['total_paid'] = $order->total_paid;
                $items[$key]['total_shipping'] = $order->total_shipping;
            }

            return $items;
        }

        return false;
    }

    /**
     * Register all transaction in a specific history table
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    protected function historize()
    {
        Db::getInstance()->execute('
		INSERT INTO `'._DB_PREFIX_.'loyalty_history` (`id_loyalty`, `id_loyalty_state`, `points`, `date_add`)
		VALUES ('.(int) ($this->id).', '.(int) ($this->id_loyalty_state).', '.(int) ($this->points).', NOW())');
    }

}

<?php
/**
 * Сonvead for Joomla
 *
 * @version     1.6
 * @author      Arkadiy Sedelnikov, Joomline
 * @copyright   © 2015. All rights reserved.
 * @license     GNU/GPL v.2 or later.
 */
defined('_JEXEC') or die;

jimport('joomla.filesystem.file');

class plgSystemConvead extends JPlugin
{
    private
        $input,
        $isAdmin,
        $app_key,
        $userId,
        $userFirstName,
        $userLastName,
        $userEmail,
        $userPhone,
        $userDateOfBirth,
        $userGender,
        $currencyValues,
        $isJoomlaThree
    ;

    public function __construct(& $subject, $config)
    {
        parent::__construct($subject, $config);
        $this->input = new JInput();
        $this->isAdmin = JFactory::getApplication()->isAdmin();
        $this->app_key = $this->params->get('app_key', '');
        $this->rub_id = $this->params->get('rub_id', 'RUB');
        $user = JFactory::getUser();
        $this->userId = $user->id;
        $this->userFirstName = $user->get('name', '');
        $this->userEmail = $user->get('email', '');
        $this->userLastName = '';
        $this->userPhone = '';
        $this->userDateOfBirth = '';
        $this->userGender = '';
        $this->isJoomlaThree = version_compare(JVERSION, '3.0', '>=');
    }

    /** Main script
     * @throws Exception
     */
    public function onBeforeRender()
    {
        if(!empty($this->app_key) && !$this->isAdmin)
        {
            $conveadSettings = '';
            $app = JFactory::getApplication();
            $guestUID = $app->getUserState('convead.guest_uid', '');

            if($guestUID == '' && $this->userId == 0)
            {
                $uid = md5(date('Y-m-d h:i:s').$_SERVER['REMOTE_ADDR']);
                $app->setUserState('convead.guest_uid', $uid);
            }
            if($this->userId > 0)
            {
                $this->updateUserInfo();

                $visitor_info = array();
                if(!empty($this->userFirstName))        $visitor_info['first_name']      = $this->userFirstName;
                if(!empty($this->userLastName))         $visitor_info['last_name']       = $this->userLastName;
                if(!empty($this->userEmail))            $visitor_info['email']           = $this->userEmail;
                if(!empty($this->userPhone))            $visitor_info['phone']           = $this->userPhone;
                if(!empty($this->userDateOfBirth))      $visitor_info['date_of_birth']   = $this->userDateOfBirth;
                if(!empty($this->userGender))           $visitor_info['gender']          = $this->userGender;

                JPluginHelper::importPlugin('convead');

                if($this->isJoomlaThree){
                    $dispatcher = JEventDispatcher::getInstance();
                }
                else{
                    $dispatcher = JDispatcher::getInstance();
                }

                $dispatcher->trigger('onConveadSettings', array(&$visitor_info));

                $conveadSettings = "
                    visitor_uid: '{$this->userId}',
                    visitor_info: {";
                $i = 0;
                foreach($visitor_info as $k => $v)
                {
                    if($i > 0)
                    {
                        $conveadSettings .= ",";
                    }
                    $conveadSettings .= "\n                        $k: '$v'";
                    $i++;
                }
                $conveadSettings .= "
                    },";
            }

            $script = "
                window.ConveadSettings = {
                    $conveadSettings
                    app_key: '".$this->app_key."'
                };
                (function(w,d,c){w[c]=w[c]||function(){(w[c].q=w[c].q||[]).push(arguments)};var ts = (+new Date()/86400000|0)*86400;var s = d.createElement('script');s.type = 'text/javascript';s.async = true;s.src = 'https://tracker.convead.io/widgets/'+ts+'/widget-".$this->app_key.".js';var x = d.getElementsByTagName('script')[0];x.parentNode.insertBefore(s, x);})(window,document,'convead');
            ";

            JFactory::getDocument()->addScriptDeclaration($script);
        }
    }


    /** Joomla system event
     */
    public function onAfterDispatch()
    {
        $app = JFactory::getApplication();
        if ($app->getName() != 'site')
        {
            return true;
        }


        // Hikashop view product event
        $input = $app->input;
        $option = $input->getCmd('option', '');
        $task = $input->getCmd('task', '');
        $ctrl = $input->getCmd('ctrl', '');
        $cid = $input->getInt('cid', 0);
        if($option == 'com_hikashop' and $ctrl == 'product' and $task == 'show' and $cid)
        {
            $productClass = hikashop_get('class.product');
            $product = $productClass->get($cid);

            /** Note: Exit if product with variants.
            *   Variants processed in event 'onAfterProductCharacteristicsLoad' of Hikashop and in this plugin
            */
            if (isset($product->product_type) and  $product->product_type == 'variant') {
                return true;
            }
            if (isset($product->product_id) and  $product->product_id) {
                $catlist = $productClass->getCategories($product->product_id);
                $category_id = 0;
                if (count($catlist)) {
                    if (isset($catlist[0])) {
                        $category_id = $catlist[0];
                    }
                }
                $this->productView($product->product_id, $product->product_name, $category_id);
            }
            unset($product);
        }



        return true;
    }

    /** Hikashop product
     * @param $element
     * @param $mainCharacteristics
     * @param $characteristics
     */
    public function onAfterProductCharacteristicsLoad( &$element, &$mainCharacteristics, &$characteristics )
    {
        $input = JFactory::getApplication()->input;
        $task = $input->getCmd('task', '');
        $ctrl = $input->getCmd('ctrl', '');
        $cid = $input->getInt('cid', 0);
        if(!($ctrl == 'product' && $task == 'show'))
        {
            return;
        }

        $product_id = $element->product_id;
        $product_name = $element->product_name;

        if ($cid and $cid <> $element->product_id) {
            if (isset($characteristics) and is_array($characteristics) and count($characteristics)) {
                foreach ($characteristics as $ch) {
                    if ($ch->variant_product_id == $cid) {
                        $product_id = $ch->variant_product_id;
                        $product_name .= ': '.$ch->characteristic_value;
                        break;
                    }
                }
            }
        }

        $this->productView($product_id, $product_name, $element->category_id);
    }

    /** Hikashop cart update
     * @param $cartClass
     * @param $cart
     * @param $product_id
     * @param $quantity
     * @param $add
     * @param $type
     * @param $resetCartWhenUpdate
     * @param $force
     * @param $updated
     */
    public function onAfterCartUpdate( &$cartClass, &$cart, &$product_id, &$quantity, &$add, &$type,&$resetCartWhenUpdate,&$force,&$updated )
    {
        if(JFactory::getApplication()->input->getCmd('option', '') != 'com_hikashop'  || $cart->cart_type != 'cart')
        {
            return;
        }

        $fullCart = $cartClass->loadFullCart();

        $items = array();
        $rub_id = $this->rub_id ? $this->rub_id : 'RUB';
        $currencyId = $this->HIKAgetCurrId($rub_id);
        $currencyClass = hikashop_get('class.currency');

        foreach ($fullCart->products as $v)
        {
            if($v->cart_product_quantity == 0){
                continue;
            }
//            $price = (float)$v->prices[0]->unit_price->price_orig_value_with_tax;
//            $productCurrencyId = (int)$v->prices[0]->unit_price->price_orig_currency_id;
            $price = (float)$v->prices[0]->unit_price->price_value_with_tax;
            $productCurrencyId = (int)$v->prices[0]->unit_price->price_currency_id;
            $price = $currencyClass->convertUniquePrice($price, $productCurrencyId, $currencyId);
//            $id = $v->product_parent_id == 0 ? $v->product_id : $v->product_parent_id;
            $id = $v->product_id;
            $items[] = array(
                'id' => (int)$id,
                'count' => (float)$v->cart_product_quantity,
                'price' => (float)$price
            );
        }

        $this->submitCart($items);
    }

    /** Hikashop order
     * @param $order
     * @param $send_email
     * @throws Exception
     */
    public function onAfterOrderCreate(&$order, &$send_email)
    {
        $input = JFactory::getApplication()->input;
        $task = $input->getCmd('task', '');
        $ctrl = $input->getCmd('ctrl', '');
        $option = $input->getCmd('option', '');

        if($option != 'com_hikashop' || !($ctrl == 'checkout' && $task == 'step' && !$this->isAdmin))
        {
            return;
        }

        $items = array();
        $rub_id = $this->rub_id ? $this->rub_id : 'RUB';
        $currencyId = $this->HIKAgetCurrId($rub_id);
        $currencyClass = hikashop_get('class.currency');

        $order_total = $order->order_full_price - $order->order_shipping_price - $order->order_payment_price;

        if(is_array($order->cart->products) && count($order->cart->products))
        {
            foreach ($order->cart->products as $v)
            {
//                $price = $v->order_product_price/$v->order_product_quantity;
//                $price = (float)$currencyClass->convertUniquePrice($price, $order->order_currency_id, $currencyId);
                $price = $v->order_product_price+(float)$v->order_product_tax;
                $price = $currencyClass->convertUniquePrice($price, $order->order_currency_id, $currencyId);

                $product = new stdClass();
                $product->product_id = (int)$v->product_id;
                $product->qnt = (float)$v->order_product_quantity;
                $product->price = $price;
                $items[] = $product;
            }
        }

//        print_r($items);

        $this->updateUserInfo();

        $orderDetails = $order->cart->shipping_address;

        if(in_array($orderDetails->address_title, array('Mr', 'Dr'))){
            $this->userGender = 'male';
        }
        else if(in_array($orderDetails->address_title, array('Mrs', 'Miss', 'Ms'))){
            $this->userGender = 'female';
        }

        if(!empty($orderDetails->address_firstname))
            $this->userFirstName = $orderDetails->address_firstname;
        if(!empty($orderDetails->address_lastname))
            $this->userLastName = $orderDetails->address_lastname;
        if(!empty($orderDetails->address_telephone))
            $this->userPhone = $orderDetails->address_telephone;
        $email = JFactory::getUser()->get('email','');
        if(!empty($email))
            $this->userEmail = $email;
        $this->submitOrder($order->order_number, $order_total, $items);
    }

    /** Virtuemart product
     * @param $context
     * @param $product
     * @param $params
     * @param int $page
     */
    public function onContentBeforeDisplay($context, &$product, &$params, $page = 0)
    {
        if($context != 'com_virtuemart.productdetails')
            return;

        $category_id = JFactory::getApplication()->input->getInt('virtuemart_category_id', 0);
        if($category_id == 0)
        {
            $category_id = $product->virtuemart_category_id;
        }
        $this->productView($product->virtuemart_product_id, $product->product_name, $category_id);
    }

    /** Virtuemart on add to cart
     * @param $cart
     */
    public function plgVmOnAddToCart($cart)
    {
        $this->virtuemartSubmitCart();
    }

    /** Virtuemart on delete from cart
     * @param $cart
     * @param $prod_id
     */
    public function plgVmOnRemoveFromCart($cart,$prod_id)
    {
        $this->virtuemartSubmitCart();
    }

    /** Virtuemart on refresh cart
     * @param $virtuemart_paymentmethod_id
     * @param $paymentCurrency
     */
    public function plgVmgetPaymentCurrency($virtuemart_paymentmethod_id, &$paymentCurrency)
    {
        $this->virtuemartSubmitCart();
    }

    /** Virtuemart submit cart
     * @param $cart
     */
    private function virtuemartSubmitCart()
    {
        if(!defined('CONVEAD_VM_SUBMIT_CART'))
        {
            define('CONVEAD_VM_SUBMIT_CART', 1);
        }
        else
        {
            return;
        }

        require_once JPATH_ROOT.'/administrator/components/com_virtuemart/helpers/currencydisplay.php';
        $CurrencyDisplay = CurrencyDisplay::getInstance();
        $cart = VirtueMartCart::getCart();
        $cart = clone($cart);
        $productsModel = VmModel::getModel('product');
        $customFieldsModel = VmModel::getModel('customfields');

        foreach($cart->cartProductsData as $k =>$productdata)
        {
            $productdata = (array)$productdata;

            if(isset($productdata['virtuemart_product_id']))
            {
                $productdata['quantity'] = (int)$productdata['quantity'];
                $product = $productsModel->getProduct($productdata['virtuemart_product_id'],TRUE,FALSE,TRUE,$productdata['quantity']);
                $product->customProductData = $productdata['customProductData'];
                $product->quantity = $productdata['quantity'];
                $product->cart_item_id = $k ;
                $product->customfields = $customFieldsModel->getCustomEmbeddedProductCustomFields($product->allIds,0,1);
                $cart->products[$k] = $product;
                $product = null;
            }
        }
        $cart->getCartPrices(true);

        $items = array();
        $rub_id = $this->rub_id ? $this->rub_id : 'RUB';
        if(isset($cart->products) && count($cart->products))
        {
            foreach ($cart->products as $v)
            {
                $price = $CurrencyDisplay->convertCurrencyTo($rub_id, $v->prices['discountedPriceWithoutTax'],false);
                $items[] = array(
                    'id' => (int)$v->virtuemart_product_id,
                    'count' => (float)$v->quantity,
                    'price' => (float)$price
                );
            }
        }

        $this->submitCart($items);
    }

    /** Vireuemart finish
     * @param $this
     * @param $orderDetails
     */
    public function plgVmConfirmedOrder($cart, $order)
    {
        $items = array();
        $rub_id = $this->rub_id ? $this->rub_id : 'RUB';
        $CurrencyDisplay = CurrencyDisplay::getInstance();

        if(is_array($order["items"]) && count($order["items"]))
        {
            foreach ($order["items"] as $v)
            {
                $price = (float)$CurrencyDisplay->convertCurrencyTo($rub_id,$v->product_final_price,false);
                $product = new stdClass();
                $product->product_id = (int)$v->virtuemart_product_id;
                $product->qnt = (float)$v->product_quantity;
                $product->price = $price;
                $items[] = $product;
            }
        }

        $orderDetails = $order["details"]["BT"];

        $order_total = (float)$orderDetails->order_salesPrice + (float)$orderDetails->coupon_discount;

        $this->updateUserInfo();

        if($orderDetails->title == 'Mr'){
            $this->userGender = 'male';
        }
        else if($orderDetails->title == 'Mrs'){
            $this->userGender = 'female';
        }

        if(!empty($orderDetails->first_name))
            $this->userFirstName = $orderDetails->first_name;
        if(!empty($orderDetails->last_name))
            $this->userLastName = $orderDetails->last_name;
        if(!empty($orderDetails->phone_1))
            $this->userPhone = $orderDetails->phone_1;
        if(!empty($orderDetails->title))
            $this->userGender = $orderDetails->title;
        if(!empty($orderDetails->email))
            $this->userEmail = $orderDetails->email;

        $this->submitOrder($orderDetails->order_number, $order_total, $items);
    }

    /** Joomshopping product
     * @param $product
     * @param $view
     * @param $product_images
     * @param $product_videos
     * @param $product_demofiles
     */
    public function onBeforeDisplayProduct(&$product, &$view, &$product_images, &$product_videos, &$product_demofiles)
    {
        $category_id = JFactory::getApplication()->input->getInt('category_id', 0);
        if($category_id == 0 && is_array($product->product_categories) && count($product->product_categories))
        {
            $category_id = $product->product_categories[0]->category_id;
        }
        $this->productView($product->product_id, $product->name, $category_id);
    }

    /** Joomshopping after add to cart
     * @param $cartClass
     * @param $product_id
     * @param $quantity
     * @param $attr_id
     * @param $freeattributes
     * @param $errors
     * @param $displayErrorMessage
     */
    public function onAfterAddProductToCart(&$cartClass, &$product_id, &$quantity, &$attr_id, &$freeattributes, &$errors, &$displayErrorMessage)
    {
        $this->jsSubmitCart($cartClass);
    }

    /** Joomshopping ater delete from cart
     * @param $number_id
     * @param $cartClass
     */
    public function onAfterDeleteProductInCart(&$number_id, &$cartClass)
    {
        $this->jsSubmitCart($cartClass);
    }

    /** Joomshopping after refresh count products
     * @param $quantity
     * @param $cartClass
     */
    public function onAfterRefreshProductInCart(&$quantity, &$cartClass)
    {
        $this->jsSubmitCart($cartClass);
    }

    /** Joomshopping submit cart
     * @param $cart
     */
    private function jsSubmitCart($cart)
    {
        if($cart->type_cart == 'cart')
        {
            $rub_id = $this->rub_id ? $this->rub_id : 'RUB';
            $currencyId = $this->JSgetCurrId($rub_id);
            $jshopConfig = JSFactory::getConfig();
            $frontCurrencyId = $this->JSgetCurrId($jshopConfig->currency_code_iso);
            $items = array();
            if(is_array($cart->products) && count($cart->products))
            {
                foreach ($cart->products as $v)
                {
                    $price = $this->JSconvertPrice($v['price'], $frontCurrencyId, $currencyId);
                    $items[] = array(
                        'id' => (int)$v['product_id'],
                        'count' => (float)$v['quantity'],
                        'price' => (float)$price
                    );
                }
            }
            $this->submitCart($items);
        }
    }

    /** Joomshopping finish
     * @param $text
     * @param $order
     * @param $pm_method
     */
    public function onAfterCreateOrderFull(&$order)
    {
       // $this->updateUserInfo();

        $items = array();
        $db = JFactory::getDbo();
        $query = $db->getQuery(true);
        $query->select('*')
            ->from('`#__jshopping_order_item`')
            ->where('`order_id` = '.$db->quote($order->order_id));
        $result = $db->setQuery($query)->loadObjectList();

        $rub_id = $this->rub_id ? $this->rub_id : 'RUB';
        $currencyId = $this->JSgetCurrId($rub_id);
        $orderCurrencyId = $this->JSgetCurrId($order->currency_code_iso);

        $order_total = $order->order_subtotal - $order->order_discount;

        if(is_array($result) && count($result))
        {
            foreach ($result as $v)
            {
                $price = $this->JSconvertPrice($v->product_item_price, $orderCurrencyId, $currencyId);
                $product = new stdClass();
                $product->product_id = (int)$v->product_id;
                $product->qnt = (float)$v->product_quantity;
                $product->price = (float)$price;
                $items[] = $product;
            }
        }

        if($order->title == 1){
            $this->userGender = 'male';
        }
        else if($order->title == 2){
            $this->userGender = 'female';
        }

        if(!empty($order->f_name))
            $this->userFirstName = $order->f_name;
        if(!empty($order->l_name))
            $this->userLastName = $order->l_name;
        if(!empty($order->phone))
            $this->userPhone = $order->phone;
        if(!empty($order->email))
            $this->userEmail = $order->email;

        $this->submitOrder($order->order_number, $order_total, $items);
    }

    /**
     * Update user info
     */
    private function updateUserInfo()
    {
        switch($this->input->getCmd('option',''))
        {
            case 'com_hikashop' :
                $this->hikashopUpdateUserInfo();
                break;
            case 'com_jshopping' :
                $this->jshoppingUpdateUserInfo();
                break;
            case 'com_virtuemart' :
                $this->virtuemartUpdateUserInfo();
                break;
            default:
                break;
        }
    }

    /**
     * Update hikashop user info
     */
    private function hikashopUpdateUserInfo()
    {
        $userId = hikashop_loadUser();

        if($userId == 0)
        {
            return;
        }
        $address = hikashop_get('class.address');
        $addresses = $address->loadUserAddresses($userId);
        if(!is_array($addresses) || !count($addresses)){
            return;
        }
        $address = array_shift($addresses);

        if(in_array($address->address_title, array('Mr', 'Dr'))){
            $this->userGender = 'male';
        }
        else if(in_array($address->address_title, array('Mrs', 'Miss', 'Ms'))){
            $this->userGender = 'female';
        }

        if(!empty($address->address_firstname))
            $this->userFirstName = $address->address_firstname;
        if(!empty($address->address_lastname))
            $this->userLastName = $address->address_lastname;
        if(!empty($address->address_telephone))
            $this->userPhone = $address->address_telephone;
    }

    /**
     * Update jshopping user info
     */
    private function jshoppingUpdateUserInfo()
    {
        $adv_user = JSFactory::getUserShop();

        if($adv_user->user_id <= 0)
        {
            $adv_user = JSFactory::getUserShopGuest();
        }

        if($adv_user->title == 1){
            $this->userGender = 'male';
        }
        else if($adv_user->title == 2){
            $this->userGender = 'female';
        }

        if(!empty($adv_user->f_name))
            $this->userFirstName = $adv_user->f_name;
        if(!empty($adv_user->l_name))
            $this->userLastName = $adv_user->l_name;
        if(!empty($adv_user->email))
            $this->userEmail = $adv_user->email;
        if(!empty($adv_user->phone))
            $this->userPhone = $adv_user->phone;
        if(!empty($adv_user->birthday))
            $this->userDateOfBirth = $adv_user->birthday;
    }

    /**
     * Update virtuemart user info
     */
    private function virtuemartUpdateUserInfo()
    {
        $userId = JFactory::getUser()->id;
        if($userId == 0)
            return;

        $db = JFactory::getDbo();
        $query = $db->getQuery(true);
        $query->select('*')
        ->from('#__virtuemart_userinfos')
        ->where('virtuemart_user_id = '.$db->quote($userId))
        ->where('address_type = '.$db->quote('BT'));
        $result = $db->setQuery($query)->loadObjectList();

        if(is_array($result) && count($result))
        {
            if(!empty($result->first_name))
                $this->userFirstName = $result->first_name;
            if(!empty($result->last_name))
                $this->userLastName = $result->last_name;
            if(!empty($result->phone_1))
                $this->userPhone = $result->phone_1;
            if($result->title == 'Mr'){
                $this->userGender = 'male';
            }
            else if($result->title == 'Mrs'){
                $this->userGender = 'female';
            }
        }
    }

    /** Submit product
     * @param $id
     * @param $name
     * @throws Exception
     */
    private function productView($id, $name, $category_id)
    {
        if(!empty($this->app_key))
        {
            $uri = JUri::getInstance()->toString();
            JFactory::getDocument()->addScriptDeclaration("
            jQuery(function($) {
                convead('event', 'view_product', {
                  product_id: $id,
                  category_id: $category_id,
                  product_name: '$name',
                  product_url: '$uri'
                });
            });
            ");
        }
    }


    /** Submit cart
     * @param array $items
     * @throws Exception
     */
    private function cartView(array $items)
    {
        if(!empty($this->app_key))
        {
            $products = array();
            if(count($items))
            {
                foreach ($items as $v)
                {
                    $products[] = '{product_id: '.$v['id'].', qnt: '.$v['count'].', price: '.$v['price'].'}';
                }
            }
            $products = implode(', ', $products);
            JFactory::getDocument()->addScriptDeclaration("
            jQuery(function($) {
                convead('event', 'update_cart', {
                  items: [
                    $products
                  ]
                });
            });
            ");
        }
    }

    /** Jvavscript submit order to convead
     * @param $orderId
     * @param $total
     * @param array $items
     * @throws Exception
     */
    private function purchaseView($orderId, $total, array $items)
    {
        if(!empty($this->app_key))
        {
            $products = array();
            if(count($items))
            {
                foreach ($items as $v)
                {
                    $products[] = '{product_id: '.$v['id'].', qnt: '.$v['count'].', price: '.$v['price'].'}';
                }
            }
            $products = implode(', ', $products);
            JFactory::getDocument()->addScriptDeclaration("
            jQuery(function($) {
                convead('event', 'purchase', {
                  order_id: '$orderId',
                  revenue: $total,
                  items: [
                    $products
                  ]
                });
            });
            ");
        }
    }


    /** Submit order to convead
     * @param $order_number
     * @param $order_total
     * @param $items
     */
    private function submitOrder($order_number, $order_total, $items)
    {
        require_once 'lib/ConveadTracker.php';

        $uri = JUri::getInstance();
        $url = $uri->toString(array('host'));

        $visitor_info = array();
        if(!empty($this->userFirstName)){
            $visitor_info['first_name'] = $this->userFirstName;
        }
        if(!empty($this->userLastName)){
            $visitor_info['last_name'] = $this->userLastName;
        }
        if(!empty($this->userEmail)){
            $visitor_info['email'] = $this->userEmail;
        }
        if(!empty($this->userPhone)){
            $visitor_info['phone'] = $this->userPhone;
        }
        if(!empty($this->userDateOfBirth)){
            $visitor_info['date_of_birth'] = $this->userDateOfBirth;
        }
        if(!empty($this->userGender)){
            $visitor_info['gender'] = $this->userGender;
        }

        JPluginHelper::importPlugin('convead');

        if($this->isJoomlaThree){
            $dispatcher = JEventDispatcher::getInstance();
        }
        else{
            $dispatcher = JDispatcher::getInstance();
        }

        $dispatcher->trigger('onConveadSettings', array(&$visitor_info));

        $guestUID = isset($_COOKIE['convead_guest_uid']) ? $_COOKIE['convead_guest_uid'] : '';

        $ConveadTracker = new ConveadTracker( $this->app_key, $url, $guestUID, $this->userId, $visitor_info );

        $return = $ConveadTracker->eventOrder($order_number, $order_total, $items);
    }

    /** Submit cart to convead
     * @param $order_number
     * @param $order_total
     * @param $items
     */
    private function submitCart(array $items)
    {
        if(empty($this->app_key))
        {
            return;
        }

        $this->updateUserInfo();

        require_once 'lib/ConveadTracker.php';

        $uri = JUri::getInstance();
        $url = $uri->toString(array('host'));

        $visitor_info = array();
        if(!empty($this->userFirstName)){
            $visitor_info['first_name'] = $this->userFirstName;
        }
        if(!empty($this->userLastName)){
            $visitor_info['last_name'] = $this->userLastName;
        }
        if(!empty($this->userEmail)){
            $visitor_info['email'] = $this->userEmail;
        }
        if(!empty($this->userPhone)){
            $visitor_info['phone'] = $this->userPhone;
        }
        if(!empty($this->userDateOfBirth)){
            $visitor_info['date_of_birth'] = $this->userDateOfBirth;
        }
        if(!empty($this->userGender)){
            $visitor_info['gender'] = $this->userGender;
        }

        $guestUID = isset($_COOKIE['convead_guest_uid']) ? $_COOKIE['convead_guest_uid'] : '';

        $ConveadTracker = new ConveadTracker( $this->app_key, $url, $guestUID, $this->userId, $visitor_info );

        $products = array();
        if(count($items))
        {
            foreach ($items as $v)
            {
                $products[] = array(
                    "product_id" => $v['id'],
                    "qnt" => $v['count'],
                    "price" => $v['price'],
                );
            }
        }

        $return = $ConveadTracker->eventUpdateCart($products);
    }

    /** Joomshopping load currency id
     * @param $currencyCode
     * @return int
     */
    private function JSgetCurrId($currencyCode)
    {
        $db = JFactory::getDbo();
        $query = $db->getQuery(true);
        $query->select('`currency_id`')
            ->from('`#__jshopping_currencies`')
            ->where('`currency_code_iso` = '.$db->quote($currencyCode))
            ->where('`currency_publish` = 1');
        return (int)$db->setQuery($query)->loadResult();
    }

    /** Joomshopping convert price
     * @param $price
     * @param $productCurrencyId
     * @param $currencyId
     * @return mixed
     */
    private function JSconvertPrice($price, $productCurrencyId, $currencyId)
    {
        if($productCurrencyId ==  $currencyId)
        {
            return $price;
        }

        if(!is_array($this->currencyValues) && !count($this->currencyValues))
        {
            $this->JSloadCurrencyValues();
        }

        $koeff = $this->currencyValues[$currencyId]/$this->currencyValues[$productCurrencyId];

        return $price*$koeff;
    }

    /**
     * Joomshopping load currency values array
     */
    private function JSloadCurrencyValues()
    {
        $db = JFactory::getDbo();
        $query = $db->getQuery(true);
        $query->select('`currency_id`, `currency_value`')
            ->from('`#__jshopping_currencies`');
        $result = $db->setQuery($query)->loadObjectList('currency_id');

        $currs = array();
        foreach($result as $v)
        {
            $currs[$v->currency_id] = $v->currency_value;
        }

        $this->currencyValues = $currs;
    }

    /** Hikashop load currency id
     * @param $currencyCode
     * @return int
     */
    private function HIKAgetCurrId($currencyCode)
    {
        $db = JFactory::getDbo();
        $query = $db->getQuery(true);
        $query->clear()
            ->select('`currency_id`')
            ->from('`#__hikashop_currency`')
            ->where('`currency_code` = '.$db->quote($currencyCode));
        return (int)$db->setQuery($query)->loadResult();
    }
}

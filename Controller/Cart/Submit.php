<?php

namespace Paylix\Pay\Controller\Cart;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;


use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;


class Submit extends Action implements \Magento\Framework\App\CsrfAwareActionInterface
{
    protected $_request;

    public function __construct(Context $context)
    {
        return parent::__construct($context);
    }

    public function execute()
    {

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $config = $objectManager->get('\Magento\Framework\App\Config\ScopeConfigInterface');

        $JsonFactory = $objectManager->get('\Magento\Framework\Controller\Result\JsonFactory');
        $resultJson = $JsonFactory->create();

        $request = $objectManager->get('\Magento\Framework\App\RequestInterface');

        // add product to cart
        $product = $objectManager->get('\Magento\Catalog\Model\Product');
        $cart = $objectManager->get('\Magento\Checkout\Model\Cart');

        // get post data. post is sent with content type json so we need to parse it.
        $tmp = json_decode($request->getContent(), true);


        $productId = isset($tmp['product']) ? $tmp['product'] : 0;

        //if there's product id add it to cart, else parse data from cart
        if ($productId) {

            $options = [];
            $super_attribute = [];
            $configurable_option = '';
            if (isset($tmp['selected_configurable_option']) && !empty($tmp['selected_configurable_option'])) {
                $configurable_option = $tmp['selected_configurable_option'];
            }
            foreach ($tmp as $k => $row) {
                $tmpid = $this->get_string_between($k, 'options[', ']');
                if ($tmpid !== false) {
                    $options[$tmpid] = $row;
                }
                $tmpid = $this->get_string_between($k, 'super_attribute[', ']');
                if ($tmpid !== false) {
                    $super_attribute[$tmpid] = $row;
                }
            }

            // $formKey = $objectManager->get('\Magento\Framework\Data\Form\FormKey');
            $params = [
                'form_key'                     => $tmp['form_key'], //$formKey->getFormKey(),
                'product'                      => $productId,
                'qty'                          => $tmp['qty'],
                'options'                      => $options,
                'super_attribute'              => $super_attribute,
                'selected_configurable_option' => $configurable_option
            ];
            //$params = $tmp;
            //$params['form_key'] = $formKey->getFormKey();

            $productData = $product->load($productId);
            $cart->addProduct($productData, $params);
            $cart->save();
        }
        //return $resultJson->setData($tmp);


        // check if cart is empty
        if (!$cart->getItemsCount()) {
            return $resultJson->setData(['success' => false, 'message' => 'Cart is Empty']);
        }


        //create order
        $storeManager = $objectManager->get('\Magento\Store\Model\StoreManagerInterface');
        $store = $storeManager->getStore();
        $websiteId = $storeManager->getStore()->getWebsiteId();

        $firstName = 'Guest';
        $lastName = 'Paylix';
        $email = 'guestj@paylix.net';
        $password = 'paylix123';

        $address = [
            'customer_address_id'  => '',
            'prefix'               => '',
            'firstname'            => $firstName,
            'middlename'           => '',
            'lastname'             => $lastName,
            'suffix'               => '',
            'company'              => '',
            'street'               => [
                '0' => 'paylix address', // this is mandatory
            ],
            'city'                 => 'New York',
            'country_id'           => 'US', // two letters country code
            'region'               => '', // can be empty '' if no region
            'region_id'            => '43',
            'postcode'             => '10450',
            'telephone'            => '123-456-7890',
            'fax'                  => '',
            'save_in_address_book' => 1
        ];

        $customerFactory = $objectManager->get('\Magento\Customer\Model\CustomerFactory')->create();

        $customerSession = $objectManager->get("\Magento\Customer\Model\Session");

        if ($customerSession->isLoggedIn()) {
            $email = $customerSession->getCustomer()->getEmail();
        }

        /**
         * check whether the email address is already registered or not
         */
        $customer = $customerFactory->setWebsiteId($websiteId)->loadByEmail($email);
        if (!$customer->getId()) {
            try {
                $customer->setEmail($email);
                $customer->setFirstname($firstName);
                $customer->setLastname($lastName);
                $customer->setPassword($password);
                $customer->setStore($store);
                $customer->setConfirmation(null);
                $customer->save();

                $customAddress = $objectManager->get('\Magento\Customer\Model\AddressFactory')->create();
                $customAddress->setData($address)
                    ->setCustomerId($customer->getId())
                    ->setIsDefaultBilling('1')
                    ->setIsDefaultShipping('1')
                    ->setSaveInAddressBook('1');
                $customAddress->save();
            } catch (Exception $e) {
                echo $e->getMessage();
            }
        }

        $customer = $objectManager->get('\Magento\Customer\Api\CustomerRepositoryInterface')->getById($customer->getId());
        //get cart items
        $items = $cart->getQuote()->getAllItems();

        try {
            $quoteFactory = $objectManager->get('\Magento\Quote\Model\QuoteFactory')->create();
            $quoteFactory->setStore($store);
            $quoteFactory->setCurrency();
            $quoteFactory->assignCustomer($customer);


            $imageHelper = $objectManager->get('\Magento\Catalog\Helper\Image');
            $checksum = [];
            $cartData = [];
            $configurableItems = [];
            $needsShipping = false;
            foreach ($items as $item) {
                $tmp = $item->getProduct()->getTypeInstance(true)->getOrderOptions($item->getProduct());

                // determine if we have simple product to ship
                if ($item->getProductType() == 'simple') {
                    $needsShipping = true;
                }

                $selected_configurable_option = isset($tmp['info_buyRequest']['selected_configurable_option']) && !empty($tmp['info_buyRequest']['selected_configurable_option']) ? $tmp['info_buyRequest']['selected_configurable_option'] : '';

                //skip simple/virtual item if it's already added with configurable item
                if (in_array($selected_configurable_option, $configurableItems)) {
                    continue;
                }
                if ($item->getProductType() == 'configurable') {
                    $configurableItems[] = $selected_configurable_option;
                }


                $params = [
                    'product'                      => $item->getProductId(),
                    'qty'                          => $item->getQty(),
                    'options'                      => isset($tmp['info_buyRequest']['options']) ? $tmp['info_buyRequest']['options'] : '',
                    'super_attribute'              => isset($tmp['info_buyRequest']['super_attribute']) ? $tmp['info_buyRequest']['super_attribute'] : '',
                    'selected_configurable_option' => $selected_configurable_option
                ];

                $objParam = new \Magento\Framework\DataObject($params);

                $product = $objectManager->get('\Magento\Catalog\Model\ProductRepository')->getById($item->getProductId(), false, $websiteId, true);// get product by product id
                $quoteFactory->addProduct($product, $objParam);  // add products to quote

                $checksum[] = [
                    'id'       => (int)$item->getProductId(),
                    'price'    => round($item->getPrice(), 2),
                    'quantity' => (int)$item->getQty(),
                ];
                $cartData[] =
                    [
                        'id'         => $item->getProductId(),
                        'title'      => $item->getName(),
                        'img'        => $imageHelper->init($product, 'product_base_image')->getUrl(), //"https://shopiqa.com/wp-content/uploads/2019/01/buy2bee.eu_330917_0-680x680.jpg", // 
                        'price'      => round($item->getPrice(), 2),
                        'quantity'   => $item->getQty(),
                        'attributes' => $tmp
                    ];
            }
            $storeSecret = $config->getValue('payment/paylix_pay/secret');

            $chechsumStr = md5(json_encode($checksum) . $storeSecret);


            /*
             * Set Address to quote
             */
            $quoteFactory->getBillingAddress()->addData($address);

            /*
             * Collect Rates and Set Shipping & Payment Method
             */
            if ($needsShipping) {
                $quoteFactory->getShippingAddress()->addData($address);

                $shippingAddress = $quoteFactory->getShippingAddress();
                $shippingAddress->setCollectShippingRates(true)
                    ->collectShippingRates()
                    ->setShippingMethod('flatrate_flatrate'); //shipping method
            }

            $quoteFactory->setPaymentMethod('paylix_pay'); //payment method
            $quoteFactory->setInventoryProcessed(false);
            $quoteFactory->save();

            /*
             * Set Sales Order Payment
             */
            $quoteFactory->getPayment()->importData(['method' => 'paylix_pay']);

            /*
             * Collect Totals & Save Quote
             */
            $quoteFactory->collectTotals()->save();

            /*
             * Create Order From Quote
             */
            $order = $objectManager->get('\Magento\Quote\Model\QuoteManagement')->submit($quoteFactory);
            $orderId = $order->getRealOrderId();
            if ($orderId) {
                $order->setEmailSent(0);
                //clear cart data
                $objectManager->get('\Magento\Checkout\Model\Session')->clearQuote()->clearStorage()->restoreQuote();
                //$cart->truncate();
                //$cart->save(); ///magento 2 error
            } else {
                return $resultJson->setData(['success' => false, 'message' => 'Could not creat Order']);
            }

        } catch (Exception $e) {
            echo $e->getMessage();
        }

        $remote = $objectManager->get('Magento\Framework\HTTP\PhpEnvironment\RemoteAddress');
        $ip = $remote->getRemoteAddress();
        $returnData = [
            'cart'          => $cartData,
            'currency'      => "USD",
            'checksum'      => $chechsumStr,
            'shipmentType'  => "regular",
            'shipmentPrice' => "20.5",
            'orderId'       => $orderId,
            'ip'            => $ip ? $ip : '',
            'statusUrl'     => $storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB) . 'paylix/order/status'
        ];


        return $resultJson->setData($returnData);
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /** * @inheritDoc */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    function get_string_between($string, $start, $end)
    {
        $string = ' ' . $string;
        $ini = strpos($string, $start);
        if ($ini == 0) return false;
        $ini += strlen($start);
        $len = strpos($string, $end, $ini) - $ini;
        return substr($string, $ini, $len);
    }
}


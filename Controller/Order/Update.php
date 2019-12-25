<?php

namespace Paylix\Pay\Controller\Order;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;

use Magento\Sales\Model\Order;

class Update extends Action implements \Magento\Framework\App\CsrfAwareActionInterface
{


    public function __construct(
        Context $context
    )
    {
        return parent::__construct($context);
    }

    public function execute()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        $JsonFactory = $objectManager->get('\Magento\Framework\Controller\Result\JsonFactory');
        $resultJson = $JsonFactory->create();
        $request = $objectManager->get('Magento\Framework\App\RequestInterface');
        $config = $objectManager->get('\Magento\Framework\App\Config\ScopeConfigInterface');

        $req = json_decode($request->getContent(), true);

        $order = $objectManager->create('\Magento\Sales\Model\Order')->load($req['orderId']);
        if ($order->getId()) {

            if ($req['status'] == 'processing') {
                $orderState = Order::STATE_PROCESSING;
                $order->setState($orderState)->setStatus(Order::STATE_PROCESSING);


                // update order customer info
                $order->setCustomerFirstname($req['firstname']);
                $order->setCustomerLastname($req['lastname']);
                if (!empty($req['email']) && $req['email'] != 'null') {
                    $order->setCustomerEmail($req['email']);
                }
                if (!$order->getIsVirtual()) {
                    if ($req['shipping'] && ($req['shipping'] == 'regular' || $req['shipping'] == 'express')) {
                        $shipping = $config->getValue('payment/paylix_pay/shipping_' . $req['shipping']);
                        $order->setShippingMethod($shipping);
                    }
                }
                //save order here to avoid address overwrite. $order->setShippingAddress($address); doesn't update order address.
                $order->save();

                //check if order is virtual / if virtual it doesn't need shipping address
                if (!$order->getIsVirtual()) {

                    //check if country has regions
                    //currently paylix doesn't support regions/states so set it empty
                    //$country = $objectManager->get('\Magento\Directory\Model\Country')->loadByCode($req['countryId'])->getRegions()->loadData()->toOptionArray(false);


                    // shipping address
                    $address = $objectManager->get('\Magento\Sales\Model\Order\Address')->load($order->getShippingAddress()->getId());
                    $address->setFirstname($req['firstname']);
                    $address->setLastname($req['lastname']);
                    $address->setCountryId($req['countryId']);
                    $address->setCity($req['city']);
                    $address->setStreet($req['street']);
                    $address->setRegion('');
                    $address->setRegionId(0);
                    $address->setPostcode($req['postcode']);
                    if (!empty($req['email']) && $req['email'] != 'null') {
                        $address->setEmail($req['email']);
                    }
                    $address->setTelephone($req['telephone']);

                    $address->save();

                    //billing address
                    $address = $objectManager->get('\Magento\Sales\Model\Order\Address')->load($order->getBillingAddress()->getId());
                    $address->setFirstname($req['firstname']);
                    $address->setLastname($req['lastname']);
                    $address->setCountryId($req['countryId']);
                    $address->setCity($req['city']);
                    $address->setStreet($req['street']);
                    $address->setRegion('');
                    $address->setRegionId(0);
                    $address->setPostcode($req['postcode']);
                    if (!empty($req['email']) && $req['email'] != 'null') {
                        $address->setEmail($req['email']);
                    }
                    $address->setTelephone($req['telephone']);

                    $address->save();

                }
            } elseif ($req['status'] == 'cancelled') {
                $orderState = Order::STATE_CANCELED;
                $order->setState($orderState)->setStatus(Order::STATE_CANCELED);
                $order->save();
            }

        } else {
            return $resultJson->setData(['success' => false, 'message' => 'order doesn not exist']);
        }


        return $resultJson->setData([]);
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /** * @inheritDoc */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $request = $objectManager->get('Magento\Framework\App\RequestInterface');

        $req = json_decode($request->getContent(), true);

        $config = $objectManager->get('\Magento\Framework\App\Config\ScopeConfigInterface');
        $secret = $config->getValue('payment/paylix_pay/secret');

        if ($req['secret'] == $secret) {
            return true;
        }
        return false;

    }
}


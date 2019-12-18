<?php namespace Paylix\Pay\Observer;

use Magento\Framework\Event\ObserverInterface;

class OrderStatus implements ObserverInterface
{

    protected $connector;

    public function __construct()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $order = $observer->getEvent()->getOrder();

        $payment = $order->getPayment();


        if ($payment->getMethod() == 'paylix_pay') {
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $config = $objectManager->get('\Magento\Framework\App\Config\ScopeConfigInterface');
            $conf = $config->getValue('payment/paylix_pay');

            $orderStatus =  strtolower($order->getStatus());
            $paylixStatus = array_search($orderStatus, $conf);
            if($paylixStatus){
                $paylixStatus = str_replace('_', '-', $paylixStatus); // replace _ with - for on-hold status

                $data = array("orderId" => $order->getId(), "orderStatus" =>$paylixStatus, "merchantKey" => $conf['secret']);

                $data_string = json_encode($data);

                $ch = curl_init('https://apiv2.paylix.net/orders/update_order');
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                        'Content-Type: application/json',
                        'Content-Length: ' . strlen($data_string))
                );

                $result = curl_exec($ch);
            }


        }

        $objData = serialize($payment->getMethod().' '.$paylixStatus.' status:'.$order->getStatus().' state:'.$order->getState());
        $filePath = 'C:\laragon\tmp\log.txt';
        if (is_writable($filePath)) {
            $fp = fopen($filePath, "w");
            fwrite($fp, $objData);
            fclose($fp);
        }

    }
}

<?php

namespace Paylix\Pay\Model;

/**
* Pay In Store payment method model
*/
class PaymentMethod extends \Magento\Payment\Model\Method\AbstractMethod
{

/**
* Payment code
*
* @var string
*/
protected $_code = 'paylix_pay';

    public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        //todo add functionality later
    }

    public function authorize(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        //todo add functionality later
    }
}

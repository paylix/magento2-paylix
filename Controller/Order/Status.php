<?php

namespace Paylix\Pay\Controller\Order;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;

use Magento\Sales\Model\Order;

class Status extends Action implements \Magento\Framework\App\CsrfAwareActionInterface
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
        $status = 'pending';

        $req = json_decode($request->getContent(), true);

        if ($req['status'] == 'check') {
            $order = $objectManager->create('\Magento\Sales\Model\Order')->load($req['orderId']);
            $status = $order->getStatus();
            if ($status == 'processing') {
                return $resultJson->setData(['status' => 'paid']);
            }
        }

        return $resultJson->setData(['status' => $status]);
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
}


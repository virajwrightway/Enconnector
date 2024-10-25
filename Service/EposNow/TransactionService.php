<?php

/*********************************************************************************
 *
 * CONFIDENTIAL
 * __________________
 *
 *  Copyright (C) WrightWay Digital, Ltd.
 *  All Rights Reserved.
 *
 * NOTICE:  All information contained herein is, and remains
 * the property of WrightWay Digital Ltd and its suppliers, if any.
 * The intellectual and technical concepts contained herein are proprietary
 * to WrightWay Digital Ltd and its suppliers and may be covered by UK and Foreign Patents,
 * or patents in process, and are protected by trade secret or copyright law.
 * Dissemination of this information or reproduction of this material
 * is strictly forbidden unless prior written permission is obtained
 * from WrightWay Digital Ltd.
 *
 * @author WrightWay Digital, Ltd.
 * @copyright 2023 WrightWay Digital, Ltd.
 * @license LICENSE.txt
 ********************************************************************************/

namespace Wrightwaydigital\Enconnector\Service\EposNow;

use Wrightwaydigital\Enconnector\Service\ProductService;
use Wrightwaydigital\Enconnector\Service\RestService;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderRepository;
use Wrightwaydigital\Enconnector\Service\Configuration\ConfigurationService;

class TransactionService
{
    /**
     * @var RestService
     */
    private $restService;
    /**
     * @var ProductService
     */
    private $productService;
    /**
     * @var StockService
     */
    private $stockService;
    /**
     * @var OrderRepository
     */
    private $orderRepository;
    private $apiBaseUrl = 'https://api.eposnowhq.com';
    private $configurationService;
    /**
     * @var TenderService
     */
    private $tenderService;
    public function __construct(
        RestService          $restService,
        ProductService       $productService,
        StockService         $stockService,
        OrderRepository      $orderRepository,
        TenderService        $tenderService,
        ConfigurationService $configurationService
    )
    {
        $this->restService = $restService;
        $this->productService = $productService;
        $this->stockService = $stockService;
        $this->orderRepository = $orderRepository;
        $this->tenderService = $tenderService;
        $this->configurationService = $configurationService;
    }
    public function create(Order $order)
    {
        $items = array();
        foreach ($order->getItems() as $item) {
            try {
                $eposnow_id = $this->productService->getEposNowIdById($item->getProductId());
            } catch (\Exception $e) {
                return false;
            }
            $tax = array();
            $tax['Percentage'] = $item->getTaxPercent();
            $tax['name'] = $tax['Percentage'] . '%';
            if ($tax['Percentage'] == 0) {
                $tax['name'] = 'No Tax';
            }
            $taxAr[] = $tax;
            $quantitiy = $item->getData('qty_ordered');
            $i = array(
                "ProductId" => $eposnow_id,
                "Quantity" => (int)$quantitiy,
                "UnitPrice" => $item->getPrice(),
                "TaxRates" => $taxAr
            );
            $items[] = $i;
        }
        $totalAmmount = $order->getBaseGrandTotal();
        $tender = array();
        $tender[] = $this->getPaymentMethod($order, $totalAmmount);
        $request = array(
            "DateTime" => $order->getUpdatedAt(),
            "StatusId" => 1,
            "TotalAmount" => (float)$order->getGrandTotal() - $order->getShippingAmount(),
            "TransactionItems" => $items,
            "AdjustStock" => true,
            "Tenders" => $tender
        );
        $response = $this->restService->post($this->apiBaseUrl . '/api/v4/transaction', $request);
        if ($response) {
            $order->setEposnowId($response->Id);
            $order->getResource()->saveAttribute($order, 'eposnow_id');
            return $response->Id;
        } else {
            return false;
        }
    }
    public function place(Order $order)
    {
        $eposOrder = $this->restService->get($this->apiBaseUrl . '/api/v4/transaction/' . $order->getEposnowId());
        if ($eposOrder) {
            if ($order->getState() == 'processing' && $eposOrder->StatusId != 8) {
                $request = array(
                    "Id" => $order->getEposnowId(),
                    "StatusId" => 8,
                    "TransactionItems" => $eposOrder->TransactionItems,
                    "AdjustStock" => true
                );
                $result = $this->restService->put($this->apiBaseUrl . '/api/v4/transaction/' . $order->getEposnowId(), $request);
                return $result;
            }
            if ($order->getState() == 'complete' && $eposOrder->StatusId != 1) {
                $request = $eposOrder;
                $request->Tenders = array(array(
                    "TenderTypeId" => $this->tenderService->getCardTenderId(),
                    "Amount" => $order->getGrandTotal() - $order->getShippingAmount(),
                    "ChangeGiven" => 0,
                ));
                $request->StatusId = 1;
                $request->DateTime = $order->getUpdatedAt();
                $request->AdjustStock = true;
                $result = $this->restService->put($this->apiBaseUrl . '/api/v4/transaction/' . $order->getEposnowId(), $request);
                return $result;
            }
            if ($order->getState() == 'closed') {
                $request = $eposOrder;
                $request->StatusId = 1;
                $items = $request->TransactionItems;
                foreach ($items as $item) {
                    $refundItem = clone $item;
                    $refundItem->UnitPrice = 0 - $item->UnitPrice;
                    array_push($request->TransactionItems, $refundItem);
                }
                $refund = clone $request->Tenders[0];
                $refund->Amount = 0 - $refund->Amount;
                array_push($request->Tenders, $refund);
                $result = $this->restService->put($this->apiBaseUrl . '/api/v4/transaction/' . $order->getEposnowId(), $request);
                return $result;
            }
        }
    }
    public function delete(Order $order)
    {
        $this->restService->delete($this->apiBaseUrl . '/api/v4/transaction/' . $order->getEposnowId(), null);
    }
    public function deleteById($id)
    {
        $this->restService->delete($this->apiBaseUrl . '/api/v4/transaction/' . $id, null);
    }
    public function get($page)
    {
        $response = $this->restService->get($this->apiBaseUrl . '/api/v4/Transaction?page=' . $page);
        return $response;
    }
    public function getPaymentMethod($order, $totalAmount)
    {
        $payment = $order->getPayment();
        $method = $payment->getMethodInstance();
        $methodTitle = $method->getTitle();
        $method = array();
        $method['TenderTypeId'] = $this->configurationService->getConfiguration()->getDefaultTender();
        $method['Amount'] = (float)$order->getGrandTotal() - $order->getShippingAmount();
        return $method;
    }
}

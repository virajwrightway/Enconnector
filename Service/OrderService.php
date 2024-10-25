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

namespace Wrightwaydigital\Enconnector\Service;

use Magento\Catalog\Model\Product;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\ProductRepository;
use Magento\Catalog\Model\ProductFactory;
use Wrightwaydigital\Enconnector\Service\EposNow\EposProductService;
use Magento\Framework\Registry;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Wrightwaydigital\Enconnector\Service\CategoryService;
use Wrightwaydigital\Enconnector\Service\ProductService;
use Wrightwaydigital\Enconnector\Service\EposNow\StockService;
use Wrightwaydigital\Enconnector\Service\EposNow\EposCustomerService;
use Wrightwaydigital\Enconnector\Service\EposNow\TransactionService;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Api\CustomerRepositoryInterface;

//use Wrightwaydigital\Enconnector\Service\TaxService;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Wrightwaydigital\Enconnector\Service\Configuration\ConfigurationService;
use Wrightwaydigital\Enconnector\Logger\Logger;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\QuoteManagement;

//use Magento\Sales\Model\Order\OrderFactory;
//use Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory;
//use Magento\Sales\Api\OrderRepositoryInterface;
class OrderService
{
    private $productRepository;
    private $productFactory;
    private $product;
    private $eposProductService;
    private $registry;
    private $collectionFactory;
    private $categoryService;
    private $productResourceModel;
    private $stockService;
    private $stockRegistry;
    private $configurationService;
    private $eposnowdataFactory;
    private $taxService;
    private $transactionService;
    private $storeManager;
    private $customerFactory;
    private $customerRepository;
    private $quoteFactory;
    private $quoteManagement;
    private $shippingRate;
    private $customerService;
    private $productService;
    private $orderCollection;
    public function __construct(
        ProductRepository                                          $productRepository,
        ProductFactory                                             $productFactory,
        EposProductService                                         $eposProductService,
        Registry                                                   $registry,
        CollectionFactory                                          $collectionFactory,
        CategoryService                                            $categoryService,
        \Magento\Catalog\Model\ResourceModel\Product               $productResourceModel,
        StockService                                               $stockService,
        StockRegistryInterface                                     $stockRegistry,
        ConfigurationService                                       $configurationService,
        \Wrightwaydigital\Enconnector\Model\EposnowdataFactory                  $eposnowdataFactory,
        TaxService                                                 $taxService,
        TransactionService                                         $transactionService,
        CustomerFactory                                            $customerFactory,
        CustomerRepositoryInterface                                $customerRepository,
        StoreManagerInterface                                      $storeManager,
        QuoteFactory                                               $quoteFactory,
        QuoteManagement                                            $quoteManagement,
        Product                                                    $product,
        EposCustomerService                                        $customerService,
        \Magento\Quote\Model\Quote\Address\Rate                    $shippingRate,
        ProductService                                             $productService,
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory
    )
    {
        $this->productRepository = $productRepository;
        $this->productFactory = $productFactory;
        $this->eposProductService = $eposProductService;
        $this->registry = $registry;
        $this->collectionFactory = $collectionFactory;
        $this->categoryService = $categoryService;
        $this->productResourceModel = $productResourceModel;
        $this->stockService = $stockService;
        $this->stockRegistry = $stockRegistry;
        $this->configurationService = $configurationService;
        $this->eposnowdataFactory = $eposnowdataFactory;
        $this->taxService = $taxService;
        $this->transactionService = $transactionService;
        $this->storeManager = $storeManager;
        $this->customerFactory = $customerFactory;
        $this->customerRepository = $customerRepository;
        $this->quoteFactory = $quoteFactory;
        $this->quoteManagement = $quoteManagement;
        $this->product = $product;
        $this->shippingRate = $shippingRate;
        $this->customerService = $customerService;
        $this->productService = $productService;
        $this->orderCollection = $orderCollectionFactory;
    }
    public function getEposNowId(Product $product)
    {
        return $product->getEposnowId();
    }
    public function getEposNowIdById(int $productId)
    {
        return $this->productRepository->getById($productId)->getEposnowId();
    }
    public function getIdByEposnowId($eposnowId)
    {
        return $this->productFactory->create()->getCollection()->addAttributeToSelect('eposnow_id')->addAttributeToFilter('eposnow_id', $eposnowId)->getFirstItem()->getId();
    }
    public function getOrderByEposnowId($eposnowId)
    {
        $order = $this->orderCollection
            ->create()
            ->addAttributeToFilter('eposnow_id', $eposnowId)
            ->getFirstItem();
        return $order->getData();
    }
    public function createOrder($EposnowOrder)
    {
        if (!$orderData = $this->magentoOrderFormat($EposnowOrder)) {
            return false;
        }
        $store = $this->storeManager->getStore();
        $websiteId = $this->storeManager->getStore()->getWebsiteId();
        $customer = $this->customerFactory->create();
        $customer->setWebsiteId($websiteId);
        $customer->loadByEmail($orderData['email']);// load customet by email address
        if (!$customer->getEntityId()) {
            //If not avilable then create this customer
            $customer->setWebsiteId($websiteId)
                ->setStore($store)
                ->setFirstname($orderData['shipping_address']['firstname'])
                ->setLastname($orderData['shipping_address']['lastname'])
                ->setEmail($orderData['email'])
                ->setPassword($orderData['email']);
            $customer->save();
        }
        $quote = $this->quoteFactory->create(); //Create object of quote
        $quote->setStore($store); //set store for which you create quote
        // if you have allready buyer id then you can load customer directly
        $customer = $this->customerRepository->getById($customer->getEntityId());
        $quote->setCurrency();
        $quote->assignCustomer($customer); //Assign quote to customer
        //add items in quote
        if (isset($orderData['items']) && !empty($orderData['items'])) {
            foreach ($orderData['items'] as $item) {
                try {
                    $product = $this->productFactory->create()->load($item['product_id']);
                    $product->setPrice($item['price']);
                    if (isset($item['qty'])) {
                        $quote->addProduct(
                            $product,
                            intval($item['qty'])
                        );
                    }
                } catch (\Magento\Framework\Exception\LocalizedException $e) {
                    throw new \Magento\Framework\Exception\SessionException(
                        new \Magento\Framework\Phrase(
                            'Item could not be found'
                        ),
                        $e
                    );
                }
            }
        }
        //Set Address to quote
        $quote->getBillingAddress()->addData($orderData['shipping_address']);
        $quote->getShippingAddress()->addData($orderData['shipping_address']);
        // Collect Rates and Set Shipping & Payment Method
        $this->shippingRate
            ->setCode('s_method_freeshipping_freeshipping')
            ->getPrice(1);
        $shippingAddress = $quote->getShippingAddress();
        $shippingAddress->setCollectShippingRates(true)
            ->collectShippingRates()
            ->setShippingMethod('s_method_freeshipping_freeshipping'); //shipping method
        $quote->getShippingAddress()->addShippingRate($this->shippingRate);
        $quote->setPaymentMethod('checkmo'); //payment method
        $quote->setInventoryProcessed(false); //not effetc inventory
        $quote->save(); //Now Save quote and your quote is ready
        // Set Sales Order Payment
        $quote->getPayment()->importData(['method' => 'checkmo']);
        // Collect Totals & Save Quote
        $quote->collectTotals()->save();
        // Create Order From Quote
        try {
            $order = $this->quoteManagement->submit($quote);
            if (isset($order)) {
                $eposnow_id = $EposnowOrder->Id;
                $order->setEposnowId($eposnow_id);
                $order->save();
                $increment_id = $order->getRealOrderId();
                if ($order->getEntityId()) {
                    $result['order_id'] = $order->getRealOrderId();
                } else {
                    return false;
                }
            }
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            throw new \Magento\Framework\Exception\SessionException(
                new \Magento\Framework\Phrase(
                    'Quote creation failed'
                ),
                $e
            );
        }
        return false;
    }
    public function processOrder($order)
    {
    }
    public function magentoOrderFormat($order)
    {
        if (!empty($order->TransactionItems) && isset($order->TransactionItems)) {
            $orderItems = $this->formatOrderItems($order->TransactionItems);

        } else {
            return false;
        }
        $customer = $this->customerService->getCustomer($order->CustomerId);
        if (empty($customer)) {
            $formattedOrder = [
                'currency_id' => 'USD',
                'email' => 'guest@site.lk',
                'shipping_address' => [
                    'firstname' => 'Pos order',
                    'lastname' => 'Pos order',
                    'street' => 'Pos order',
                    'city' => 'Pos order',
                    'country_id' => 'GB',
                    'region' => 2,
                    'postcode' => '43244',
                    'telephone' => '0000000000',
                    'fax' => '32423',
                    'save_in_address_book' => 1
                ],
                'items' => $orderItems
            ];
            return $formattedOrder;
        } else {
            $formattedOrder = [
                'currency_id' => 'USD',
                'email' => $customer->EmailAddress, //buyer email id
                'shipping_address' => [
                    'firstname' => $customer->Forename, //address Details
                    'lastname' => $customer->Surname,
                    'street' => $customer->CustomerAddress->AddressLine1 . ',' . $customer->CustomerAddress->AddressLine2,
                    'city' => $customer->CustomerAddress->Town,
                    'country_id' => $customer->CustomerAddress->County,
                    'region' => 2,
                    'postcode' => '43244',
                    'telephone' => $customer->ContactNumber,
                    'fax' => '32423',
                    'save_in_address_book' => 1
                ],
                'items' => $orderItems
            ];
            return $formattedOrder;
        }
    }
    public function eposNowFormat($order)
    {
    }
    public function formatOrderItems($orderItems)
    {
        $orderItemsFormatted = [];
        $syncStocks = $this->configurationService->getConfiguration()->getSyncStock();
        foreach ($orderItems as $key => $value) {
            if ($productId = $this->productService->getIdByEposnowId($value->ProductId)) {
                $orderItemsFormatted[$key]['product_id'] = (int)$productId;
                if ($syncStocks) {
                    $orderItemsFormatted[$key]['qty'] = $value->Quantity;
                }
                $orderItemsFormatted[$key]['price'] = $value->UnitPrice;
            }
        }
        if (empty($orderItemsFormatted)) {
            return false;
        }
        return $orderItemsFormatted;
    }
    public function getMagentoOrdersByPage($page)
    {
        $orderCollecion = $this->orderCollection
            ->create()
            ->addFieldToSelect('*')
            ->getItems();
        return $orderCollecion;
    }
    public function getMagentoOrders($page, $lastId, $pageSize = 10)
    {
        $orderCollecion = $this->orderCollection->create();
        $orderCollecion->setPageSize($pageSize);
        $orderCollecion->setCurPage($page);
        $orderCollecion->addFieldToFilter('entity_id', ['gt' => $lastId]);
        $orderCollecion->addAttributeToSelect('*');
        $numberOfPages = $orderCollecion->getLastPageNumber();
        if ($numberOfPages >= $page) {
            $orderCollecion->setCurPage($page);
            $orders = $orderCollecion->load();
            return $orders;
        } else {
            return [];
        }
    }
    public function OrderExists($orderEposnowId)
    {
        foreach ($this->getMagentoOrders() as $order) {
            if ($this->getEposnowIdById($order->getId()) == $orderEposnowId) {
                return true;
            }
        }
        return false;
    }
}

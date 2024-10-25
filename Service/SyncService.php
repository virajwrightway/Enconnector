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

use Wrightwaydigital\Enconnector\Model\Configuration;
use Wrightwaydigital\Enconnector\Logger\Logger;
use Wrightwaydigital\Enconnector\Service\EposNow\EposCategoryService;
use Wrightwaydigital\Enconnector\Service\EposNow\EposProductService;
use Wrightwaydigital\Enconnector\Service\EposNow\StockService;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\Registry;
use Wrightwaydigital\Enconnector\Service\Configuration\ConfigurationService;
use Magento\Catalog\Model\Product\Action;
use Wrightwaydigital\Enconnector\Model\ConfigurationFactory;
use Magento\Framework\Api\Search\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrder;
use Wrightwaydigital\Enconnector\Service\EposNow\TransactionService;
use Wrightwaydigital\Enconnector\Service\OrderService;
use Magento\CatalogInventory\Model\Stock\StockItemRepository;
use stdClass;

class SyncService
{
    /**
     * @var EposCategoryService
     */
    private $eposCategoryService;
    /**
     * @var EposProductService
     */
    private $eposProductService;
    /**
     * @var CategoryService
     */
    private $categoryService;
    /**
     * @var ProductService
     */
    private $productService;
    /**
     * @var StockService
     */
    private $stockService;
    /**
     * @var StockRegistryInterface
     */
    private $stockRegistry;
    /**
     * @var Registry
     */
    private $registry;
    protected $messageManager;
    protected $stockItemRepository;
    /**
     * @var Logger
     */
    private $logger;
    protected $productResource;
    protected $productAction;
    protected $_product;
    private $configurationFactory;
    private $configurationService;
    private $eposnowdataFactory;
    protected $searchCriteriaBuilder;
    private $taxService;
    private $transactionService;
    private $orderService;
    public $WD_SCRIPT_START;
    public $WD_LOOP_END;
    private $timeout_code = 403;
    public function __construct(
        EposCategoryService                          $eposCategoryService,
        EposProductService                           $eposProductService,
        CategoryService                              $categoryService,
        ProductService                               $productService,
        StockService                                 $stockService,
        StockRegistryInterface                       $stockRegistry,
        Registry                                     $registry,
        ConfigurationService                         $configurationService,
        Logger                                       $logger,
        \Magento\Catalog\Model\ResourceModel\Product $productResource,
        \Magento\Catalog\Model\Product               $product,
        Action                                       $productAction,
        ConfigurationFactory                         $configurationFactory,
        \Wrightwaydigital\Enconnector\Model\EposnowdataFactory    $eposnowdataFactory,
        SearchCriteriaBuilder                        $searchCriteriaBuilder,
        TaxService                                   $taxService,
        TransactionService                           $transactionService,
        OrderService                                 $orderService,
        \Magento\Framework\Message\ManagerInterface  $messageManager,
        StockItemRepository                          $stockItemRepository
    )
    {
        $this->eposCategoryService = $eposCategoryService;
        $this->eposProductService = $eposProductService;
        $this->categoryService = $categoryService;
        $this->productService = $productService;
        $this->stockService = $stockService;
        $this->stockRegistry = $stockRegistry;
        $this->registry = $registry;
        $this->logger = $logger;
        $this->configurationService = $configurationService;
        $this->productResource = $productResource;
        $this->_product = $product;
        $this->productAction = $productAction;
        $this->configurationFactory = $configurationFactory;
        $this->eposnowdataFactory = $eposnowdataFactory;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->taxService = $taxService;
        $this->transactionService = $transactionService;
        $this->orderService = $orderService;
        $this->messageManager = $messageManager;
        $this->stockItemRepository = $stockItemRepository;
        $this->WD_SCRIPT_START = time();
        $this->WD_LOOP_END = $this->calculateScriptEnd();
    }
    private function categoryExists($eposCategories, $magentoCategory)
    {
        $magentoCategoryData = $magentoCategory->getData();
        if (isset($magentoCategoryData['eposnow_id'])) {
            $eposId = $magentoCategoryData['eposnow_id'];
            $eposName = $magentoCategoryData['name'];
            foreach ($eposCategories as $category) {
                $CategoryId = $category->Id;
                $categoryName = $category->Name;
                if (($category->Id == $eposId && $category->Name == $eposName || $this->categoryExists($category->Children, $magentoCategory))) {
                    return true;
                }
            }
        }
        return false;
    }
    private function productExists($eposProducts, $magentoProduct)
    {
        foreach ($eposProducts as $product) {
            if ($product->Sku == $magentoProduct->getSku()) {
                return true;
            }
        }
        return false;
    }
    private function calculateScriptEnd()
    {
        $loopTimeout = $this->configurationService->getConfiguration()->getLoopTimeout();
        return $this->WD_LOOP_END = strtotime('+' . $loopTimeout . ' seconds', $this->WD_SCRIPT_START);
    }
    public function shouldEnd()
    {
        $currentTime = time();
        $is_cron = $this->configurationService->getRawConfiguration('is_cron');
        if (($currentTime > $this->WD_LOOP_END) && !$is_cron) {
            return true;
        } else {
            return false;
        }
    }
    public function createMagentoCategories($lastSync)
    {
        $response = [];
        $lastPageSynced = (int)$lastSync['last_page'];
        $syncedCount = (int)$lastSync['synced_count'];
        $lastPageFetched = (int)$lastSync['last_fetched'];
        $nextFetch = ++$lastPageFetched;
        $lastId = $lastSync['last_id'];
        $syncCategories = $this->configurationService->getConfiguration()->getSyncCategories();
        if ($syncCategories) {
            $data = $this->eposCategoryService->getCategoryByPage($nextFetch);
            $eposCategories = $this->sliceByLastId($data, $lastId, 'Id');
            $this->configurationService->updateSyncProcess(['type' => 'category', 'synced_count' => 0, 'last_fetched' => 0, 'total_count' => count($eposCategories), 'direction' => 'magento']);
            if ($eposCategories != [[]]) {
                $count = 0;
                foreach ($eposCategories as $dir) {
                    if ($this->categoryService->walkCategorySingle($dir, 2)) {
                        $count++;
                    }
                    if ($this->shouldEnd()) {
                        $this->configurationService->updateSyncProcess(['type' => 'category', 'synced_count' => $syncedCount + $count, 'last_id' => $dir->Id, 'direction' => 'magento']);
                        return true;
                    }
                }
                $this->configurationService->updateSyncProcess(['type' => 'category', 'direction' => 'magento', 'synced_count' => $syncedCount + $count, 'last_id' => 0]);
            }
            if ($this->configurationService->getConfiguration()->getData('api_locked')) {
                return $this->timeout_code;
            }
            $this->configurationService->updateSyncProcess(['type' => 'category', 'status' => 1, 'direction' => 'magento']);
            $response['status'] = true;
        } else {
            $response['status'] = true;
        }
        return $response;
    }
    public function createEposCategoriesOld($lastSync)
    {
        $lastPageSynced = (int)$lastSync['last_page'];
        if ($lastPageSynced == 0) {
            $nextPage = $lastPageSynced;
        } else {
            $nextPage = $lastPageSynced + 1;
        }
        $lastId = $lastSync['last_id'];
        $lastCount = $lastSync['synced_count'];
        $syncCategories = $this->configurationService->getConfiguration()->getSyncCategories();
        if ($syncCategories) {
            $eposCategories = $this->eposCategoryService->getCategory();
            $magentoCategories = $this->categoryService->getCategories();
            $categories = [];
            $put = [];
            $response = [];
            $this->updateSyncCount('category', 0);
            foreach ($magentoCategories as $category) {
                if ($category->getParentId() > 1) {
                    if (!$this->categoryExists($eposCategories, $category)) {
                        $data = [
                            "ParentId" => $this->categoryService->getEposnowIdById($category->getParentCategory()->getId()),
                            "Name" => $category->getName()
                        ];
                        array_push($categories, $data);
                    } else {
                        $putData = [
                            "Id" => $category->getEposnowId(),
                            "ParentId" => $this->categoryService->getEposnowIdById($category->getParentCategory()->getId()),
                            "Name" => $category->getName()
                        ];
                        array_push($put, $putData);
                    }
                }
            }
            if (count($categories) > 0) {
                $result = $this->eposCategoryService->postCategory($categories);
                $this->saveEposnowId($result);
                $this->updateEposCategories();
                $this->updateSyncCount('category', count($categories));
                $response['status'] = 'done';
            }
            if (count($put) > 0) {
                $result = $this->eposCategoryService->putCategory($put);
                $this->updateEposCategories();
                $this->updateSyncCount('category', count($put));
                $response['status'] = 'done';
            }
        } else {
            $response['status'] = false;
        }
        return $response;
    }
    public function createEposCategories($lastSync)
    {
        $lastPageSynced = (int)$lastSync['last_page'];
        $lastId = $lastSync['last_id'];
        $lastCount = $lastSync['synced_count'];
        if ($lastPageSynced == 0 && $lastCount == 0) {
            $nextPage = $lastPageSynced;
            $totalCount = $this->categoryService->getAllCategories()->getSize();
            $this->configurationService->updateSyncProcess(['type' => 'category', 'direction' => 'eposnow', 'total_count' => $totalCount]);
        } else {
            $nextPage = $lastPageSynced + 1;
        }
        $syncCategories = $this->configurationService->getConfiguration()->getSyncCategories();
        if ($syncCategories) {
            if ($magentoCategories = $this->categoryService->getCategories($nextPage, 50)) {
                $categories = $this->formatEpownowCategories($magentoCategories);
                $response = $this->eposCategoryService->postCategories($categories);
                if (isset($response) && is_array($response)) {
                    $this->saveEposnowId($response);
                }
                if ($this->configurationService->getConfiguration()->getData('api_locked')) {
                    $this->configurationService->updateSyncProcess(['type' => 'category', 'direction' => 'eposnow', 'synced_count' => $lastCount + count($response), 'last_page' => $lastPageSynced + 1]);
                    return $this->timeout_code;
                }
                $this->configurationService->updateSyncProcess(['type' => 'category', 'direction' => 'eposnow', 'synced_count' => $lastCount + count($response), 'last_page' => $lastPageSynced + 1]);
                return true;
            } else {
                $this->configurationService->updateSyncProcess(['type' => 'category', 'direction' => 'eposnow', "status" => 1]);
                return true;
            }
        } else {
            return true;
        }
    }
    public function formatEpownowCategories($magentoCategories)
    {
        $categories = [];
        $post = [];
        $put = [];
        foreach ($magentoCategories as $category) {
            if ($category->getParentId() > 1) {
                if ($category->getName() != null) {
                    $eposnowId = $category->getEposNowId();
                    if (isset($eposnowId) && $eposnowId > 0) {
                        $putData = [
                            "Id" => $category->getEposnowId(),
                            "ParentId" => $this->categoryService->getEposnowIdById($category->getParentCategory()->getId()),
                            "Name" => $category->getName()
                        ];
                        if (isset($putData)) {
                            array_push($put, $putData);
                        }
                    } else {
                        $data = [
                            "ParentId" => $this->categoryService->getEposnowIdById($category->getParentCategory()->getId()),
                            "Name" => $category->getName()
                        ];
                        if (isset($data)) {
                            array_push($post, $data);
                        }
                    }
                }
            }
        }
        $categories['post'] = $post;
        $categories['put'] = $put;
        return $categories;
    }
    public function updateEposCategories()
    {
        $syncCategories = $this->configurationService->getConfiguration()->getSyncCategories();
        if ($syncCategories) {
            $eposCategories = $this->eposCategoryService->getCategory();
            $magentoCategories = $this->categoryService->getCategories();
            $categories = [];
            $response = [];
            $this->updateSyncCount('category', 0);
            foreach ($magentoCategories as $category) {
                if ($category->getParentId() > 1) {
                    if ($this->categoryExists($eposCategories, $category) && $this->categoryService->getEposnowIdById($category->getParentCategory()->getId())) {
                        $data = [
                            "ParentId" => $this->categoryService->getEposnowIdById($category->getParentCategory()->getId()),
                            "Name" => $category->getName(),
                            "Id" => $category->getEposnowId()
                        ];
                        array_push($categories, $data);
                    }
                }
            }
            if (count($categories) > 0) {
                $result = $this->eposCategoryService->putCategory($categories);
            } else {
                $response['status'] = false;
            }
        } else {
            $response['status'] = false;
        }
        return $response;
    }
    public function saveEposnowId($eposCategories)
    {
        foreach ($eposCategories as $category) {
            $magentoCategory = $this->categoryService->getCategoriesByName($category->Name);
            $this->categoryService->addEposCategoryId($magentoCategory, $category->Id);
        }
    }
    public function createMagentoProductsOld($page, $lastId)
    {
        $response = [];
        $syncProducts = $this->configurationService->getConfiguration()->getSyncProducts();
        if ($syncProducts) {
            $lastPageFetched = $this->configurationService->getConfiguration()->getepos_last_product_page();
            $lastPageSynced = $this->configurationService->getConfiguration()->getproduct_page();
            $totalEposProductPages = $this->getEposProductPages();
            if ($lastPageSynced == 0) {
                $this->setTotalEposProducts();
                $this->updateSyncCount('product', 0);
                $this->truncateEposDataTable();
                $this->updateEposLastFetchedPage(0);
                $this->getEposProductsByPage(1);
                $this->productService->insertColorAttributes();
            } elseif ($totalEposProductPages > $lastPageFetched) {
                $this->getEposProductsByPage($lastPageFetched + 1);
            }
            if ($resultPage = $this->processTempData($lastPageSynced)) {
                return $resultPage;
            } else {
                $response['status'] = 'done';
            }
        } else {
            $response['status'] = false;
        }
        return $response;
    }
    public function createMagentoProducts($lastSync)
    {
        $lastPageSynced = (int)$lastSync['last_page'];
        $lastPageFetched = (int)$lastSync['last_fetched'];
        $lastId = $lastSync['last_id'];
        $lastCount = $lastSync['synced_count'];
        $response = [];
        $syncProducts = $this->configurationService->getConfiguration()->getSyncProducts();
        if ($syncProducts) {
            $nextFetch = $lastPageFetched + 1;
            if ($lastPageSynced == 0) {
                $this->configurationService->updateSyncProcess(['type' => 'product', 'direction' => 'magento', 'total_count' => $this->setTotalEposProducts()]);
            }
            $totalEposProductPages = $this->getEposProductPages();
            if ($totalEposProductPages > $lastPageFetched) {
                $fetched = $this->getEposProductsByPage($nextFetch);
                if ($this->configurationService->getConfiguration()->getData('api_locked')) {
                    return $this->timeout_code;
                }
            }
            if ($this->processTempData($lastPageSynced, $lastId, $lastCount)) {
                return true;
            } else {
                $response['status'] = 'done';
            }
        } else {
            $response['status'] = false;
        }
        return $response;
    }
    public function setProductInitialSync()
    {
        $eposProdCount = $this->setTotalEposProducts();
        $this->configurationService->updateSyncProcess(['type' => 'product', 'synced_count' => 0, 'direction' => 'magento', 'last_fetched' => 0, 'total_count' => $eposProdCount, 'last_page' => 0]);
        $this->truncateEposDataTable();
        $this->getEposProductsByPage(1);
        $this->productService->insertColorAttributes();
    }
    public function getMagentoProductsFormatted($lastId, $currentPage = 0, $type = 'simple')
    {
        $pageSize = 500;
        $magentoProducts = $this->productService->getProductsSliced($lastId, $pageSize, $currentPage, $type);
        $bulkPost = [];
        if (empty($magentoProducts)) {
            return false;
        }
        $taxMatches = $this->taxService->getMatchedTaxes();
        foreach ($magentoProducts as $product) {
            $product = (object)$product;
            $postData = [];
            if (strlen($product->getSku()) > 34) {
                $this->logger->Error('Sku need to be between 0 and 34 characters ,Product Id: ' . $product->getId());
                continue;
            }
            if (($product->getEposnowId()) > 0) {
                $postData['Id'] = $product->getEposnowId();
            }
            foreach ($taxMatches as $key => $value) {
                if (isset($product->getTaxClassId) && ((int)$value['magento_tax_id'] == $product->getTaxClassId)) {
                    $postData['SalePriceTaxGroupId'] = $value['epos_tax_id'];
                    break;
                } else if ($value['magento_tax_id'] == 0) {
                    $postData['SalePriceTaxGroupId'] = $this->configurationService->getConfiguration()->getDefaultEposnowTaxClass();
                }
            }
            $postData['Name'] = $this->productService->sanitizeString($product->getName());
            if (!isset($postData['Name'])) {
                continue;
            }
            $postData['Description'] = $this->productService->sanitizeString($product->getName());
            if (count($product->getCategoryIds()) > 0) {
                $postData['CategoryId'] = $this->categoryService->getEposnowIdById($product->getCategoryIds()[0]);
            }
            $postData['Sku'] = $product->getSku();
            $postData['CostPrice'] = (float)$product->getPrice();
            $postData['SalePrice'] = (float)$product->getPrice();
            $postData['SellOnWeb'] = true;
            $postData['EatOutPrice'] = (float)$product->getPrice();
            if (isset($postData['Id'])) {
                $bulkPost['put'][] = $postData;
            } else {
                $bulkPost['post'][] = $postData;
            }
        }
        if (is_array($bulkPost) && !empty($bulkPost)) {
            return $bulkPost;
        } else {
            return true;
        }
    }
    public function createEposnowChildProducts($currentPage = 0)
    {
        $pageSize = 500;
        $configurableProducts = $this->productService->getProductsByPagination($pageSize, $currentPage, 'configurable');
        $bulkPost = [];
        if (empty($configurableProducts)) {
            return false;
        }
        $taxMatches = $this->taxService->getMatchedTaxes();
        foreach ($configurableProducts as $product) {
            if (!$product->getEposnowId()) {
                if (strlen($product->getSku()) > 34) {
                    $this->logger->Error('Sku need to be between 0 and 34 characters ,Product Id: ' . $product->getId());
                    continue;
                }
                foreach ($taxMatches as $key => $value) {
                    if (isset($product->getTaxClassId) && ((int)$value['magento_tax_id'] == $product->getTaxClassId)) {
                        $postData['SalePriceTaxGroupId'] = $value['epos_tax_id'];
                        break;
                    } else if ($value['magento_tax_id'] == 0) {
                        $postData['SalePriceTaxGroupId'] = $this->configurationService->getConfiguration()->getDefaultEposnowTaxClass();
                    }
                }
                $postData['Name'] = $product->getName();
                $postData['Description'] = $product->getName();
                if (count($product->getCategoryIds()) > 0) {
                    $postData['CategoryId'] = $this->categoryService->getEposnowIdById($product->getCategoryIds()[0]);
                }
                $postData['Sku'] = $product->getSku();
                $postData['CostPrice'] = (float)$product->getPrice();
                $postData['SalePrice'] = (float)$product->getPrice();
                $postData['SellOnWeb'] = true;
                $postData['EatOutPrice'] = (float)$product->getPrice();
                $bulkPost[] = $postData;
            }
        }
        if (is_array($bulkPost) && !empty($bulkPost)) {
            return $bulkPost;
        } else {
            return true;
        }
    }
    public function formatChildProduct($childProduct, $parent)
    {
        $taxMatches = $this->taxService->getMatchedTaxes();
        if (strlen($childProduct->getSku()) > 34) {
            $this->logger->Error('Sku need to be between 0 and 34 characters ' . $childProduct->getId());
            return false;
        }
        foreach ($taxMatches as $key => $value) {
            if (isset($childProduct->getTaxClassId) && ((int)$value['magento_tax_id'] == $childProduct->getTaxClassId)) {
                $childProduct['SalePriceTaxGroupId'] = $value['epos_tax_id'];
                break;
            } else if ($value['magento_tax_id'] == 0) {
                $postData['SalePriceTaxGroupId'] = $this->configurationService->getConfiguration()->getDefaultEposnowTaxClass();
            }
        }
        $epos_id = $this->productService->getEposNowIdById($childProduct->getId());
        if ($epos_id > 0) {
            $postData['Id'] = $epos_id;
        }
        $postData['Name'] = $childProduct->getName();
        $postData['VariantGroupId'] = $parent->getEposnowId();
        $postData['Description'] = $childProduct->getName();
        if (count($childProduct->getCategoryIds()) > 0) {
            $postData['CategoryId'] = $this->categoryService->getEposnowIdById($childProduct->getCategoryIds()[0]);
        }
        $postData['Sku'] = substr($childProduct->getSku(), 0, 34);
        $postData['CostPrice'] = (float)$childProduct->getPrice();
        $postData['SalePrice'] = (float)$childProduct->getPrice();
        $postData['SellOnWeb'] = true;
        $postData['EatOutPrice'] = (float)$childProduct->getPrice();
        $postData['size'] = $this->productService->sanitizeVariantOption($childProduct->getName(), $parent->getName());
        $bulkPost[] = $postData;
        return $bulkPost;
//        if ($this->validateEposnowProduct($postData)) {
//        } else {
//            return false;
//        }
    }
    public function createEposproducts($lastSync)
    {
        $nextPage = (int)$lastSync['last_page'];
        $lastId = $lastSync['last_id'];
        $lastCount = $lastSync['synced_count'];
        $syncProducts = $this->configurationService->getConfiguration()->getSyncProducts();
        $response = [];
        if ($syncProducts) {
            if ($nextPage == 0 && $lastCount == 0) {
                $nextPage=1;
                $totalCount = $this->productService->getProducts()->count();
                $this->configurationService->updateSyncProcess(['type' => 'product', 'synced_count' => 0, 'direction' => 'eposnow', 'last_fetched' => 0, 'total_count' => $totalCount]);
            }
            $data = $this->getMagentoProductsFormatted($lastId, $nextPage, 'simple');
            if (!empty($data['post']) || !empty($data['put'])) {
                $responseArray = $this->eposProductService->postProducts($data);
                if (is_array($responseArray) && !empty($responseArray)) {
                    $syncedCount = 0;
                    if (isset($responseArray) && is_array($responseArray)) {
                        foreach ($responseArray as $response) {
                            $this->updateProductsBySku($response);
                            $syncedCount++;
//                            if ($this->shouldEnd()) {
//                                $this->configurationService->updateSyncProcess(['type' => 'product', 'synced_count' => $syncedCount + $lastCount, 'last_id' => $response->Id, 'direction' => 'eposnow']);
//                                return true;
//                            }
                        }
                    }
                    $this->logger->info("product synced count : " . $syncedCount);
                    $this->configurationService->updateSyncProcess(['type' => 'product', 'synced_count' => count($responseArray) + $lastCount, 'direction' => 'eposnow', 'last_page' => $nextPage + 1]);
                } else {
                    $this->configurationService->updateSyncProcess(['type' => 'product', 'direction' => 'eposnow', 'last_page' => $nextPage + 1]);
                    if ($this->configurationService->getConfiguration()->getData('api_locked')) {
                        return $this->timeout_code;
                    }
                }
            } else {
                $this->configurationService->updateSyncProcess(['type' => 'product', 'direction' => 'eposnow', 'status' => 1]);
            }
        }
        return true;
    }
    public function createEposVariantsOld($lastSync)
    {
        $page = $lastSync['last_sync'];
        $syncProducts = $this->configurationService->getConfiguration()->getSyncProducts();
        $response = [];
        if ($syncProducts) {
            $variantGroupStatus = $this->getVariantGroupStatus();
            if ($variantGroupStatus == 0) {
                $data = $this->formatEposnowParentProduct($page);
                if (!empty($data) && is_array($data)) {
                    if ($responseArray = $this->eposProductService->postProduct($data)) {
                        $this->deleteEnParentProducts($responseArray);
                        $this->updateEposnowId($responseArray);
                        return $page + 1;
                    }
                } else {
                    $this->changeVariantGroupStatus(1);
                    $page = 0;
                    return $page;
                }
            } else {
                $count = $this->createChildProducts($page);
                if (!$count) {
                    $this->changeVariantGroupStatus(0);
                    $response['status'] = 'done';
                } else {
                    return $page + 1;
                }
            }
        } else {
            $response['status'] = false;
        }
        return $response;
    }
    public function createEposnowVariants($lastSync)
    {
        $lastPageSynced = (int)$lastSync['last_page'];
        $lastCount = $lastSync['synced_count'];
        if ($lastPageSynced == 0 && $lastCount == 0) {
            $nextPage = $lastPageSynced;
        } else {
            $nextPage = $lastPageSynced + 1;
        }
        $last_id = $lastSync['last_id'];
        $syncProducts = $this->configurationService->getConfiguration()->getSyncProducts();
        $response = [];
        if ($syncProducts) {
            $configurableProducts = $this->getConfigurableProductsByPage($nextPage);
            if (!empty($configurableProducts)) {
                $this->deleteEnParentProducts($configurableProducts);
                if ($this->configurationService->getConfiguration()->getData('api_locked')) {
                    return $this->timeout_code;
                }
                $data = [];
                foreach ($configurableProducts as $parent) {
                    $_children = $parent->getTypeInstance()->getUsedProducts($parent);
                    foreach ($_children as $child) {
                        $childFormatted = $this->formatChildProduct($child, $parent);
                        if ($childFormatted) {
                            if (isset($childFormatted[0]['VariantGroupId']) && $childFormatted[0]['VariantGroupId'] != null) {
                                if (isset($childFormatted[0]['Id']) && $childFormatted[0]['Id'] > 0) {
                                    $data['put'][] = $childFormatted[0];
                                } else {
                                    $data['post'][] = $childFormatted[0];
                                }
                            }
                        }
                    }
                }
                if (!empty($data['post']) || !empty($data['put'])) {
                    $responseArray = $this->eposProductService->postProducts($data);
                    if (is_array($responseArray) && !empty($responseArray)) {
                        $syncedCount = 0;
                        if (isset($responseArray) && is_array($responseArray)) {
                            foreach ($responseArray as $response) {
                                $this->updateProductsBySku($response);
                                $syncedCount++;
                            }
                        }
                        $this->configurationService->updateSyncProcess(['type' => 'variant', 'synced_count' => $syncedCount + $lastCount, 'direction' => 'eposnow', 'last_page' => $lastPageSynced + 1]);
                        return true;
                    } else {
                        if ($this->configurationService->getConfiguration()->getData('api_locked')) {
                            return $this->timeout_code;
                        }
                        $this->configurationService->updateSyncProcess(['type' => 'variant', 'direction' => 'eposnow', 'last_page' => $nextPage + 1]);
                        return true;
                    }
                }
                $this->configurationService->updateSyncProcess(['type' => 'variant', 'direction' => 'eposnow', 'last_page' => $nextPage + 1]);
            } else {
                $this->configurationService->updateSyncProcess(['type' => 'variant', 'status' => 1, 'direction' => 'eposnow']);
                return false;
            }
        } else {
            $response['status'] = false;
        }
        return $response;
    }
    public function createChildProducts($configurableProducts)
    {
        foreach ($configurableProducts as $parent) {
            $_children = $parent->getTypeInstance()->getUsedProducts($parent);
            foreach ($_children as $child) {
                $this->createChildProduct($child, $parent);
            }
        }
    }
    public function updateEposnowId($responseArray)
    {
        foreach ($responseArray as $response) {
            $this->updateProductsBySku($response);
        }
    }
    public function updateProductsBySku($data)
    {
        if ($product = $this->productService->getProductsFiltered('Sku', $data->Sku)) {
            $product->setEposnowId($data->Id);
            $this->productResource->saveAttribute($product, 'eposnow_id');
            return true;
        } else {
            return false;
        }
    }
    public function deleteEnParentProducts($products)
    {
        $deleteArray = [];
        foreach ($products as $product) {
            $eposnowId = $product->getEposnowId();
            if (isset($eposnowId) && $eposnowId > 0) {
                $deleteArray[]['Id'] = $eposnowId;
            }
        }
        $response = $this->eposProductService->deleteProduct($deleteArray);
        return $response;
    }
    public function getConfigurableProductsByPage($page)
    {
        $pageSize = 500;
        $configurableProducts = $this->productService->getProductsByPagination($pageSize, $page, 'configurable');
        return $configurableProducts;
    }
    public function createChildProduct($child, $parent)
    {
        $productFormatted = $this->formatChildProduct($child, $parent);
        $epos_id = $this->productService->getEposNowIdById($child->getId());
        if ($epos_id) {
            $response = $this->eposProductService->putProduct($productFormatted);
        } else {
            if ($response = $this->eposProductService->postProduct($productFormatted)) {
                $child->setEposnowId($response[0]->Id);
                $this->productResource->saveAttribute($child, 'eposnow_id');
            }
        }
        if (is_array($response)) {
            return $response;
        } else {
            $this->logger->Error('Epos Variant Save Error: ' . json_encode($response));
            return false;
        }
    }
    public function createChildProductsByParent($parent)
    {
        $_children = $parent->getTypeInstance()->getUsedProducts($parent);
        foreach ($_children as $child) {
            $this->createChildProduct($child, $parent);
        }
    }
    public function getProductBySku($sku)
    {
        if ($product = $this->productService->getProductsFiltered('Sku', $sku)) {
            return $product;
        } else {
            return false;
        }
    }
    public function resyncStock()
    {
        $direction = $this->configurationService->getConfiguration()->getSyncDirection();
        $return = false;
        if ($direction == 'magento') {
            $return = $this->resyncStockMagento();
        } elseif ($direction == 'eposnow') {
            $return = $this->resyncStockEposnow();
        }
        return $return;
    }
    public function syncMagentoStock($lastSync)
    {
        $lastPage = $lastSync['last_page'];
        $count = $lastSync['synced_count'];
        $lastId = $lastSync['last_id'];
        if ($lastPage == 0) {
            $totalCount = $this->productService->getProducts()->count();
            $this->configurationService->updateSyncProcess(['type' => 'stock', 'total_count' => $totalCount, 'synced_count' => 0, 'last_id' => 0, 'direction' => 'magento', 'status' => 0]);
        }
        $pageSize = 10;
        $syncedCount = 0;
        $magentoProducts = $this->productService->getProductsByPagination($pageSize, $lastPage);
        if (isset($magentoProducts) && !empty($magentoProducts)) {
            $slicedData = $this->sliceByLastId($magentoProducts->toArray(), $lastId, 'entity_id');
            if (isset($slicedData) && !empty($slicedData)) {
                foreach ($slicedData as $product) {
                    $product = (object)$product;
                    if (isset($product->eposnow_id) && null !== $product->eposnow_id) {
                        if ($this->configurationService->getConfiguration()->getData('api_locked')) {
                            return $this->timeout_code;
                        }
                        $stock = $this->stockService->getStockByProductId($product->eposnow_id);
                        if ($this->productService->updateStocks($product->eposnow_id, $stock)) {
                            $syncedCount++;
                        }
                    }
                    if ($this->shouldEnd()) {
                        $this->configurationService->updateSyncProcess(['type' => 'stock', 'synced_count' => $syncedCount + $count, 'last_id' => $product->entity_id, 'direction' => 'magento']);
                        return true;
                    }
                }
            }
            $this->configurationService->updateSyncProcess(['type' => 'stock', 'direction' => 'magento', 'last_page' => $lastPage + 1, 'synced_count' => $syncedCount + $count]);
        } else {
            $this->configurationService->updateSyncProcess(['type' => 'stock', 'status' => 1, 'direction' => 'magento']);
            return true;
        }
        return true;
    }
    public function resyncStockEposnowold()
    {
        $magentoProducts = $this->productService->getProducts();
        foreach ($magentoProducts as $product) {
            $productStock = $this->stockRegistry->getStockItem($product->getId());
            $stockqty = $productStock->getQty();
            if (null !== $product->getEposnowId() && null !== $stockqty) {
                $this->stockService->updateStock($product->getEposnowId(), $stockqty);
            }
        }
        return true;
    }
    public function syncEposnowStock($lastSync)
    {
        $lastPage = $lastSync['last_page'];
        $count = $lastSync['synced_count'];
        $lastId = $lastSync['last_id'];
        if ($lastPage == 0 && $count == 0) {
            $nextPage = 0;
            $totalCount = $this->productService->getProducts()->count();
            $this->configurationService->updateSyncProcess(['type' => 'stock', 'total_count' => $totalCount, 'synced_count' => 0, 'last_id' => 0, 'direction' => 'eposnow', 'status' => 0]);
        } else {
            $nextPage = $lastPage + 1;
        }
        $pageSize = 10;
        $syncedCount = 0;
        $magentoProducts = $this->productService->getProductsSliced($lastId, $pageSize, $nextPage);
        $this->logger->info("Total magento products per page: " .count($magentoProducts));
//        $magentoProducts = $this->sliceByLastId($magentoProducts->toArray(), $lastId, 'entity_id');
        if (isset($magentoProducts) && !empty($magentoProducts)) {
            foreach ($magentoProducts as $product) {
                $productStock = $this->stockRegistry->getStockItem($product->getId());
                if ($this->configurationService->getConfiguration()->getData('api_locked')) {
                    return $this->timeout_code;
                }
                $stockqty = $productStock->getQty();
                if (null !== $product->getEposnowId() && null !== $stockqty) {
                    $syncedCount++;
                    $this->stockService->updateStock($product->getEposnowId(), $stockqty);
                }
                if ($this->shouldEnd()) {
                    $this->configurationService->updateSyncProcess(['type' => 'stock', 'synced_count' => $syncedCount + $count, 'last_id' => $product->getId(), 'direction' => 'eposnow', 'last_page' => $nextPage]);
                    return true;
                }
            }
            $this->configurationService->updateSyncProcess(['type' => 'stock', 'synced_count' => $syncedCount + $count, 'direction' => 'eposnow', 'last_page' => $nextPage]);
            return true;
        } else {
            $this->logger->info("No more products with stock to sync" . print_r($syncedCount + $count, 1));
            $this->configurationService->updateSyncProcess(['type' => 'stock', 'direction' => 'eposnow', 'status' => 1]);
            return true;
        }
    }
    public function resyncStockEposnowByPage()
    {
        $page = 0;
        $request = [];
        $count = true;
        while ($count) {
            $magentoProducts = $this->productService->getProductsByPagination(200, $page);
            if (!empty($magentoProducts)) {
                $count = true;
                foreach ($magentoProducts as $product) {
                    $productStock = $this->stockRegistry->getStockItem($product->getId());
                    $stockqty = $productStock->getQty();
                    if (null !== $product->getEposnowId() && null !== $stockqty) {
                        $request[] = $this->stockService->formatStockItem($product->getEposnowId(), $stockqty);
                    }
                }
                $response = $this->stockService->bulkPostStock($request);
                $page++;
            } else {
                $count = false;
            }
        }
        return true;
    }
    public function getStockItem($productId)
    {
        return $this->stockItemRepository->get($productId);
    }
    public function processTempDataOld($page)
    {
        $paginatedData = $this->getPaginatedTempData($page);
        $syncedProducts = [];
        if ($paginatedData != false) {
            foreach ($paginatedData as $data) {
                if ($this->productService->createProductsFromTemp($data['data'])) {
                    $syncedProducts[] = $data['id'];
                }
            }
            $this->truncateEposDataTable($syncedProducts);
            $this->updateSyncCount('product', count($syncedProducts));
            $page = $page + 1;
            $this->updateSyncedPage($page);
            return $page;
        } else {
//            $this->updateSyncedPage(0);
            $this->updateEposLastFetchedPage(0);
            $this->updateSyncedPage(0);
            return false;
        }
    }
    public function processTempData($page, $lastId, $lastCount)
    {
        $paginatedData = $this->getPaginatedTempData($page);
        if (!empty($paginatedData) && $paginatedData != false) {
            $paginatedData = $this->sliceByLastId($paginatedData->getData(), $lastId, 'id');
            $syncedProducts = [];
            foreach ($paginatedData as $data) {
                if ($this->productService->createProductsFromTemp($data['data'])) {
                    $syncedProducts[] = $data['id'];
                }
                if ($this->shouldEnd()) {
                    $this->configurationService->updateSyncProcess(['type' => 'product', 'direction' => 'magento', 'synced_count' => count($syncedProducts) + $lastCount, 'last_page' => $page, 'last_id' => $data['id']]);
                    return true;
                }
                $lastId = $data['id'];
            }
            $page = $page + 1;
            $this->configurationService->updateSyncProcess(['type' => 'product', 'direction' => 'magento', 'synced_count' => count($syncedProducts) + $lastCount, 'last_page' => $page, 'last_id' => $lastId]);
            return true;
        } else {
            $this->truncateEposDataTable();
            $this->configurationService->updateSyncProcess(['type' => 'product', 'direction' => 'magento', 'status' => 1]);
            return true;
        }
    }
    public function getPaginatedTempData($currentPage, $pageSize = 20)
    {
        $collection = $this->eposnowdataFactory->create()->getCollection();
        $collection->setPageSize($pageSize);
        $numberOfPages = $collection->getLastPageNumber();
        if ($numberOfPages >= $currentPage) {
            $collection->setCurPage($currentPage);
            $collection->load();
            $data = $collection->getData();
            if (!empty($data)) {
                return $collection;
            } else {
                return false;
            }
        } else {
            return [];
        }
    }
    public function sliceByLastId($data, $id, $field)
    {
        $thresholdId = $id;
        $index = array_search($thresholdId, array_column($data, $field));
        if ($index !== false) {
            $result = array_slice($data, $index + 1);
        } else {
            $result = $data;
        }
        return $result;
    }
    function truncateEposDataTable($products = [])
    {
        $model = $this->eposnowdataFactory->create();
        $connection = $model->getResource()->getConnection();
        $tableName = $model->getResource()->getMainTable();
        if (isset($products) && $products) {
            $productString = implode(',', $products);
            $sql = "Delete FROM " . $tableName . " Where id IN   (" . $productString . ")";
        } else {
            $sql = "Delete  FROM " . $tableName;
        }
        $connection->query($sql);
    }
    function updateSyncCount($type, $count)
    {
        $model = $this->configurationFactory->create();
        $configurationService = $this->configurationService->getConfiguration();
        $syncedCount = [];
        $prevCount = 0;
        $field = $type . "_sync_count";
        if ($count > 0) {
            $connection = $model->getResource()->getConnection();
            $tableName = $model->getResource()->getMainTable();
            $sql = "SELECT " . $field . " FROM " . $tableName;
            $result = $connection->fetchAll($sql);
            $prevCount = $result[0][$field];
            if ($type == "product") {
                $syncedCount[$field] = $prevCount + $count;
            } else if ($type == 'category') {
                $syncedCount[$field] = $count;
            }
        } else {
            $syncedCount[$field] = 0;
        }
        $syncedCount['id'] = 1;
        $model->setData($syncedCount);
        $model->save();
        return true;
    }
    public function updateSyncedPage($page)
    {
        $model = $this->configurationFactory->create();
        $configurationService = $this->configurationService->getConfiguration();
        $update['product_page'] = $page;
        $update['id'] = 1;
        $model->setData($update);
        if ($model->save()) {
            return true;
        } else {
            return false;
        }
    }
    function getSyncedCount($type)
    {
        $model = $this->configurationFactory->create();
        $field = $type . "_sync_count";
        $connection = $model->getResource()->getConnection();
        $tableName = $model->getResource()->getMainTable();
        $sql = "SELECT " . $field . " FROM " . $tableName;
        $result = $connection->fetchAll($sql);
        $count = $result[0][$field];
        return $count;
    }
    function getCountToSync($type)
    {
        $model = $this->configurationFactory->create();
        $field = "epos" . $type . "_count";
        $connection = $model->getResource()->getConnection();
        $tableName = $model->getResource()->getMainTable();
        $sql = "SELECT " . $field . " FROM " . $tableName;
        $result = $connection->fetchAll($sql);
        $count = $result[0][$field];
        return $count;
    }
    public function createMagentoOrders($lastSync)
    {
        $lastPageSynced = (int)$lastSync['last_page'];
        if ($lastPageSynced == 0) {
            $nextPage = $lastPageSynced;
        } else {
            $nextPage = $lastPageSynced + 1;
        }
        $lastId = $lastSync['last_id'];
        $lastCount = $lastSync['synced_count'];
        $response = [];
        $syncOrders = $this->configurationService->getConfiguration()->getSyncOrders();
        $syncOrdersToMagento = $this->configurationService->getConfiguration()->getData('sync_orderstoEposnow');
        $syncCount = 0;
        if ($syncOrders) {
            $fetched = $this->getEposnowOrders($nextPage);
            if (isset($response) && !empty($response)) {
                $orders = $this->sliceByLastId($response->data, $lastId, 'Id');
                foreach ($orders as $order) {
                    if (!empty($this->orderService->getOrderByEposnowId($order->Id))) {
                        continue;
                    }
                    if ($this->orderService->createOrder($order)) {
                        $syncCount++;
                    }
                    if ($this->shouldEnd()) {
                        $this->configurationService->updateSyncProcess(['type' => 'order', 'direction' => 'magento', 'synced_count' => $syncCount + $lastCount, 'last_page' => $nextPage, 'last_id' => $order->Id]);
                        return true;
                    }
                }
                if ($this->configurationService->getConfiguration()->getData('api_locked')) {
                    return $this->timeout_code;
                }
                $this->configurationService->updateSyncProcess(['type' => 'order', 'direction' => 'magento', 'synced_count' => $syncCount + $lastCount, 'last_page' => $nextPage + 1]);
                return true;
            } else {
                $this->configurationService->updateSyncProcess(['type' => 'order', 'direction' => 'magento', 'synced_count' => $syncCount + $lastCount, 'last_page' => $nextPage, 'status' => 1]);
            }
        }
        return true;
    }
    public function createEposNowOrders($lastSync)
    {
        $lastPageSynced = (int)$lastSync['last_page'];
        $nextPage = $lastPageSynced + 1;
        $lastCount = $lastSync['synced_count'];
        $lastId = $lastSync['last_id'];
        $syncOrders = $this->configurationService->getConfiguration()->getSyncOrders();
        $response = [];
        if ($syncOrders) {
            $magentoOrders = $this->orderService->getMagentoOrders($nextPage, $lastId);
            if (isset($magentoOrders) && !empty($magentoOrders)) {
                $syncedCount = 0;
                foreach ($magentoOrders as $order) {
                    if ($order->getEposnowId() > 0) {
                        $this->transactionService->place($order);
                    } else {
                        $this->transactionService->create($order);
                    }
                    if ($this->shouldEnd()) {
                        $this->configurationService->updateSyncProcess(['type' => 'order', 'direction' => 'eposnow', 'synced_count' => $syncedCount + $lastCount, 'last_page' => $nextPage, 'last_id' => $order->entity_id]);
                        return true;
                    }
                    if ($this->configurationService->getConfiguration()->getData('api_locked')) {
                        return $this->timeout_code;
                    }
                }
                $this->configurationService->updateSyncProcess(['type' => 'order', 'direction' => 'eposnow', 'synced_count' => $syncedCount + $lastCount, 'last_page' => $nextPage, 'last_id' => 0]);
            } else {
                $this->configurationService->updateSyncProcess(['type' => 'order', 'direction' => 'eposnow', 'status' => 1]);
            }
        }
        return true;
    }
    public function getEposnowOrders($page)
    {
        $transactions = $this->transactionService->get($page + 1);
        return $transactions;
    }
    public function deleteEposNowTransactions()
    {
        $transactions = $this->transactionService->get();
        foreach ($transactions as $transaction) {
            $this->transactionService->deleteById($transaction->Id);
        }
    }
    public function getEposProductsByPage($pageNumber)
    {
        $response = $this->eposProductService->getProductByPage($pageNumber);
        if (is_array($response) && !empty($response)) {
            $this->productService->saveEposProductTemp($response, 'product');
            $update = ['type' => 'product', 'last_fetched' => $pageNumber, 'direction' => 'magento'];
            $this->configurationService->updateSyncProcess($update);
            return $response;
        } else {
            return $response;
        }
    }
    function updateEposLastFetchedPage($page)
    {
        $model = $this->configurationFactory->create();
        $update = [];
        $update['epos_last_product_page'] = $page;
        $update['id'] = 1;
        $model->setData($update);
        if ($model->save()) {
            return true;
        } else {
            return false;
        }
    }
    function setTotalEposProducts()
    {
        $response = $this->eposProductService->getEposProductStats();
        if (isset($response->TotalProducts)) {
            return $response->TotalProducts;
        } else {
            return false;
        }
    }
    public function getEposProductPages()
    {
        $response = $this->eposProductService->getEposProductStats();
        if (isset($response->TotalProducts)) {
            $noProducts = $response->TotalProducts;
            $noPages = (int)ceil($noProducts / 200);
            return $noPages;
        } else {
            return false;
        }
    }
    public function changeVariantGroupStatus($status)
    {
        $model = $this->configurationFactory->create();
        $update['en_variantGrp_status'] = $status;
        $update['id'] = 1;
        $model->setData($update);
        if ($model->save()) {
            return true;
        } else {
            return false;
        }
    }
    public function getVariantGroupStatus()
    {
        $status = $this->configurationService->getConfiguration();
        $status = $status->getData();
        return $status['en_variantGrp_status'];
    }
    public function resumeSyncByCron()
    {
        $direction = $this->configurationService->getConfiguration()->getSyncDirection();
        $data = [];
        $lastSync = $this->configurationService->resumeCron($direction);
        if (isset($lastSync['status']) && $lastSync['status'] == 1) {
            $data['syncProcess'] = $this->configurationService->loadCronSyncprocess($direction);
            $data['status'] = 'done';
            return $data;
        } else {
            $type = $lastSync['type'];
            if ($direction == 'magento') {
                if ($type == 'order') {
                    $data['result'] = $this->createMagentoOrders($lastSync);
                } elseif ($type == 'stock') {
                    $data['result'] = $this->syncMagentoStock($lastSync);
                }
            } elseif ($direction == 'eposnow') {
                if ($type == 'order') {
                    $data['result'] = $this->createEposNowOrders($lastSync);
                } elseif ($type == 'stock') {
                    $data['result'] = $this->syncEposnowStock($lastSync);
                }
            }
            return $data;
        }
    }
}

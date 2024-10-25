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
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\ProductRepository;
use Magento\Catalog\Model\ProductFactory;
use Wrightwaydigital\Enconnector\Service\EposNow\EposProductService;
use Magento\Framework\Registry;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Wrightwaydigital\Enconnector\Service\CategoryService;
use Wrightwaydigital\Enconnector\Service\EposNow\StockService;
use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Eav\Api\AttributeOptionManagementInterface;
use Magento\Eav\Api\Data\AttributeOptionInterface;
use Magento\Eav\Api\Data\AttributeOptionInterfaceFactory;
use Magento\Eav\Model\Entity\AttributeFactory;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Wrightwaydigital\Enconnector\Service\Configuration\ConfigurationService;
use Wrightwaydigital\Enconnector\Logger\Logger;

class ProductService
{
    private $productRepository;
    private $productFactory;
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
    private $eposnowcolourFactory;
    private $attibuterepository;
    protected $attributeFactory;
    protected $attributeOptionManagement;
    protected $attributeOptionInterfaceFactory;
    private $logger;
    public function __construct(
        ProductRepository                            $productRepository,
        ProductFactory                               $productFactory,
        EposProductService                           $eposProductService,
        Registry                                     $registry,
        CollectionFactory                            $collectionFactory,
        CategoryService                              $categoryService,
        \Magento\Catalog\Model\ResourceModel\Product $productResourceModel,
        StockService                                 $stockService,
        StockRegistryInterface                       $stockRegistry,
        ConfigurationService                         $configurationService,
        \Wrightwaydigital\Enconnector\Model\EposnowdataFactory    $eposnowdataFactory,
        TaxService                                   $taxService,
        ProductAttributeRepositoryInterface          $attributeRepository,
        \Wrightwaydigital\Enconnector\Model\EposnowcoloursFactory $eposnowcolourFactory,
        AttributeFactory                             $attributeFactory,
        AttributeOptionManagementInterface           $attributeOptionManagement,
        AttributeOptionInterfaceFactory              $attributeOptionInterfaceFactory,
        Logger                                       $logger
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
        $this->attibuterepository = $attributeRepository;
        $this->eposnowcolourFactory = $eposnowcolourFactory;
        $this->attributeFactory = $attributeFactory;
        $this->attributeOptionManagement = $attributeOptionManagement;
        $this->attributeOptionInterfaceFactory = $attributeOptionInterfaceFactory;
        $this->logger = $logger;
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
    public function getProductByEposnowId($eposnowId)
    {
        if ($this->productFactory->create()->getCollection()->addAttributeToSelect('eposnow_id')->addAttributeToFilter('eposnow_id', $eposnowId)->getFirstItem()->getId()) {
            return $this->productFactory->create()->getCollection()->addAttributeToSelect('eposnow_id')->addAttributeToFilter('eposnow_id', $eposnowId)->getFirstItem();
        } else {
            return false;
        }
    }
    public function createProduct($data)
    {
        $alreadyExists = false;
        if (!isset($data->Sku)) {
            $this->logger->Error('Invalid Sku ' . $data->Id);
            return false;
        }
        foreach ($this->getProducts() as $product) {
            if ($product->getSku() == $data->Sku) {
                $alreadyExists = true;
                break;
            }
        }
        if (!isset($data->TaxClass)) {
            $data->TaxClass = $this->findTaxClass($data);
        }
        if (isset($data->Sku) && $data->Sku != '' && !$alreadyExists) {
            if (isset($data->VariantGroupId)) {
                $this->setConfigurableProducts($data);
            } else {
                try {
                    $this->registry->register('isCreatedBySync', true, true);
                    $product = $this->productFactory->create();
                    $product = $this->assignProductData($product, $data);
                    $product->save();
                    $this->registry->unregister('isCreatedBySync');
                    return true;
                } catch (\Exception $e) {
                    return false;
                }
            }
        } else {
            if ($this->updateProduct($data)) {
                return true;
            }
        }
        $this->registry->unregister('isCreatedBySync');
        return true;
    }
    public function assignProductData($product, $data)
    {
        $product->setTypeId('simple')
            ->setCategoryIds([$this->categoryService->getIdByEposnowId($data->CategoryId)])
            ->setName($data->Name)
            ->setEposnowId($data->Id)
            ->setStockData(['qty' => $this->stockService->getStockByProductId($data->Id), 'is_in_stock' => 1])
            ->setAttributeSetId(4)
            ->setSku($data->Sku)
            ->setStoreId(0)
            ->setVisibility(4)
            ->setStatus(Status::STATUS_ENABLED)
            ->setWebsiteIds(array(1))
            ->setInStock(true)
            ->setPrice($data->SalePrice)
            ->setData('url_key', "newkey-{$data->Id}")
            ->setIsActive(true)
            ->setTaxClassId($data->TaxClass);
        if ($this->configurationService->getConfiguration()->getSyncType() == 1) {
            $product->setShortDescription($data->Description);
        } else {
            $product->setDescription($data->Description);
        }
        return $product;
    }
    public function setConfigurableProducts($data)
    {
        $tempData = $this->getTempData();
        $childProducts = [];
        $deleteData = [];
        $sizeAttributes = [];
        $colorAttributes = [];
        if (!empty($tempData)) {
            foreach ($tempData as $temp) {
                $productData = json_decode($temp['data']);
                if (isset($productData->VariantGroupId) && $productData->VariantGroupId == $data->VariantGroupId) {
                    $childProducts[] = $productData;
                    $deleteData[] = $temp['id'];
                    if (!in_array($productData->Size, $sizeAttributes)) {
                        $sizeAttributes[] = $productData->Size;
                    }
                    if (isset($productData->ColourId) && $productData->ColourId != false) {
                        $colorAttributes[] = $this->getColor($productData->ColourId);
                    }
                }
            }
        } else {
            //webhook variant creation
            $childProducts[] = $data;
            if (isset($data->Size)) {
                $sizeAttributes[] = $data->Size;
            }
            if (isset($data->ColourId)) {
                $colorAttributes[] = $this->getColor($data->ColourId);
            }
        }
        $attributes = $this->createConfigurableAttributes($data, $sizeAttributes, $colorAttributes);
        $this->createConfigurableProduct($childProducts, $data, $attributes);
        $this->truncateEposDataTable($deleteData);
        return $childProducts;
    }
    public function createConfigurableProduct($childProducts, $data, $configurableAttributes)
    {
        //checking if parent product has aleady been created via webhooks
        if (!$configurableProduct = $this->getProductByEposnowId($data->VariantGroupId)) {
            $this->registry->register('isCreatedBySync', true, true);
            $configurableProduct = $this->productFactory->create();
            $configurableProductname = $this->getCreateConfigurableProductName($data);
            $configurableProduct->setSku(substr($data->Sku, 0, 21) . $configurableProductname);
            $configurableProduct->setName($configurableProductname);
            $configurableProduct->setPrice($data->SalePrice);
            $configurableProduct->setAttributeSetId(4);
            $configurableProduct->setEposnowId($data->VariantGroupId);
            $configurableProduct->setTypeId('configurable');
            $configurableProduct->setVisibility(4);
            $configurableProduct->setExtensionAttributes($configurableProduct->getExtensionAttributes());
        }
        try {
            $configurableProduct->save();
        } catch (\Exception $e) {
            return false;
        }
        $simpleProducts = $this->createSimpleProducts($childProducts);
        $attributes = [];
        foreach ($configurableAttributes as $attributeData) {
            $attribute = $this->attibuterepository->get($attributeData['attribute_code']);
            $attribute->loadByCode('catalog_product', $attributeData['attribute_code']);
            $attributes[] = $attribute->getAttributeId();
        }
        $configurableProduct->getTypeInstance()->setUsedProductAttributeIds($attributes, $configurableProduct);
        $configurableAttributesData = $configurableProduct->getTypeInstance()->getConfigurableAttributesAsArray($configurableProduct);
        $configurableProduct->setCanSaveConfigurableAttributes(true);
        $configurableProduct->setConfigurableAttributesData($configurableAttributesData);
        $configurableProductsData = array();
        $configurableProduct->setConfigurableProductsData($configurableProductsData);
        try {
// Save the configurable product
            $configurableProduct->save();
            $associatedProduct = $this->productFactory->create();
            $associatedProduct->load($configurableProduct->getId());
            $associatedProduct->setAssociatedProductIds($simpleProducts); // Setting Associated Products
            $associatedProduct->setCanSaveConfigurableAttributes(true);
            $associatedProduct->save();
            $this->registry->unregister('isCreatedBySync');
        } catch (\Exception $e) {
            return "Error: " . $e->getMessage();
        }
        return false;
    }
    public function createSimpleProducts($productData)
    {
        $associatedProductIds = [];
        foreach ($productData as $data) {
            $simpleProduct = $this->createSimpleProduct($data);
            $associatedProductIds[] = $simpleProduct;
        }
        return $associatedProductIds;
    }
    public function createsimpleProduct($data)
    {
        $simpleProduct = $this->productFactory->create();
        $simpleProduct->setTypeId('simple')
            ->setCategoryIds([$this->categoryService->getIdByEposnowId($data->CategoryId)])
            ->setName($data->Name)
            ->setEposnowId($data->Id)
            ->setStockData(['qty' => $this->stockService->getStockByProductId($data->Id), 'is_in_stock' => 1])
            ->setAttributeSetId(4)
            ->setSku($data->Sku)
            ->setStoreId(0)
            ->setVisibility(4)
            ->setStatus(Status::STATUS_ENABLED)
            ->setWebsiteIds(array(1))
            ->setInStock(true)
            ->setPrice($data->SalePrice)
            ->setData('url_key', "newkey-{$data->Id}")
            ->setIsActive(true)
            ->setAttributeSetId(4)
            ->setTaxClassId($data->TaxClass);
        $poductReource = $this->productFactory->create();
        $isAttributeExist = $poductReource->getResource()->getAttribute('color');
        $optionText = $isAttributeExist->getSource()->getOptionId('red');
        if (isset($data->ColourId)) {
            $simpleProduct->setData('color', $this->attributeValueExists('color', $this->getColor($data->ColourId)));
        }
        if (isset($data->Size)) {
            $simpleProduct->setData('size', $this->attributeValueExists('size', $data->Size));
        }
        $simpleProduct->save();
        return $simpleProduct->getId();
    }
    public function getCreateConfigurableProductName($data)
    {
        $variantName = $data->Name;
        $configurableProductName = str_replace($data->Size, "", $variantName);
        return $configurableProductName;
    }
    public function createConfigurableAttributes($data, $sizeAttributes, $colorAttributes)
    {
        $configurableAttributes = [];
        if (isset($sizeAttributes) && !empty($sizeAttributes)) {
            $configurableAttributes[0]['attribute_code'] = 'size';
            $configurableAttributes[0]['attribute_values'] = $sizeAttributes;
            foreach ($sizeAttributes as $key => $size) {
                $this->addAttributeValue('size', $size);
            }
        }
        if (isset($data->ColourId) && $data->ColourId != false) {
            $configurableAttributes[1]['attribute_code'] = 'color';
            $configurableAttributes[1]['attribute_values'] = $colorAttributes;
            foreach ($colorAttributes as $key => $color) {
                $this->addAttributeValue('color', $color);
            }
        }
        return $configurableAttributes;
    }
    function addAttributeValue($attributeCode, $attValue)
    {
        if (!$this->attributeValueExists($attributeCode, $attValue)) {
            $this->addAttributeOptionValue($attributeCode, $attValue);
        }
    }
    public function addAttributeOptionValue($attributeCode, $optionLabel)
    {
        $attribute = $this->attributeFactory->create()->loadByCode('catalog_product', $attributeCode);
        if (!$attribute->getId()) {
            return; // Attribute not found
        }
        $option = $this->attributeOptionInterfaceFactory->create();
        $option->setLabel($optionLabel);
        $this->attributeOptionManagement->add(
            'catalog_product',
            $attribute->getId(),
            $option
        );
    }
    function attributeValueExists($attrCode, $optLabel)
    {
        $product = $this->productFactory->create();
        $isAttrExist = $product->getResource()->getAttribute($attrCode); // Add here your attribute code
        $optId = '';
        if ($isAttrExist && $isAttrExist->usesSource()) {
            if ($optId = $isAttrExist->getSource()->getOptionId($optLabel)) {
                return $optId;
            } else {
                return false;
            }
        }
        return false;
    }
    public function insertColorAttributes()
    {
        $this->truncateEposnowcolourFactory();
        $model = $this->eposnowcolourFactory->create();
        $colors = $this->eposProductService->getEposColor();
        $eposnowColours = [];
        foreach ($colors as $color) {
            $eposnowColours['eposnow_id'] = $color->Id;
            $eposnowColours['colour_code'] = $color->Code;
            $eposnowColours['name'] = $color->Name;
            $model->setData($eposnowColours);
            $model->save();
        }
    }
    public function getColor($colorId)
    {
        $collection = $this->eposnowcolourFactory->create()->getCollection();
        $collection->addFieldToFilter('eposnow_id', $colorId);
        $collection->load('name');
        if ($data = $collection->getData()) {
            $data = $data[0]['name'];
            return $data;
        }
        return false;
    }
    function truncateEposnowcolourFactory()
    {
        $model = $this->eposnowcolourFactory->create();
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
    public function getTempData()
    {
        $collection = $this->eposnowdataFactory->create()->getCollection();
        $data = $collection->getData();
        if (!empty($data)) {
            return $collection;
        } else {
            return false;
        }
    }
    public
    function createProducts($eposnowData)
    {
        foreach ($eposnowData as $eposnowProduct) {
            $alreadyExists = false;
            if (!isset($eposnowProduct->Sku) || $eposnowProduct->Sku == '') {
                $eposnowProduct->Sku = $eposnowProduct->Name;
                $alreadyExists = false;
            }
            foreach ($this->getProducts() as $product) {
                if ($product->getSku() == $eposnowProduct->Sku) {
                    $alreadyExists = true;
                }
            }
            if (isset($eposnowProduct->Sku) && $eposnowProduct->Sku != '' && !$alreadyExists) {
                $this->createProduct($eposnowProduct);
            }
        }
    }
    public function saveEposProductTemp($eposnowData, $type)
    {
        $taxMatches = $this->taxService->getMatchedTaxes();
        foreach ($eposnowData as $eposnowProduct) {
            $eposnowProduct->TaxClass = $this->findTaxClass($eposnowProduct);
            $model = $this->eposnowdataFactory->create();
            $tempData['entity_id'] = $eposnowProduct->Id;
            $tempData['entity_type'] = $type;
            $tempData['data'] = json_encode($eposnowProduct);
            $model->setData($tempData);
            $model->save();
        }
    }
    public function findTaxClass($eposnowProduct)
    {
        $taxMatches = $this->taxService->getMatchedTaxes();
        $taxClass = 0;
        foreach ($taxMatches as $key => $value) {
            if (($eposnowProduct->SalePriceTaxGroupId > 0) && ((int)$value['eposnow_tax_id'] == $eposnowProduct->SalePriceTaxGroupId)) {
                $taxClass = $value['magento_tax_id'];
                break;
            } else {
                $taxClass = $this->configurationService->getConfiguration()->getDefaultMagentoTaxClass();
                break;
            }
        }
        return $taxClass;
    }
    public function createProductsFromTemp($tempData)
    {
        $eposnowProduct = json_decode($tempData);
        if (!isset($eposnowProduct->Sku) || $eposnowProduct->Sku == '') {
            $eposnowProduct->Sku = $eposnowProduct->Name;
        }
        try {
            if ($this->createProduct($eposnowProduct)) {
                return true;
            } else {
                return false;
            }
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            return false;
        }
    }
    public function updateStock($eposnowId)
    {
        $this->registry->register('isCreatedBySync', true, true);
        $product = $this->productFactory->create()->getCollection()->addAttributeToSelect('eposnow_id')->addAttributeToFilter('eposnow_id', $eposnowId)->getFirstItem();
        $stock = $this->stockService->getStockByProductId($eposnowId);
        $stockItem = $this->stockRegistry->getStockItemBySku($product->getSku());
        $stockItem->setQty($stock);
        if ($stock > 0) {
            $stockItem->setIsInStock(true);
            $stockItem->setAttributeSetId(4);
            $stockItem->setStoreId(0);
            $stockItem->setVisibility(4);
            $stockItem->setStatus(Status::STATUS_ENABLED);
            $stockItem->setIsActive(true);
            $stockItem->setWebsiteIds(array(1));
            $stockItem->setStockData(['qty' => $stock, 'is_in_stock' => 1]);
        } else {
            $stockItem->setIsInStock(false);
            $stockItem->setIsActive(false);
        }
        if ($this->stockRegistry->updateStockItemBySku($product->getSku(), $stockItem)) {
            return true;
        } else {
            return false;
        }
        $this->registry->unregister('isCreatedBySync');
    }
    public function updateStocks($eposnowId, $stock)
    {
        $this->registry->register('isCreatedBySync', true, true);
        $product = $this->productFactory->create()->getCollection()->addAttributeToSelect('eposnow_id')->addAttributeToFilter('eposnow_id', $eposnowId)->getFirstItem();
        $stockItem = $this->stockRegistry->getStockItemBySku($product->getSku());
        $stockItem->setQty($stock);
        if ($stock > 0) {
            $stockItem->setIsInStock(true);
            $stockItem->setAttributeSetId(4);
            $stockItem->setStoreId(0);
            $stockItem->setVisibility(4);
            $stockItem->setStatus(Status::STATUS_ENABLED);
            $stockItem->setIsActive(true);
            $stockItem->setWebsiteIds(array(1));
            $stockItem->setStockData(['qty' => $stock, 'is_in_stock' => 1]);
        } else {
            $stockItem->setIsInStock(false);
            $stockItem->setIsActive(false);
        }
        if ($this->stockRegistry->updateStockItemBySku($product->getSku(), $stockItem)) {
            return true;
        } else {
            return false;
        }
        $this->registry->unregister('isCreatedBySync');
    }
    public function updateProduct($data)
    {
        $this->logger->info("Product update : ".$data->Id);
        $this->registry->register('isCreatedBySync', true, true);
        try {
            $product = $this->productFactory->create()->getCollection()->addAttributeToFilter('sku', $data->Sku)->getFirstItem();
            if ($this->configurationService->getConfiguration()->getSyncTitle()) {
                $product->setData('name', $data->Name);
            }
            if ($this->configurationService->getConfiguration()->getSyncDesc()) {
                if ($this->configurationService->getConfiguration()->getSyncType() == 1) {
                    $product->setShortDescription($data->Description);
                } else {
                    $product->setLongDescription($data->Description);
                }
            }
            if ($this->configurationService->getConfiguration()->getSyncPrice()) {
                $product->setData('price', $data->SalePrice);
            }
            $product->setData('eposnow_id', $data->Id);
            $product->setCategoryIds([$this->categoryService->getIdByEposnowId($data->CategoryId)]);
            if ($this->configurationService->getConfiguration()->getSyncStock()) {
                $stock = $this->stockService->getStockByProductId($data->Id);
                $stockItem = $this->stockRegistry->getStockItemBySku($product->getSku());
                $stockItem->setQty($stock);
                $this->stockRegistry->updateStockItemBySku($product->getSku(), $stockItem);
            }
            if (!$data->Sku) {
                $product->setSku($data->Name);
            }
            $product->save();
            $this->logger->info("Product Update Success : ".$data->Id);
            $this->registry->unregister('isCreatedBySync');
            return true;
        } catch (\Exception $e) {
            $this->logger->info("Product Update Failed" . print_r($e, true));
            return $e;
        }
    }
    public function deleteProduct($magentoId)
    {
        $product = $this->productRepository->getById($magentoId);
        $this->registry->register("isSecureArea", true);
        $product->delete();
        $this->registry->unregister("isSecureArea");
    }
    public function getProductsFiltered($filter, $filterValue)
    {
        $product = $this->productFactory->create();
        $product = $product->loadByAttribute($filter, $filterValue);
        return $product;
    }
    public function getProducts()
    {
        $products = $this->collectionFactory->create();
        $products->addAttributeToSelect('*');
        return $products;
    }
    public function getProductsByPagination($pageSize = 10, $currentPage = 1, $type = 'simple')
    {
        $currentPage = $currentPage + 1;
        $products = $this->collectionFactory->create();
        $products->setPageSize($pageSize);
        $products->addAttributeToFilter('type_id', $type);
        $products->addAttributeToSelect('*');
        $numberOfPages = $products->getLastPageNumber();
        if ($numberOfPages >= $currentPage) {
            $products->setCurPage($currentPage);
            $products = $products->load();
            return $products;
        } else {
            return false;
        }
    }
    public function getProductsSliced($lastId, $pageSize = 10, $currentPage = 1)
    {
        $products = $this->collectionFactory->create();
        $products->setPageSize($pageSize);
        $products->addAttributeToFilter('type_id', ['in' => ['simple', 'configurable']]);
        if ($lastId > 0) {
            $productId = $this->getIdByEposnowId($lastId);
            if (isset($productId) && $productId > 0) {
                $products->addFieldToFilter('entity_id', ['gt' => $productId]);
            }
        }
        $products->addAttributeToSelect('*');
        $numberOfPages = $products->getLastPageNumber();
        if ($numberOfPages >= $currentPage) {
            $products->setCurPage($currentPage);
            $products = $products->load();
            return $products;
        } else {
            return [];
        }
    }
    public function resyncProducts()
    {
        $this->deleteProducts();
        $productData = $this->eposProductService->getProduct();
        $this->createProducts($productData);
    }
    public function productExists($productEposnowId)
    {
        foreach ($this->getProducts() as $product) {
            if ($this->getEposnowIdById($product->getId()) == $productEposnowId) {
                return true;
            }
        }
        return false;
    }
    public function deleteProducts()
    {
        $products = $this->productFactory->create()->getCollection();
        $this->registry->register("isSecureArea", true);
        foreach ($products as $product) {
            $product->delete();
        }
        $this->registry->unregister("isSecureArea");
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
    function sanitizeString($inputString, $length = 40)
    {
        $inputString = substr($inputString, 0, $length);
        return preg_replace('/[^a-zA-Z0-9_ %\[\]\.\(\)%&-]/s', '', $inputString);
    }
    function sanitizeVariantOption($variantName, $parentName)
    {
        $variantName = preg_replace('/[^a-zA-Z0-9_ %\[\]\.\(\)%&-]/s', '', $variantName);
        $variantName = $string = str_replace(' ', '', $variantName);
        $parentName = preg_replace('/[^a-zA-Z0-9_ %\[\]\.\(\)%&-]/s', '', $parentName);
        $parentName = $string = str_replace(' ', '', $parentName);
        return $variantName = str_replace($parentName . '-', '', $variantName);
    }
}

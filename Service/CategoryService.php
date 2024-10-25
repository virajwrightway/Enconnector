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

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Catalog\Model\CategoryRepository;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Wrightwaydigital\Enconnector\Service\EposNow\EposCategoryService;
use Magento\Framework\Registry;
use Wrightwaydigital\Enconnector\Service\Configuration\ConfigurationService;
use stdClass;

class CategoryService
{
    protected $categoryFactory;
    protected $categoryRepository;
    protected $eposCategoryService;
    protected $registry;
    protected $configurationService;
    private $collectionFactory;
    public $syncedCount = 0;
    public function __construct(
        CategoryFactory      $categoryFactory,
        CategoryRepository   $categoryRepository,
        CollectionFactory    $collectionFactory,
        EposCategoryService  $eposCategoryService,
        Registry             $registry,
        ConfigurationService $configurationService
    )
    {
        $this->categoryFactory = $categoryFactory;
        $this->categoryRepository = $categoryRepository;
        $this->eposCategoryService = $eposCategoryService;
        $this->registry = $registry;
        $this->configurationService = $configurationService;
        $this->collectionFactory = $collectionFactory;
    }
    public function getEposNowId(Category $category)
    {
        return $category->getEposnowId();
    }
    public function getIdByEposnowId($eposnowId)
    {
        return $this->categoryFactory->create()->getCollection()->addAttributeToSelect('eposnow_id')->addAttributeToFilter('eposnow_id', $eposnowId)->getFirstItem()->getId();
    }
    public function getEposnowIdById($categoryId)
    {
        return $this->categoryRepository->get($categoryId)->getEposnowId();
    }
    public function updateCategory($data)
    {
        $categoryId = $this->getIdByEposnowId($data->Id);
        if (isset($categoryId) && $categoryId > 0) {
            if ($this->configurationService->getConfiguration()->getSyncTitle()) {
                $this->categoryRepository->get($categoryId)->setIsActive(true)->setData('name', $data->Name)->setStoreId(0)->save();
            }
            if ($this->configurationService->getConfiguration()->getSyncCatdesc()) {
                $this->categoryRepository->get($categoryId)->setIsActive(true)->setData('setDescription', $data->Description)->setStoreId(0)->save();
            }
            if (isset($data->ParentId) && $data->ParentId > 0) {
                $this->categoryRepository->get($categoryId)->setIsActive(true)->setStoreId(0)->move($this->getIdByEposnowId($data->ParentId), null)->save();
            }
//        return $this->categoryFactory->create()->load($this->getIdByEposnowId($data->Id))->getParentId();
            return $categoryId;
        } else {
            return false;
        }
    }
    public function createCategory($row, $parent_id = 2)
    {
        if (substr($row->Name, -3) != '...') {
            $parentId = $parent_id;
            $parentCategory = $this->categoryFactory->create()->load($parentId);
            $category = $this->categoryFactory->create();
            $cate = $category->getCollection()
                ->addAttributeToFilter('name', $row->Name)
                ->addAttributeToFilter('eposnow_id', (int)$row->Id)
                ->getFirstItem();
            if (!$cate->getId()) {
                $category->setPath($parentCategory->getPath())
                    ->setParentId($parentId)
                    ->setName($row->Name)
                    ->setStoreId(0)
                    ->setEposnowId($row->Id)
                    ->setIsActive(true)
                    ->save();
            }
            return $category->getId();
        }
    }
    public function createCategories($eposnowData)
    {
        if ($count = $this->walkCategories($eposnowData, 2)) {
            return $count;
        } else {
            return false;
        }
    }
    public function walkCategories($eposnowData, $parent)
    {
        $count = 0;
        if ($eposnowData != [[]]) {
            foreach ($eposnowData as $dir) {
                if ($this->walkCategorySingle($dir, $parent)) {
                    $count++;
                }
            }
        }
        return $count;
    }
    public function walkCategorySingle($dir, $parent)
    {
        if (substr($dir->Name, -3) != '...') {
            $alreadyExists = false;
            foreach ($this->getAllCategories() as $category) {
                if ($category->getParentId() == $parent && $category->getName() == $dir->Name) {
                    $epos_id = $this->getEposNowId($category);
                    if (!isset($epos_id) && !($epos_id > 0)) {
                        $this->addEposCategoryId($category, $dir->Id);
                    }
                    $alreadyExists = true;
                }
            }
            if (!$alreadyExists) {
                if ($category_id = $this->createCategory($dir, $parent)) {
                    if (isset($dir->Children) && is_array($dir->Children)) {
                        $this->createChildCategories($dir->Children, $category_id);
                    }
                    return true;
                }
            } else if ($alreadyExists) {
                $this->updateCategory($dir);
                if (isset($dir->Children) && is_array($dir->Children)) {
                    $this->createChildCategories($dir->Children, $this->getIdByEposnowId($dir->Id));
                }
                return true;
            } else {
                return false;
            }
        }
        return false;
    }
    public function createChildCategories($children, $parent)
    {
        if ($children != [[]]) {
            foreach ($children as $dir) {
                if ($this->walkCategorySingle($dir, $parent)) {
                    return true;
                }
            }
        }
        return true;
    }
    public function getCategories($page, $pageSize = 10)
    {
        $categories = $this->collectionFactory->create();
        $categories->setPageSize($pageSize);
        $categories->addAttributeToSelect('*');
        $numberOfPages = $categories->getLastPageNumber();
        if ($numberOfPages >= $page) {
            $categories->setCurPage($page);
            $categories = $categories->load();
            return $categories;
        } else {
            return false;
        }
    }
    public function getAllCategories()
    {
        $categories = $this->collectionFactory->create();
        $categories->addAttributeToSelect('*');
        return $categories;
    }
    public function getCategoriesByName($name)
    {
        $category = $this->categoryFactory->create();
        $cate = $category->getCollection()
            ->addAttributeToFilter('name', $name)
            ->getFirstItem();
        if ($cate) {
            return $cate;
        } else {
            return false;
        }
    }
    public function deleteCategory($magentoId)
    {
        $this->registry->register("isSecureArea", true);
        $this->categoryRepository->get($magentoId)->delete();
        $this->registry->unregister("isSecureArea");
    }
    public function deleteCategories()
    {
        $categories = $this->categoryFactory->create()->getCollection();
        $this->registry->register("isSecureArea", true);
        foreach ($categories as $category) {
            if ($category->getId() > 2) {
                $category->delete();
            }
        }
        $this->registry->unregister("isSecureArea");
    }
    public function resyncCategories()
    {
        $this->deleteCategories();
        $categoryData = $this->eposCategoryService->getCategory();
        $this->createCategories($categoryData);
    }
    public function addEposCategoryId($category, $epos_id)
    {
        $category->setEposnowId($epos_id);
        $category->save();
        return true;
    }
}

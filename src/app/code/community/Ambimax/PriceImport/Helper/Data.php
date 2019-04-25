<?php

class Ambimax_PriceImport_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * @return bool
     */
    public function updateIndex()
    {
        return Mage::getStoreConfigFlag('ambimax_priceimport/options/update_index');
    }

    /**
     * @param array $productIds
     * @param $storeId
     */
    public function reindexProductFlatAndPrice(array $productIds, $storeId)
    {
        Mage::getResourceSingleton('catalog/product_indexer_price')->reindexProductIds($productIds);
        Mage::getResourceSingleton('catalog/product_flat_indexer')->rebuild($storeId);
    }

    /**
     * @param array $productIds
     */
    public function clearProductCache(array $productIds)
    {
        foreach ($productIds as $productId) {
            Mage::app()->cleanCache([Mage_Catalog_Model_Product::CACHE_TAG . '_' . $productId]);

            if (Mage::helper('core')->isModuleEnabled('Lesti_Fpc')) {
                Mage::getSingleton('fpc/fpc')->clean(sha1('product_' . $productId));
            }
        }
    }
}

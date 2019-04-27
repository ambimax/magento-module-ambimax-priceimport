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

    /**
     * @param string $specialToDate
     * @return bool
     */
    public function checkIfSpecialPriceDateIsValidate(string $specialToDate)
    {

        if ($this->getFormatedSpecialDate($specialToDate) > $this->getYMDFormattedCurrentDate()) {
            return true;
        }
        return false;
    }

    /**
     * @return false|string
     */
    public function getYMDFormattedCurrentDate()
    {
        return date('Y.m.d', Mage::getModel('core/date')->timestamp(time()));
    }

    /**
     * @param string $specialDate
     * @return false|string
     */
    public function getFormatedSpecialDate(string $specialDate)
    {
        return date('Y.m.d', Mage::getModel('core/date')->timestamp($specialDate));
    }

    /**
     * @param array $actualOffer
     * @param array $futureOffer
     * @return bool
     */
    public function checkIfNewOfferIsBetter(array $actualOffer, array $futureOffer)
    {
        if ($this->getYMDFormattedCurrentDate() > $this->getFormatedSpecialDate($futureOffer['special_from_date'])) {

            if (
                $this->getFormatedSpecialDate($futureOffer['special_to_date'])
                < $this->getFormatedSpecialDate($actualOffer['special_to_date'])
            ) {
                return true;
            }
        }
        return false;
    }
}

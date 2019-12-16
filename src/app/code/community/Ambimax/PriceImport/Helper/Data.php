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
    public function checkIfSpecialPriceDateIsValid($specialToDate)
    {
        if ($this->getFormattedSpecialDate($specialToDate) >= $this->getFormattedCurrentDate()) {
            return true;
        }
        return false;
    }

    /**
     * @return false|string
     */
    public function getFormattedCurrentDate($format = 'Y.m.d')
    {
        return date($format, Mage::getModel('core/date')->timestamp(time()));
    }

    /**
     * @param string $specialDate
     * @return false|string
     */
    public function getFormattedSpecialDate($specialDate, $format = 'Y.m.d')
    {
        return date($format, Mage::getModel('core/date')->timestamp($specialDate));
    }

    /**
     * @param array $actualOffer
     * @param array $futureOffer
     * @return bool
     */
    public function checkIfNewOfferIsBetter(array $actualOffer, array $futureOffer)
    {
        if ($this->getFormattedCurrentDate() > $this->getFormattedSpecialDate($futureOffer['special_from_date'])) {

            if (
                $this->getFormattedSpecialDate($futureOffer['special_to_date'])
                < $this->getFormattedSpecialDate($actualOffer['special_to_date'])
            ) {
                return true;
            }
        }
        return false;
    }
}

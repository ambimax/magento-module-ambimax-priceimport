<?php

class Ambimax_PriceImport_Helper_Data extends Mage_Core_Helper_Abstract
{

    protected $_import;

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

    /**
     * @param string $price
     * @return string|string[]
     */
    public function fixPriceFormat(string $price)
    {
        return str_replace(',', '.', $price);
    }

    public function getPriceBySku($sku, $default = 0): ?float
    {
        $sku = isset($sku['sku']) ? $sku['sku'] : $sku;
        return $this->getErpImporter(true)->getPriceDataValue($sku,'UVPINKL');
    }

    public function getErpImporter($load = false):Ambimax_PriceImport_Model_ErpImport
    {
        if ( !$this->_import ) {
            /** @var Ambimax_PriceImport_Model_ErpImport */
            $this->_import = Mage::getModel('ambimax_priceimport/erpimport');

            if ( $load ) {
                $this->_import->loadCsvData(Mage::getStoreConfig('ambimax_priceimport/erp_import_options/file_location'));
            }
        }
        return $this->_import;
    }

}

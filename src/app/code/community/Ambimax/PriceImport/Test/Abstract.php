<?php

class Ambimax_PriceImport_Test_Abstract extends EcomDev_PHPUnit_Test_Case
{
    /**
     * Prepare tests
     */
    public function setUp()
    {
        Mage::getSingleton('core/resource')->getConnection('core_write')->beginTransaction();

//        $productResource = Mage::getResourceModel('catalog/product');
//        $productResource->getWriteConnection()->query("DELETE FROM `{$productResource->getEntityTable()}`");
    }

    /**
     * Reset test environment
     */
    public function tearDown()
    {
        Mage::getSingleton('core/resource')->getConnection('core_write')->rollBack();
    }

    /**
     * Helper to provide same style as config.xml during ho_import
     *
     * @param $string
     * @return array
     */
    protected function _xmlValue($string)
    {
        return array('@' => array('value' => $string));
    }

    /**
     * Helper function to create local csv file
     *
     * @param $file
     * @param $content
     */
    protected function _createLocalCsvFile($file, $content, $delimiter = ',', $enclosure = '"')
    {
        $io = new Varien_Io_File();
        $io->setAllowCreateFolders(true);
        $io->cd(Mage::getBaseDir());
        $io->checkAndCreateFolder(dirname($file)); // @codingStandardsIgnoreLine
        $io->streamOpen($file);

        $columns = false;
        foreach ($content as $row) {
            if ( !$columns ) {
                $io->streamWriteCsv(array_keys($row), $delimiter, $enclosure);
                $columns = true;
            }
            $io->streamWriteCsv($row, $delimiter, $enclosure);
        }
        $io->streamClose();
    }

    /**
     * Loads products by sku and returns collection
     *
     * @param array $skuCollection
     * @return array
     */
    public function loadProducts(array $skuCollection, $storeId = null)
    {
        $products = array();
        foreach ($skuCollection as $sku) {
            /** @var Mage_Catalog_Model_Product $product */
            $product = Mage::getModel('catalog/product');
            if ( $storeId ) {
                $product->setStoreId($storeId);
            }
            $products[$sku] = $product->loadByAttribute('sku', $sku);
        }

        return $products;
    }

    /**
     * Loads stock items as collection
     *
     * @param array $productCollection
     * @return array
     */
    public function loadStockItems(array $productCollection)
    {
        $items = array();
        foreach ($productCollection as $sku => $product) {
            $items[$sku] = Mage::getModel('cataloginventory/stock_item')->loadByProduct($product);
        }

        return $items;
    }
}
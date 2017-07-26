<?php

class Ambimax_PriceImport_Test_Model_Import extends EcomDev_PHPUnit_Test_Case
{
    /**
     * Prepare tests
     */
    public function setUp()
    {
        Mage::getSingleton('core/resource')->getConnection('core_write')->beginTransaction();
    }

    /**
     * Reset test environment
     */
    public function tearDown()
    {
        Mage::getSingleton('core/resource')->getConnection('core_write')->rollBack();
    }

    /**
     * Test if disabling stock import prevents from importing
     */
    public function testNoRunWhenDisabled()
    {
        Mage::app()->getStore()->setConfig('ambimax_priceimport/options/enabled', 0);

        $this->assertFalse(Mage::getStoreConfigFlag('ambimax_priceimport/options/enabled'));

        $result = Mage::getModel('ambimax_priceimport/import')->run();
        $this->assertNull($result);
    }

    /**
     * @loadFixture delphin-products
     * @dataProvider dataProvider
     */
    public function testLoadDataCsvLocal($providerData)
    {
//        Mage::app()->getStore()->setConfig('ambimax_priceimport/options/enabled', 1);
//        Mage::app()->getStore()->setConfig('ambimax_priceimport/options/file_location', Ambimax_PriceImport_Model_Import::TYPE_LOCAL);
//        Mage::app()->getStore()->setConfig('ambimax_priceimport/options/file_path', 'var/tmp/ambimax_priceimporter.csv');
//        Mage::app()->getStore()->setConfig('catalog/price/scope', 1);
//
//        Mage::getConfig()->setNode('catalog/price/scope', 1);
//        Mage::app()->getConfig()->removeCache();
//        Mage::app()->getStore()->setConfig('catalog/price/scope', 1);

        $this->assertTrue(Mage::getStoreConfigFlag('ambimax_priceimport/options/enabled'));
        $this->assertEquals(Ambimax_PriceImport_Model_Import::TYPE_LOCAL, Mage::getStoreConfig('ambimax_priceimport/options/file_location'));
        $this->assertEquals('var/tmp/ambimax_priceimporter.csv', Mage::getStoreConfig('ambimax_priceimport/options/file_path'));
        $this->assertEquals(1, Mage::getStoreConfig('catalog/price/scope'));

        // setup local csv in var/tmp/
        $this->_createLocalCsvFile('var/tmp/ambimax_priceimporter.csv', $providerData);

        /** @var Ambimax_PriceImport_Model_Import $import */
        $import = Mage::getModel('ambimax_priceimport/import');

        $data = $import->loadCsvData();

        $this->assertArrayHasKey('german_website', $data);
        $this->assertArrayHasKey('website', current($data['german_website']));
        $this->assertArrayHasKey('sku', current($data['german_website']));
        $this->assertArrayHasKey('price', current($data['german_website']));

        // Test before

        /** @var Mage_Catalog_Model_Product $standardProduct */
        $product1 = Mage::getModel('catalog/product')->loadByAttribute('sku', '303297');
        $product2 = Mage::getModel('catalog/product')->loadByAttribute('sku', '303296');
        $product3 = Mage::getModel('catalog/product')->loadByAttribute('sku', '000331');

        $this->assertEquals(89, $product1->getPrice());
        $this->assertEquals(84, $product1->getSpecialPrice());
        $this->assertEquals('2017-01-01 00:00:00', $product1->getData('special_from_date'));
        $this->assertEquals('2049-12-31 00:00:00', $product1->getData('special_to_date'));
        $this->assertEquals(150, $product2->getPrice());
        $this->assertEquals(null, $product2->getSpecialPrice());
        $this->assertEquals(111, $product3->getPrice());

        $import->updatePrices();

        // Test store1
        $storeId = Mage::app()->getStore('germany')->getId();

        /** @var Mage_Catalog_Model_Product $standardProduct */
        $product1 = Mage::getModel('catalog/product')->setStoreId($storeId)->loadByAttribute('sku', '303297');
        $product2 = Mage::getModel('catalog/product')->setStoreId($storeId)->loadByAttribute('sku', '303296');
        $product3 = Mage::getModel('catalog/product')->setStoreId($storeId)->loadByAttribute('sku', '000331');

        $this->assertEquals(59, $product1->getPrice());
        $this->assertEquals(49, $product1->getSpecialPrice());
        $this->assertEquals('2017-06-12 00:00:00', $product1->getData('special_from_date'));
        $this->assertEquals('2049-07-31 00:00:00', $product1->getData('special_to_date'));

        $this->assertEquals(139.5, $product2->getPrice());
        $this->assertEquals(null, $product2->getSpecialPrice());
        $this->assertEquals(null, $product2->getData('special_from_date'));
        $this->assertEquals(null, $product2->getData('special_to_date'));

        $this->assertEquals(111, $product3->getPrice());
        $this->assertEquals(null, $product2->getSpecialPrice());
        $this->assertEquals(null, $product2->getData('special_from_date'));
        $this->assertEquals(null, $product2->getData('special_to_date'));

        // Test store2
        $storeId = Mage::app()->getStore('usa')->getId();

        /** @var Mage_Catalog_Model_Product $standardProduct */
        $product1 = Mage::getModel('catalog/product')->setStoreId($storeId)->loadByAttribute('sku', '303297');
        $product2 = Mage::getModel('catalog/product')->setStoreId($storeId)->loadByAttribute('sku', '303296');
        $product3 = Mage::getModel('catalog/product')->setStoreId($storeId)->loadByAttribute('sku', '000331');

        $this->assertEquals(89, $product1->getPrice());
        $this->assertEquals(84, $product1->getSpecialPrice());
        $this->assertEquals('2017-01-01 00:00:00', $product1->getData('special_from_date'));
        $this->assertEquals('2049-12-31 00:00:00', $product1->getData('special_to_date'));

        $this->assertEquals(150, $product2->getPrice());
        $this->assertEquals(null, $product2->getSpecialPrice());
        $this->assertEquals(null, $product2->getData('special_from_date'));
        $this->assertEquals(null, $product2->getData('special_to_date'));

        $this->assertEquals(99, $product3->getPrice());
        $this->assertEquals(97, $product3->getSpecialPrice());
        $this->assertEquals('2016-06-06 00:00:00', $product3->getData('special_from_date'));
        $this->assertEquals('2016-07-31 00:00:00', $product3->getData('special_to_date'));
    }

    public function testGetMysqlDate()
    {
        /** @var Ambimax_PriceImport_Model_Import $import */
        $import = Mage::getModel('ambimax_priceimport/import');

        $this->assertEquals('2000-12-31', $import->getMysqlDate('31.12.2000'));
        $this->assertEquals('2000-12-31', $import->getMysqlDate('31.12.2000 12:30'));
        $this->assertEquals('2017-03-12', $import->getMysqlDate('2017-03-12'));
        $this->assertEquals('2025-11-02', $import->getMysqlDate('2025/11/02'));
        $this->assertEquals(null, $import->getMysqlDate(''));
    }

    /**
     * @dataProvider dataProvider
     */
    public function testGetterAndSetter($data)
    {

        /** @var Ambimax_PriceImport_Model_Import $import */
        $import = Mage::getModel('ambimax_priceimport/import');

        $this->assertFalse($import->hasPriceData());
        $this->assertEquals(0, count($import->getPriceData()));

        $import->setPriceData($data, true);

        $this->assertTrue($import->hasPriceData());
        $this->assertEquals(1, count($import->getPriceData()));
        $this->assertEquals(2, count($import->getPriceData('website1')));

        $import->addPriceData('website1', array('sku' => 'test'));

        $this->assertEquals(1, count($import->getPriceData()));
        $this->assertEquals(3, count($import->getPriceData('website1')));
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
        $io->checkAndCreateFolder(dirname($file));
        $io->streamOpen($file);

        $columns = false;
        foreach($content as $row) {
            if( ! $columns) {
                $io->streamWriteCsv(array_keys($row), $delimiter, $enclosure);
                $columns = true;
            }
            $io->streamWriteCsv($row, $delimiter, $enclosure);
        }
        $io->streamClose();
    }
}
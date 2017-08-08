<?php

class Ambimax_PriceImport_Test_Model_Import extends Ambimax_PriceImport_Test_Abstract
{
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
     * @singleton ambimax_priceimport/import
     */
    public function testLoadDataCsvLocal($providerData)
    {
        $this->assertTrue(Mage::getStoreConfigFlag('ambimax_priceimport/options/enabled'));
        $this->assertEquals('local', Mage::getStoreConfig('ambimax_priceimport/options/file_location'));
        $this->assertEquals(
            'var/tmp/ambimax_priceimporter.csv',
            Mage::getStoreConfig('ambimax_priceimport/options/file_path')
        );
        $this->assertEquals(1, Mage::getStoreConfig('catalog/price/scope'));

        // setup local csv in var/tmp/
        $this->_createLocalCsvFile('var/tmp/ambimax_priceimporter.csv', $providerData);

        /** @var Ambimax_PriceImport_Model_Import $import */
        $import = Mage::getSingleton('ambimax_priceimport/import');

        $data = $import->loadCsvData();

        $this->assertArrayHasKey('german_website', $data);
        $this->assertArrayHasKey('website', current($data['german_website']));
        $this->assertArrayHasKey('sku', current($data['german_website']));
        $this->assertArrayHasKey('price', current($data['german_website']));

        // Test before
        // Build test products array
        $skuCollection = array('303297', '303296', '000331');
        $products = $this->loadProducts($skuCollection);

        $this->assertEquals(89, $products['303297']->getPrice());
        $this->assertEquals(84, $products['303297']->getSpecialPrice());
        $this->assertEquals('2017-01-01 00:00:00', $products['303297']->getData('special_from_date'));
        $this->assertEquals('2049-12-31 00:00:00', $products['303297']->getData('special_to_date'));
        $this->assertEquals(150, $products['303296']->getPrice());
        $this->assertEquals(null, $products['303296']->getSpecialPrice());
        $this->assertEquals(111, $products['000331']->getPrice());

        $import->updatePrices();

        // Test store1
        $storeId = Mage::app()->getStore('germany')->getId();

        // Build test products array
        $skuCollection = array('303297', '303296', '000331');
        $products = $this->loadProducts($skuCollection, $storeId);

        $this->assertEquals(59, $products['303297']->getPrice());
        $this->assertEquals(49, $products['303297']->getSpecialPrice());
        $this->assertEquals('2017-06-12 00:00:00', $products['303297']->getData('special_from_date'));
        $this->assertEquals('2049-07-31 00:00:00', $products['303297']->getData('special_to_date'));

        $this->assertEquals(139.5, $products['303296']->getPrice());
        $this->assertEquals(null, $products['303296']->getSpecialPrice());
        $this->assertEquals(null, $products['303296']->getData('special_from_date'));
        $this->assertEquals(null, $products['303296']->getData('special_to_date'));

        $this->assertEquals(111, $products['000331']->getPrice());
        $this->assertEquals(null, $products['303296']->getSpecialPrice());
        $this->assertEquals(null, $products['303296']->getData('special_from_date'));
        $this->assertEquals(null, $products['303296']->getData('special_to_date'));

        // Test store2
        $storeId = Mage::app()->getStore('usa')->getId();

        // Build test products array
        $skuCollection = array('303297', '303296', '000331');
        $products = $this->loadProducts($skuCollection, $storeId);

        $this->assertEquals(89, $products['303297']->getPrice());
        $this->assertEquals(84, $products['303297']->getSpecialPrice());
        $this->assertEquals('2017-01-01 00:00:00', $products['303297']->getData('special_from_date'));
        $this->assertEquals('2049-12-31 00:00:00', $products['303297']->getData('special_to_date'));

        $this->assertEquals(150, $products['303296']->getPrice());
        $this->assertEquals(null, $products['303296']->getSpecialPrice());
        $this->assertEquals(null, $products['303296']->getData('special_from_date'));
        $this->assertEquals(null, $products['303296']->getData('special_to_date'));

        $this->assertEquals(99, $products['000331']->getPrice());
        $this->assertEquals(97, $products['000331']->getSpecialPrice());
        $this->assertEquals('2016-06-06 00:00:00', $products['000331']->getData('special_from_date'));
        $this->assertEquals('2016-07-31 00:00:00', $products['000331']->getData('special_to_date'));
    }

    /**
     * @loadFixture delphin-products
     * @dataProvider dataProvider
     * @singleton ambimax_priceimport/observer
     * @singleton ambimax_priceimport/import
     */
    public function testCronjobRun($providerData)
    {
        $this->assertTrue(Mage::getStoreConfigFlag('ambimax_priceimport/options/enabled'));
        $this->assertEquals('local', Mage::getStoreConfig('ambimax_priceimport/options/file_location'));
        $this->assertEquals(
            'var/tmp/ambimax_priceimporter.csv',
            Mage::getStoreConfig('ambimax_priceimport/options/file_path')
        );
        $this->assertEquals(1, Mage::getStoreConfig('catalog/price/scope'));

        // setup local csv in var/tmp/
        $this->_createLocalCsvFile('var/tmp/ambimax_priceimporter.csv', $providerData);

        /** @var Ambimax_Priceimport_Model_Observer $observer */
        $observer = Mage::getSingleton('ambimax_priceimport/observer');
        $observer->import();

        /** @var Ambimax_PriceImport_Model_Import $observer */
        $importer = Mage::getSingleton('ambimax_priceimport/import');

        $data = $importer->getPriceData();

        $this->assertArrayHasKey('german_website', $data);
        $this->assertArrayHasKey('website', current($data['german_website']));
        $this->assertArrayHasKey('sku', current($data['german_website']));
        $this->assertArrayHasKey('price', current($data['german_website']));

        // Test store1
        $storeId = Mage::app()->getStore('germany')->getId();

        // Build test products array
        $skuCollection = array('303297', '303296', '000331');
        $products = $this->loadProducts($skuCollection, $storeId);

        $this->assertEquals(59, $products['303297']->getPrice());
        $this->assertEquals(49, $products['303297']->getSpecialPrice());
        $this->assertEquals('2017-06-12 00:00:00', $products['303297']->getData('special_from_date'));
        $this->assertEquals('2049-07-31 00:00:00', $products['303297']->getData('special_to_date'));

        $this->assertEquals(139.5, $products['303296']->getPrice());
        $this->assertEquals(null, $products['303296']->getSpecialPrice());
        $this->assertEquals(null, $products['303296']->getData('special_from_date'));
        $this->assertEquals(null, $products['303296']->getData('special_to_date'));

        $this->assertEquals(111, $products['000331']->getPrice());
        $this->assertEquals(null, $products['303296']->getSpecialPrice());
        $this->assertEquals(null, $products['303296']->getData('special_from_date'));
        $this->assertEquals(null, $products['303296']->getData('special_to_date'));

        // Test store2
        $storeId = Mage::app()->getStore('usa')->getId();

        // Build test products array
        $skuCollection = array('303297', '303296', '000331');
        $products = $this->loadProducts($skuCollection, $storeId);

        $this->assertEquals(89, $products['303297']->getPrice());
        $this->assertEquals(84, $products['303297']->getSpecialPrice());
        $this->assertEquals('2017-01-01 00:00:00', $products['303297']->getData('special_from_date'));
        $this->assertEquals('2049-12-31 00:00:00', $products['303297']->getData('special_to_date'));

        $this->assertEquals(150, $products['303296']->getPrice());
        $this->assertEquals(null, $products['303296']->getSpecialPrice());
        $this->assertEquals(null, $products['303296']->getData('special_from_date'));
        $this->assertEquals(null, $products['303296']->getData('special_to_date'));

        $this->assertEquals(99, $products['000331']->getPrice());
        $this->assertEquals(97, $products['000331']->getSpecialPrice());
        $this->assertEquals('2016-06-06 00:00:00', $products['000331']->getData('special_from_date'));
        $this->assertEquals('2016-07-31 00:00:00', $products['000331']->getData('special_to_date'));
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
}
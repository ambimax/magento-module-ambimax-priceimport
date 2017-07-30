<?php

class Ambimax_PriceImport_Model_Import extends Mage_Core_Model_Abstract
{
    const TYPE_LOCAL = 'local';
    const TYPE_URL = 'url';
    const TYPE_SFTP = 'sftp';

    const CACHE_TIME = 120;

    const ATTRIBUTE_CODE_SKU = 'sku';
    const ATTRIBUTE_CODE_WEBSITE = 'website';

    protected $_priceData = array();

    protected $_map = array(
        'website'           => 'shop',
        'sku'               => 'ARNR',
        'price'             => 'Detailpreis',
        'special_price'     => 'sonderpreis',
        'special_from_date' => 'start_sonderpreis',
        'special_to_date'   => 'ende_sonderpreis',
        'msrp'              => 'uvp',
    );

    /**
     * Import data into product database
     */
    public function run()
    {
        if ( !Mage::getStoreConfigFlag('ambimax_priceimport/options/enabled') ) {
            return;
        }

        if ( !$this->hasPriceData() ) {
            $this->loadCsvData();
        }

        $priceData = $this->getPriceData();
        if ( count($priceData) <= 1 ) {
            throw new Exception('Price import file not readable or has a wrong format');
        }

        $this->updatePrices();
    }

    /**
     * Updates prices set by setPriceDate function or by param
     *
     * @param null $data
     * @return $this
     */
    public function updatePrices($data = null)
    {
        if ( count($data) ) {
            $this->setPriceData($data);
        }

        if ( !$this->hasPriceData() ) {
            return $this;
        }

        foreach ($this->getPriceData() as $websiteCode => $products) {
            $website = Mage::app()->getWebsite($websiteCode);
            $storeId = $website->getDefaultStore()->getId();

            // Ensure product skus are always strings!!
            $productSkuCollection = array_map(array($this, 'convertToString'), array_keys($products));

            /** @var Mage_Catalog_Model_Resource_Product_Collection $collection */
            $collection = Mage::getResourceModel('catalog/product_collection')
                ->addAttributeToSelect(array('price', 'special_price', 'special_from_date', 'special_to_date'), 'left')
                ->addAttributeToFilter('sku', array('in' => $productSkuCollection))
                ->setStoreId($storeId);

            /** @var Mage_Catalog_Model_Product $product */
            foreach ($collection as $product) {

                $changes = $products[$product->getSku()];

                $priceFields = array(
                    'special_from_date' => $this->getMysqlDate($changes['special_from_date']),
                    'special_to_date'   => $this->getMysqlDate($changes['special_to_date']),
                    'price'             => $changes['price'],
                    'special_price'     => $changes['special_price'],
                );

                foreach ($priceFields as $key => $value) {
                    if ( $product->getData($key) == $value ) {
                        unset($priceFields[$key]);
                    }
                }

                $this->updateProductAttributes($product->getId(), $priceFields, $storeId);
            }
        }
    }

    /**
     * Returns item as string
     *
     * @param $item
     * @return string
     */
    public function convertToString($item)
    {
        return (string)$item;
    }

    /**
     * Return special date in database format
     *
     * @param null $input
     * @return null|string
     */
    public function getMysqlDate($input = null)
    {
        if ( !$input || empty($input) ) {
            return null;
        }

        $date = new DateTime($input);
        return $date->format('Y-m-d');
    }

    /**
     * Returns true when there is price data
     *
     * @return bool
     */
    public function hasPriceData()
    {
        return (bool)count($this->_priceData);
    }

    /**
     * Get price data
     *
     * @param null $website
     * @return array
     */
    public function getPriceData($website = null)
    {
        if ( $website ) {
            return isset($this->_priceData[$website]) ? $this->_priceData[$website] : null;
        }
        return $this->_priceData;
    }

    /**
     * Add price data
     *
     * @param $website
     * @param $data
     * @return $this
     */
    public function addPriceData($website, $data)
    {
        if ( !empty($website) && !empty($data) ) {
            $sku = $data['sku'];
            $this->_priceData[$website][$sku] = $data;
        }
        return $this;
    }

    /**
     * Set price data
     *
     * @param $data
     * @return $this
     */
    public function setPriceData($data, $doMapping = true)
    {
        if ( $doMapping ) {
            $result = array();
            $map = array_flip($this->_map);
            foreach ($data as $sku => $values) {
                $item = array();
                foreach ($values as $field => $value) {
                    $field = isset($map[$field]) ? $map[$field] : $field;
                    $item[$field] = $value;
                }

                if ( !isset($item['sku']) ) {
                    throw new Exception('Sku field not defined');
                }
                $sku = $item['sku'];
                $website = $item['website'];
                $result[$website][$sku] = $item;
            }
            $data = $result;
        }
        $this->_priceData = $data;
        return $this;
    }

    /**
     * Save updated product attributes
     *
     * @param Mage_Catalog_Model_Product|int|array $productIds
     * @param array $attributes
     * @param int $storeId
     * @return $this
     */
    public function updateProductAttributes($productIds, array $attributes, $storeId = 0)
    {
        if ( $productIds instanceof Mage_Catalog_Model_Product ) {
            $productIds = $productIds->getId();
        }

        if ( !is_array($productIds) ) {
            $productIds = array($productIds);
        }

        /** @var Mage_Catalog_Model_Resource_Product_Action $action */
        $action = Mage::getModel('catalog/resource_product_action');
        $action->updateAttributes($productIds, $attributes, $storeId);

        return $this;
    }


    /**
     * Locates csv-file and return content
     */
    public function loadCsvData()
    {
        // @codingStandardsIgnoreStart
        $io = new Varien_Io_File();
        switch (Mage::getStoreConfig('ambimax_priceimport/options/file_location')) {
            case Ambimax_PriceImport_Model_Import::TYPE_URL:
                $io->streamOpen(Mage::getStoreConfig('ambimax_priceimport/options/url_path'), 'r');
                break;
            case Ambimax_PriceImport_Model_Import::TYPE_LOCAL:
                $destination = Mage::getStoreConfig('ambimax_priceimport/options/file_path');
                $io->open(array('path' => dirname($destination)));
                $io->streamOpen(basename($destination), 'r');
                break;
            case Ambimax_PriceImport_Model_Import::TYPE_SFTP:
                $destination = $this->_downloadSftpFile();
                $io->open(array('path' => dirname($destination)));
                $io->streamOpen(basename($destination), 'r');
                break;
        }
        // @codingStandardsIgnoreEnd

        $data = array();
        $columns = null;
        $map = array_flip($this->_map);
        while (false !== ($csvLine = $io->streamReadCsv(',', '""'))) {

            if ( !$columns ) {
                foreach ($csvLine as $field) {
                    $columns[] = isset($map[$field]) ? $map[$field] : $field;
                }
                continue;
            }

            //build row array
            $row = array_combine($columns, $csvLine);
            $website = $row['website'];
            $sku = $row['sku'];
            $data[$website][$sku] = $row;
        }
        $this->setPriceData($data, false);
        return $data;
    }

    /**
     * Download file from the SFTP server
     *
     * @return string
     */
    protected function _downloadSftpFile()
    {
        $destination = Mage::getBaseDir().DS;
        $destination .= trim(Mage::getStoreConfig('ambimax_priceimport/options/file_sftp_tmp'), '/');

        // @codingStandardsIgnoreStart
        if (
            is_file($destination)
            && filesize($destination)
            && (time() - @filemtime($destination)) <= self::CACHE_TIME
        ) {
            return $destination;
        }
        // @codingStandardsIgnoreEnd

        $options = array(
            '{host}'     => Mage::getStoreConfig('ambimax_priceimport/options/file_sftp_host'),
            '{username}' => Mage::getStoreConfig('ambimax_priceimport/options/file_sftp_username'),
            '{password}' => Mage::getStoreConfig('ambimax_priceimport/options/file_sftp_password'),
            '{path}'     => Mage::getStoreConfig('ambimax_priceimport/options/file_sftp_path'),
        );

        // Ensure writeable folder exists
        // @codingStandardsIgnoreStart
        $io = new Varien_Io_File();
        $io->checkAndCreateFolder(dirname($destination));

        $connectionString = str_replace(array_keys($options), $options, 'sftp://{username}:{password}@{host}{path}');

        $fp = fopen($destination, 'w+');//This is the file where we save the information
        $ch = curl_init($connectionString);
        curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_SFTP);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_exec($ch);
        curl_close($ch);
        fclose($fp);
        // @codingStandardsIgnoreEnd

        return $destination;
    }
}
<?php

class Ambimax_PriceImport_Model_Import extends Mage_Core_Model_Abstract
{
    const TYPE_LOCAL = 'local';
    const TYPE_URL = 'url';
    const TYPE_SFTP = 'sftp';

    const CACHE_TIME  = 120;

    const ATTRIBUTE_CODE_SKU = 'sku';
    const ATTRIBUTE_CODE_WEBSITE = 'website';

    protected $_priceData = array();

    protected $_map = array(
        'website' => 'shop',
        'sku' => 'ARNR',
        'price' => 'Detailpreis',
        'special_price' => 'sonderpreis',
        'special_from_date' => 'start_sonderpreis',
        'special_to_date' => 'ende_sonderpreis',
        'msrp' => 'uvp',
    );

    /**
     * Import data into product database
     *
     */
    public function run()
    {
        if (!Mage::getStoreConfigFlag('priceimport_options/options/enabled')) {
            echo("Price Import ist disabled \n");
            return;
        }

        $this->_priceData = $this->_getCsvData();

        if (count($this->_priceData) <= 1) {
            throw new Exception ('Price import file is not readable or has a wrong format');
        }

        foreach ($this->_priceData as $website => $product) {
            $this->_saveAttributesPerWebsite($website, $product);

        }
        exit;

        $this->_saveNewPrice();

    }


    /**
     * locates csv-file and return content
     *
     */
    public function _getCsvData()
    {
        $io = new Varien_Io_File();
        switch (Mage::getStoreConfig('priceimport_options/options/file_location')) {
            case Ambimax_PriceImport_Model_Import::TYPE_URL:
                $io->streamOpen(Mage::getStoreConfig('priceimport_options/options/url_path'), 'r');
                break;
            case Ambimax_PriceImport_Model_Import::TYPE_LOCAL:
                $destination = Mage::getStoreConfig('priceimport_options/options/file_path');
                $io->open(array('path' => dirname($destination)));
                $io->streamOpen(basename($destination), 'r');
                break;
            case Ambimax_PriceImport_Model_Import::TYPE_SFTP:
                $destination = $this->_downloadSftpFile();
                $io->open(array('path' => dirname($destination)));
                $io->streamOpen(basename($destination), 'r');
                break;
        }
        $data = array();
        $columns = null;
        $map = array_flip($this->_map);
        while (false !== ($csvLine = $io->streamReadCsv(',', '""'))) {

            if (!$columns) {
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
        return $data;
    }


    /**
     * save attributes for each website
     *
     * @param $websiteCode
     * @param $productCollection
     */
    protected function _saveAttributesPerWebsite($websiteCode, $productCollection)
    {
        $website = Mage::app()->getWebsite($websiteCode);
        $storeId = $website->getDefaultStore()->getId();

        foreach ($productCollection as $item) {

            /** @var Mage_Catalog_Model_Product $product */
            $product = Mage::getModel('catalog/product')->loadByAttribute('sku', $item['sku']);
            $product->setStoreId($storeId);

            foreach ($item as $key => $value) {
                $product->setData($key, $value);
            }
            $product->save();
        }
    }

    protected function _downloadSftpFile()
    {
        $destination = Mage::getBaseDir().DS.trim(Mage::getStoreConfig('priceimport_options/options/file_sftp_tmp'), '/');

        if( is_file($destination) && filesize($destination) && (time()-@filemtime($destination)) <= self::CACHE_TIME) {
            return $destination;
        }

        $options = array(
            '{host}' => Mage::getStoreConfig('priceimport_options/options/file_sftp_host'),
            '{username}' => Mage::getStoreConfig('priceimport_options/options/file_sftp_username'),
            '{password}' => Mage::getStoreConfig('priceimport_options/options/file_sftp_password'),
            '{path}' => Mage::getStoreConfig('priceimport_options/options/file_sftp_path'),
        );

        // Ensure writeable folder exists
        $io = new Varien_Io_File();
        $io->checkAndCreateFolder(dirname($destination));

        $connectionString = str_replace(array_keys($options), $options, 'sftp://{username}:{password}@{host}{path}');

        $fp = fopen($destination, 'w+');//This is the file where we save the information
        $ch = curl_init($connectionString);
        curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_SFTP);
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_exec($ch);
        curl_close ($ch);
        fclose($fp);
        return $destination;
    }
}
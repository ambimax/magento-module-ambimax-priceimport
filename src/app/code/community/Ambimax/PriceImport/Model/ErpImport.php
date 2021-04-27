<?php

class Ambimax_PriceImport_Model_ErpImport extends Mage_Core_Model_Abstract
{
    const TYPE_LOCAL = 'local';
    const TYPE_URL = 'url';
    const TYPE_SFTP = 'sftp';
    const TYPE_S3 = 's3';

    const CACHE_TIME = 120;

    const ATTRIBUTE_CODE_SKU = 'sku';
    const ATTRIBUTE_CODE_WEBSITE = 'website';

    protected $_priceData = array();

    //not fully used at the moment, use it as an example if you want to change the used Importfile
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
     * Set price data
     *
     * @param $data
     * @param bool $doMapping
     * @return $this
     * @throws Exception
     */
    public function setPriceData($data, $doMapping = true)
    {
        if ($doMapping) {
            $result = array();
            $map = array_flip($this->_map);
            foreach ($data as $sku => $values) {
                $item = array();
                foreach ($values as $field => $value) {
                    $field = isset($map[$field]) ? $map[$field] : $field;
                    $item[$field] = $value;
                }

                if (!isset($item['sku'])) {
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
     * Locates csv-file and return content
     *
     * @param $fileLocation
     * @return array
     * @throws Exception
     */
    public function loadCsvData($fileLocation)
    {
        $helper = Mage::helper('ambimax_priceimport');
        $io = $this->getCsvStream($fileLocation);

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
            if (!$sku = $row['sku']) {
                continue;
            }

            if (!$helper->checkIfSpecialPriceDateIsValid($row['special_to_date'])) {
                continue;
            }
            $row['UVPINKL'] = $helper->fixPriceFormat($row['UVPINKL']);
            $row['UVPINKL'] = $helper->fixPriceFormat($row['UVPINKL']);

            if (!empty($data[$website][$sku])) {
                if (!$helper->checkIfNewOfferIsBetter($data[$website][$sku], $row)) {
                    continue;
                }
            }

            $data['de'][$sku] = $row;
        }
        $this->setPriceData($data, false);
        return $data;
    }

    /**
     * @param $additional boolean
     * @return string
     */
    public function getLocalFilePath()
    {
        return Mage::getStoreConfig('ambimax_priceimport/erp_import_options/file_path');
    }

    /**
     * Download file from the SFTP server
     *
     * @return string
     * @throws Exception
     */
    protected function _downloadSftpFile()
    {
        $destination = Mage::getBaseDir() . DS;

        $destination .= trim(Mage::getStoreConfig('ambimax_priceimport/erp_import_options/file_sftp_tmp'), '/');

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
            '{host}' => Mage::getStoreConfig('ambimax_priceimport/erp_import_options/file_sftp_host'),
            '{username}' => Mage::getStoreConfig('ambimax_priceimport/erp_import_options/file_sftp_username'),
            '{password}' => Mage::getStoreConfig('ambimax_priceimport/erp_import_options/file_sftp_password'),
            '{path}' => Mage::getStoreConfig('ambimax_priceimport/erp_import_options/file_sftp_path'),
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

    /**
     * @param $fileLocation
     * @return Varien_Io_File
     * @throws Exception
     */
    public function getCsvStream($fileLocation)
    {
        $awsHelper = Mage::helper('ambimax_priceimport/downloader_s3');
        $io = new Varien_Io_File();
        // @codingStandardsIgnoreStart

        switch ($fileLocation) {
            case Ambimax_PriceImport_Model_Import::TYPE_URL:
                $io->streamOpen(Mage::getStoreConfig('ambimax_priceimport/erp_import_options/url_path'), 'r');
                break;
            case Ambimax_PriceImport_Model_Import::TYPE_LOCAL:
                $destination = $this->getLocalFilePath();
                $io->open(array('path' => dirname($destination)));
                $io->streamOpen(basename($destination), 'r');
                break;
            case Ambimax_PriceImport_Model_Import::TYPE_SFTP:
                $destination = $this->_downloadSftpFile();
                $io->open(array('path' => dirname($destination)));
                $io->streamOpen(basename($destination), 'r');
                break;
            case Ambimax_PriceImport_Model_Import::TYPE_S3:
                $destination = $awsHelper->download();
                $io->open(array('path' => dirname($destination)));
                $io->streamOpen(basename($destination), 'r');
                break;
            default:
                throw new Exception('No valide file location selected!');
        }
        return $io;
        // @codingStandardsIgnoreEnd
    }

    public function getPriceDataValue($sku, $key, $default = null): ?float
    {
        if ($sku instanceof Mage_Catalog_Model_Product) {
            $sku = $sku->getSku();
        }
        if (isset($this->_priceData['de'][$sku][$key])) {
            return $this->roundPrice($this->_priceData['de'][$sku][$key]);
        }

        return $default;
    }

    public function roundPrice(float $price): ?float
    {
        return round($price * 2, 1) / 2;
    }

}
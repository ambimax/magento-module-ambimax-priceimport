<?php

class Ambimax_PriceImport_Helper_Data extends Mage_Core_Helper_Abstract
{

    protected $_import;
    protected $_listsuffix = "-fwpf-";

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

    public function getPriceBySku(array $productInformation): ?float
    {
        $sku = $productInformation['sku'];

        if (strpos($sku, 'G') !== false) {
            return 0;
        }
        $price = $productInformation['price'];
        $erpPrice = $this->getErpImporter(true)->getPriceDataValue($sku, 'price');
        $price = isset($erpPrice) ? $erpPrice : $price;
        if (!is_numeric($price)) {
            throw new Exception('price is not set:' . $sku);
        }
        return $price;
    }

    public function getErpImporter(bool $load = false): Ambimax_PriceImport_Model_ErpImport
    {
        if (!$this->_import) {
            /** @var Ambimax_PriceImport_Model_ErpImport */
            $this->_import = Mage::getModel('ambimax_priceimport/erpImport');

            if ($load) {
                $this->_import->loadCsvData();
            }
        }
        return $this->_import;
    }

    public function getCurrentFile(): array
    {
        $fileNames = $this->getFileNames();

        return $this->getNewestFiles($fileNames);
    }

    public function getFileNames(): array
    {
        $connectionInforamtions = $this->_getConnectionInformations();

        //@codingStandardsIgnoreStart
        $connectionId = ftp_connect($connectionInforamtions['serverAddress']);
        if (!ftp_login($connectionId, $connectionInforamtions['username'], $connectionInforamtions['password'])) {
            $this->throwLoginError($connectionInforamtions['serverAddress'], $connectionInforamtions['username']);
        }

        ftp_pasv($connectionId, true);
        $fileNames = $this->prepareFileNames(ftp_nlist($connectionId, $connectionInforamtions['path']));

        ftp_close($connectionId);
        //@codingStandardsIgnoreEnd

        return $fileNames;
    }

    /**
     * @return array
     */
    protected function _getConnectionInformations(): array
    {
        $connectionConfig = array(
            'username' => $this->getFtpUser(),
            'password' => $this->getFtpPassword(),
            'serverAddress' => $this->getFtpServerAddress(),
            'path' => $this->getFtpFilesLocation(),
        );

        return $connectionConfig;
    }

    protected function getFtpUser(): string
    {

        return Mage::getStoreConfig('ambimax_priceimport/erp_import_options/file_sftp_username');

    }

    protected function getFtpPassword(): string
    {

        return Mage::getStoreConfig('ambimax_priceimport/erp_import_options/file_sftp_password');

    }

    protected function getFtpServerAddress(): string
    {

        return Mage::getStoreConfig('ambimax_priceimport/erp_import_options/file_sftp_host');

    }

    protected function getFtpFilesLocation(): string
    {

        return Mage::getStoreConfig('ambimax_priceimport/erp_import_options/file_sftp_path');

    }

    protected function getFtpFileNames(): string
    {

        return Mage::getStoreConfig('ambimax_priceimport/erp_import_options/file_sftp_name');

    }

    /**
     * @param $originalFilenames
     */
    public function prepareFileNames($originalFilenames)
    {
        if (empty($originalFilenames)) {
            return;
        }
        if (!empty($originalFilenames)) {
            foreach ($originalFilenames as $name) {
                $filenames[$name] = substr($name, strripos($name, '/') + 1);
            }
        }

        return $filenames;
    }

    public function getNewestFiles(array $allFileNames): array
    {
        $fileName = $this->cleanFileNames($allFileNames);
        $onlyPetfriendsFileName = $this->cleanFileNames($allFileNames, 1);
        $fileNames = [];
        array_push($fileNames, $fileName, $onlyPetfriendsFileName);
        return $fileNames;
    }

    /*
     * use $hasSuffix = 1 for list with suffix
     */
    public function cleanFileNames(array $fileNames, int $hasSuffix = 0): string
    {
        if ($hasSuffix) {
            $fileNames = array_filter($fileNames, array($this, 'isOnlyPetfriendsFile'));
        } else $fileNames = array_filter($fileNames, array($this, 'isPriceFile'));

        sort($fileNames);
        $newestFileName = array_pop($fileNames);
        if (!empty($fileNames)) {
            $this->cleanFtpFiles($fileNames);
        }
        return $newestFileName;
    }

    public function cleanFtpFiles(array $fileNames): void
    {
        foreach ($fileNames as $filename) {
            $this->deleteFtpFiles($this->getFtpFilesLocation() . $filename);
        }
    }

    public function deleteFtpFiles($filePath): void
    {
        $connectionInforamtions = $this->_getConnectionInformations();

        //@codingStandardsIgnoreStart
        $connectionId = ftp_connect($connectionInforamtions['serverAddress']);
        if (!ftp_login($connectionId, $connectionInforamtions['username'], $connectionInforamtions['password'])) {
            $this->throwLoginError($connectionInforamtions['serverAddress'], $connectionInforamtions['username']);
        }
        ftp_delete($connectionId, $filePath);
        ftp_close($connectionId);
        //@codingStandardsIgnoreEnd
    }

    public function isPriceFile(string $fileName): bool
    {
        $fileNameSetting = $this->getFtpFileNames();
        if (!str_starts_with($fileName, $fileNameSetting)) {
            return false;
        }
        if (!str_starts_with($fileName, $fileNameSetting . $this->_listsuffix)) {
            return true;
        }
        return false;
    }

    public function isOnlyPetfriendsFile(string $fileName): bool
    {
        $fileNameSetting = $this->getFtpFileNames() . $this->_listsuffix;
        if (!str_starts_with($fileName, $fileNameSetting)) {
            return false;
        }
        return true;
    }


}

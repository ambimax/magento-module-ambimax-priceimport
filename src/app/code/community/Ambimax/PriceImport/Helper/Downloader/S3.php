<?php

class Ambimax_PriceImport_Helper_Downloader_S3
{
    protected $_client;

    public function download()
    {
        $target = Mage::getBaseDir() . DS . Mage::getStoreConfig('ambimax_priceimport/options/file_path');

        $connectionInfo = $this->getConnectionInfo();

        $client = $this->getClient($connectionInfo->getData('profile'));

        $client->getObject(
            array('Bucket' => $this->getBucket(), 'Key' => $connectionInfo->getFile(), 'SaveAs' => $target)
        );

        return $target;
    }

    /**
     * @return Varien_Object
     */
    public function getConnectionInfo()
    {
        $profile = Mage::getStoreConfig('ambimax_priceimport/options/s3_profile');
        $sourceFile = Mage::getStoreConfig('ambimax_priceimport/options/s3_file');

        $connectionInfo = new Varien_Object();

        $connectionInfo->setData('profile', Mage::getStoreConfig('ambimax_priceimport/options/s3_profile'));
        $connectionInfo->setData('bucket', $this->getBucket());
        $connectionInfo->setData('file', Mage::getStoreConfig('ambimax_priceimport/options/s3_file'));

        return $connectionInfo;
    }

    /**
     * @return string
     */
    public function getBucket()
    {
        return Mage::getStoreConfig('ambimax_priceimport/options/s3_bucket');
    }

    /**
     * Get aws s3 client
     *
     * @return Aws\S3\S3Client
     */
    public function getClient($profile)
    {
        if ( !$this->_client ) {
            $this->_client = Mage::helper('ambimax_import/aws')->getClient($profile);
        }
        return $this->_client;
    }
}

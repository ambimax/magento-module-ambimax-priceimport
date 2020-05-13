<?php

class Ambimax_PriceImport_Model_System_Config_Source_File_Location
{
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return array(
            array(
                'value' => Ambimax_PriceImport_Model_Import::TYPE_LOCAL,
                'label'=>Mage::helper('ambimax_priceimport')->__('Local')
            ),
            array(
                'value' => Ambimax_PriceImport_Model_Import::TYPE_URL,
                'label'=>Mage::helper('ambimax_priceimport')->__('Url')
            ),
            array(
                'value' => Ambimax_PriceImport_Model_Import::TYPE_SFTP,
                'label'=>Mage::helper('ambimax_priceimport')->__('S/FTP')
            ),
            array(
                'value' => Ambimax_PriceImport_Model_Import::TYPE_S3,
                'label'=>Mage::helper('ambimax_priceimport')->__('S3')
            ),
        );
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        return array(
            Ambimax_PriceImport_Model_Import::TYPE_LOCAL => Mage::helper('ambimax_priceimport')->__('Local'),
            Ambimax_PriceImport_Model_Import::TYPE_URL => Mage::helper('ambimax_priceimport')->__('Url'),
            Ambimax_PriceImport_Model_Import::TYPE_SFTP => Mage::helper('ambimax_priceimport')->__('S/FTP'),
        );
    }

}
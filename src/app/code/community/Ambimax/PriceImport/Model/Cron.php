<?php

class Ambimax_PriceImport_Model_Cron
{
    public function additionPriceImport()
    {
        if (!Mage::getStoreConfigFlag('ambimax_priceimport/options/enabled')) {
            return;
        }

        if (!Mage::getStoreConfigFlag('ambimax_priceimport/options/additional_enabled')) {
            return;
        }

        $importer = Mage::getModel('ambimax_priceimport/import')->runPriceImport(true);
    }
}
<?php

class Ambimax_Priceimport_Model_Observer
{
    /**
     * Import price data
     */
    public function import()
    {
        Mage::getSingleton('ambimax_priceimport/import')->runPriceImport();
    }
}
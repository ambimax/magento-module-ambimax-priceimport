
# ambimax® PriceImport

[![Build Status](https://travis-ci.org/ambimax/magento-module-ambimax-priceimport.svg?branch=master)](https://travis-ci.org/ambimax/magento-module-ambimax-priceimport)

This modules handles:
 - Import of priceimport.csv by file, url or sftp

## Description

With this module you can import prices, special prices
(incl. start and endtime) and msrp from different products 
for different websites.


A product can appear as often as desired in the list. 
The module selects the entry with the earliest end date. 
Thus several offers can be maintained in a list.

## Install

For installation use composer, modman or copy files manually.

### Composer

```
"require": {
    "ambimax/magento-module-ambimax-priceimport": "^2.2.0"
}
```

### Set configuration

Login into Admin-Panel.

Switch to ```System-> Configuration-> Catalog-> Price Import Options```.

Enable module, set cronjob timer and choose file location.

### Import by file

Type the absolute path of the file (incl. the file itself)

### Import by url

Type the url of the destination file.

### Import by sftp

All entries are needed.

SFTP File Path: file path on the host system (incl. the file itself).

SFTP Tmp File Path: file path on the target system. Started from the root path.

### Import by AWS S3

Configure AWS settings und set locale path to save the file from S3 storage.

## License

[MIT License](http://choosealicense.com/licenses/mit/)

## Author Information

 - Julian Bour, [ambimax® GmbH](https://www.ambimax.de)
 - Tobias Schifftner, [ambimax® GmbH](https://www.ambimax.de)

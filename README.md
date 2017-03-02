
# ambimax® PriceImporter

This modules handles:
 - Import of priceimport.csv by file, url or sftp
 
## Description

With this module you can import prices, special prices
(incl. start and endtime) and msrp from different products 
for different websites.

## Install

For installation use composer, modman or copy files manually.

### Composer

```
"require": {
    "ambimax/magento-module-ambimax-priceimporter": "~1.0"
}
```

### Set configuration

Login into Admin-Panel

Switch to System-> Configuration-> Catalog-> Price Import Options

Enable module, set cronjob timer and chose file location


##### Import by file

Type the absolute path of the file(incl. the file itself)

##### Import by url

Type the url of the destination file

##### Import by sftp

All entries are needed

SFTP File Path: file path on the host system(incl. the file itself)

SFTP Tmp File Path: file path on the target system. Started from the root path.

## License

[MIT License](http://choosealicense.com/licenses/mit/)

## Author Information

 - Julian Bour, [ambimax® GmbH](https://www.ambimax.de)

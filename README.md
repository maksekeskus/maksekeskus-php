maksekeskus-php
===============

#Installation

## Composer

``` json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/maksekeskus/maksekeskus-php"
        }
    ],
    "require": {
        "maksekeskus/maksekeskus-php": "v1.4.5"
    }
}
```

## Prebuilt packages

Download the packaged library form the repository [releases]
(https://github.com/maksekeskus/maksekeskus-php/releases/).

Unpack it into your project folder (i.e. /htdocs/myshop/ )
and include the libarary file ( i.e. /htdocs/myshop/Maksekeskus-1.4.5/Maksekeskus.php )

Get your API keys from [merchant.maksekeskus.ee](https://merchant.maksekeskus.ee) or [merchant.test.maksekeskus.ee](https://merchant.test.maksekeskus.ee)

# Example

``` php
<?php

require __DIR__ . '/maksekeskus-1.4.5/vendor/autoload.php'; //Comment this line out if you are using Composer to build your project

use Maksekeskus\Maksekeskus;

// get your API keys from merchant.test.maksekeskus.ee or merchant.maksekeskus.ee
$shopId = '12ee0036-3719-...-9a8b-51f5770190ca';
$KeyPublishable = '5wCSE2B2OAV6...cpe2N1kZQzCXNTe';
$KeySecret = 'JvH2IZ6W6fvKB7W7...ea3BLWgqcfbhQKEN1w2UDrua3sWlojPGfhp';

// use TRUE if working against the Test environment
// see https://makecommerce.net/en/for-developers/test-environment/
$MK = new Maksekeskus($shopId,$KeyPublishable,$KeySecret,TRUE);

$context["currency"]="eur";
$context["country"]="ee";

$data = $MK->getPaymentMethods($context);

print "<pre>";
print_r($data);
print "</pre>";

?>
```

See more examples on https://developer.maksekeskus.ee/ 



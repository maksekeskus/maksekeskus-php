maksekeskus-php
===============

Maksekeskus PHP SDK

API documentation: http://docs.maksekeskus.apiary.io 
and more on https://maksekeskus.ee/api-explorer/

Place the lib under your project folder:  /lib/Maksekeskus.php

Use the composer to install dependencies declared in /lib/composer.json 
(https://getcomposer.org/)


Get your API keys from [metchant.maksekeskus.ee](https://metchant.maksekeskus.ee) or [merchant-test.maksekeskus.ee](https://metchant-test.maksekeskus.ee)

And off you go:
``` php
<?php
 include_once 'lib/Maksekeskus.php';

// get your own API keys from metchant.maksekeskus.ee
$MK = new Maksekeskus(
    '12ee0036-3719-...-9a8b-51f5770190ca',  // ShopID
    '5wCSE2B2OAV64jt...pe2N1kZQzCXNTe',     // Api Key Publishable 
    'JvH2IZ6W6fvKB7W....a3BLWgqcfbhQKEN1w2UDrua3sWlojPGfhp',    // API Key Secret
     TRUE   // use TRUE if work against the Test environment 
    	  // see https://makecommerce.net/en/for-developers/test-environment/
);

$context["currency"]="eur";
$context["country"]="ee";

$data = $MK->getPaymentMethods( $context ) ;

print "<pre>";
print_r($data);
print "</pre>";

?>

```



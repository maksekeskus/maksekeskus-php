maksekeskus-php
===============


See API documentation: http://docs.maksekeskus.apiary.io 
and more on https://maksekeskus.ee/api-explorer/intro.php

Place the lib under your project folder:  /lib/Maksekeskus.php

Use the composer to install dependencies declared in /lib/composer.json 
(https://getcomposer.org/)


Get your API keys from [merchant.maksekeskus.ee](https://merchant.maksekeskus.ee) or [merchant-test.maksekeskus.ee](https://merchant-test.maksekeskus.ee)

And off you go:
``` php
<?php
 include_once 'lib/Maksekeskus.php';

// get your API keys from metchant.maksekeskus.ee
$shopId = '12ee0036-3719-4edb-9a8b-51f5770190ca';
$KeyPublishable = '5wCSE2B2OAV64jtfYcpe2N1kZQzCXNTe';
$KeySecret = 'JvH2IZ6W6fvKB7W74UGeQNS1490Kpea3BLWgqcfbhQKEN1w2UDrua3sWlojPGfhp';

// use TRUE if work against the Test environment 
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



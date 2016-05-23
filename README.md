maksekeskus-php
===============

Download the packaged library form the repository [releases]
(https://github.com/maksekeskus/maksekeskus-php/releases/).

Unpack it into your project folder (i.e. /htdocs/myshop/ )
and include the libarary file ( i.e. /htdocs/myshop/Maksekeskus-1.0/Maksekeskus.php )

Get your API keys from [merchant.maksekeskus.ee](https://merchant.maksekeskus.ee) or [merchant-test.maksekeskus.ee](https://merchant-test.maksekeskus.ee)

And off you go:
``` php
<?php
 include_once 'Maksekeskus-1.0/Maksekeskus.php';

// get your API keys from metchant.maksekeskus.ee
$shopId = '12ee0036-3719-...-9a8b-51f5770190ca';
$KeyPublishable = '5wCSE2B2OAV6...cpe2N1kZQzCXNTe';
$KeySecret = 'JvH2IZ6W6fvKB7W7...ea3BLWgqcfbhQKEN1w2UDrua3sWlojPGfhp';

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

See more examples on https://maksekeskus.ee/api-explorer/intro.php 

and API documentation: http://docs.maksekeskus.apiary.io 


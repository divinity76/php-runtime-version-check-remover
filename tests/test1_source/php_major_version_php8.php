<?php
$pkHandle = openssl_pkey_new();
if (PHP_MAJOR_VERSION < 8) {
    openssl_pkey_free($pkHandle);
}

if ( defined( 'E_DEPRECATED' ) ) {
	define( 'QM_E_DEPRECATED', E_DEPRECATED );
} else {
	define( 'QM_E_DEPRECATED', 0 );
}

if ( defined( 'E_USER_DEPRECATED' ) ) {
	define( 'QM_E_USER_DEPRECATED', E_USER_DEPRECATED );
} else {
	define( 'QM_E_USER_DEPRECATED', 0 );
}

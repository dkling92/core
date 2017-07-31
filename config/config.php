<?php
$CONFIG = array (
    'debug' => true,
  

     'trusted_domains' =>
          array (
            'https://owncloudphp-owncloud.apps.openshift.aman.de', 
            'https://master.openshift.aman.de:8443'),

    'datadirectory' => '/var/www/owncloud/data',

    'dbtype' => 'mysql',

    'dbhost' => '8080',
    
    'dbname' => 'owncloud',

    'dbuser' => 'userOJT',

    'dbpassword' => 'okS30dvVmrGTJJiO',

    'installed' => true,

    'default_language' => 'de',

    'updatechecker' => true,

    'updater.server.url' => 'https://updates.owncloud.com/server/',

    'has_internet_connection' => true,

    'check_for_working_htaccess' => true,

    'config_is_read_only' => false,

    'log_type' => 'owncloud',

    'logfile' => 'owncloud.log',
/**
    'openssl' => array(
        'config' => '/absolute/location/of/openssl.cnf',),

    'dbdriveroptions' => array(
        PDO::MYSQL_ATTR_SSL_CA => '/file/path/to/ca_cert.pem',), 
*/
    'supportedDatabases' => array(
        'sqlite',
        'mysql',
        'pgsql',
        'oci',),



);
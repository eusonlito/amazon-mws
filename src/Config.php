<?php declare(strict_types=1);

namespace AmazonMws;

use Exception;

class Config
{
    /**
     * @var array
     */
    protected static $config = [
        'Seller_Id' => null,
        'Marketplace_Id' => null,
        'Access_Key_ID' => null,
        'Secret_Access_Key' => null,
        'MWSAuthToken' => null,
        'Application_Version' => '0.0.*',
    ];

    /**
     * @var array
     */
    protected static $marketplaces = [
        'A2EUQ1WTGCTBG2' => 'mws.amazonservices.ca',
        'ATVPDKIKX0DER' => 'mws.amazonservices.com',
        'A1AM78C64UM0Y8' => 'mws.amazonservices.com.mx',
        'A1PA6795UKMFR9' => 'mws-eu.amazonservices.com',
        'A1RKKUPIHCS9HS' => 'mws-eu.amazonservices.com',
        'A13V1IB3VIYZZH' => 'mws-eu.amazonservices.com',
        'A21TJRUUN4KGV' => 'mws.amazonservices.in',
        'APJ6JRA9NG5V4' => 'mws-eu.amazonservices.com',
        'A1F83G8C2ARO7P' => 'mws-eu.amazonservices.com',
        'A1VC38T7YXB528' => 'mws.amazonservices.jp',
        'AAHKV2X7AFYLW' => 'mws.amazonservices.com.cn',
        'A39IBJ37TRP1C6' => 'mws.amazonservices.com.au',
        'A2Q3Y263D00KWC' => 'mws.amazonservices.com',
    ];

    /**
     * @var array
     */
    protected static $requiredKeys = [
        'Marketplace_Id',
        'Seller_Id',
        'Access_Key_ID',
        'Secret_Access_Key'
    ];

    /**
     * @param array
     *
     * @return void
     */
    public static function set(array $config): void
    {
        static::$config = array_merge(static::$config, $config);

        foreach (static::$requiredKeys as $key) {
            if (empty(static::$config[$key])) {
                throw new Exception('Required field '.$key.' is not set');
            }
        }

        if (empty(static::$marketplaces[$config['Marketplace_Id']])) {
            throw new Exception('Invalid Marketplace Id');
        }

        static::$config['marketplaces'] = static::$marketplaces;
        static::$config['Application_Name'] = Constant::APPLICATION_NAME;
        static::$config['Region_Host'] = static::$marketplaces[$config['Marketplace_Id']];
        static::$config['Region_Url'] = 'https://'.static::$config['Region_Host'];
    }

    /**
     * @param string $key
     *
     * @return mixed
     */
    public static function get(string $key)
    {
        return static::$config[$key];
    }
}

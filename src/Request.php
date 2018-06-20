<?php declare (strict_types = 1);

namespace AmazonMws;

use Exception;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\BadResponseException;

class Request
{
    /**
     * @var \GuzzleHttp\Client
     */
    protected static $client;

    /**
     * @param \GuzzleHttp\Client $client
     *
     * @return void
     */
    public static function setClient(GuzzleClient $client): void
    {
        static::$client = $client;
    }

    /**
     * Request MWS
     *
     * @param string $endPoint
     * @param array $query = []
     * @param string $body = null
     * @param bool $raw = false
     *
     * @return mixed
     */
    public static function request(string $endPoint, array $query = [], string $body = null, bool $raw = false)
    {
        $endPoint = EndPoint::get($endPoint);

        $query += [
            'Timestamp' => gmdate(Constant::DATE_FORMAT, time()),
            'AWSAccessKeyId' => Config::get('Access_Key_ID'),
            'Action' => $endPoint['action'],
            'MarketplaceId' => Config::get('Marketplace_Id'),
            'MarketplaceId.Id.1' => Config::get('Marketplace_Id'),
            'SellerId' => Config::get('Seller_Id'),
            'SignatureMethod' => Constant::SIGNATURE_METHOD,
            'SignatureVersion' => Constant::SIGNATURE_VERSION,
            'Version' => $endPoint['date'],
        ];

        if (Config::get('MWSAuthToken')) {
            $query['MWSAuthToken'] = Config::get('MWSAuthToken');
        }

        try {
            return static::exec($endPoint, $query, $body, $raw);
        } catch (BadResponseException $e) {
            return static::error($e);
        }
    }

    /**
     * @param array $endPoint
     * @param array $query = []
     * @param string $body = null
     * @param bool $raw = false
     *
     * @return mixed
     */
    protected static function exec(array $endPoint, array $query = [], string $body = null, bool $raw = false)
    {
        $headers = [
            'Accept' => 'application/xml',
            'x-amazon-user-agent' => Config::get('Application_Name').'/'.Config::get('Application_Version'),
        ];

        if ($endPoint['action'] === 'SubmitFeed') {
            $headers['Content-MD5'] = base64_encode(md5($body, true));
            $headers['Content-Type'] = 'text/xml; charset=iso-8859-1';
            $headers['Host'] = Config::get('Region_Host');

            unset($query['MarketplaceId.Id.1'], $query['SellerId']);
        }

        $requestOptions = [
            'headers' => $headers,
            'body' => $body,
        ];

        ksort($query);

        $query['Signature'] = static::getSignature($endPoint, $query);

        $requestOptions['query'] = $query;

        if (static::$client === null) {
            static::$client = new GuzzleClient;
        }

        $response = static::$client->request(
            $endPoint['method'],
            Config::get('Region_Url').$endPoint['path'],
            $requestOptions
        );

        $body = (string)$response->getBody();

        if ($raw) {
            return $body;
        }

        if (strpos(strtolower($response->getHeader('Content-Type')[0]), 'xml') === false) {
            return $body;
        }

        return static::xmlToArray($body)[$endPoint['action'].'Result'] ?? [];
    }

    /**
     * @param BadResponseException $e
     *
     * @throws Exception
     */
    protected static function error(BadResponseException $e)
    {
        if (!$e->hasResponse()) {
            throw new Exception('An error occured');
        }

        $message = $e->getResponse()->getBody();

        if (strpos($message, '<ErrorResponse') !== false) {
            $message = simplexml_load_string($message)->Error->Message;
        }

        throw new Exception($message);
    }

    /**
     * @param array $endPoint
     * @param array $query = []
     *
     * @return string
     */
    protected static function getSignature(array $endPoint, array $query): string
    {
        $string = $endPoint['method']
            ."\n".Config::get('Region_Host')
            ."\n".$endPoint['path']
            ."\n".http_build_query($query, null, '&', PHP_QUERY_RFC3986);

        return base64_encode(hash_hmac('sha256', $string, Config::get('Secret_Access_Key'), true));
    }
}

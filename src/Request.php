<?php declare (strict_types = 1);

namespace AmazonMWS;

class Request
{
    /**
     * Request MWS
     *
     * @param string $endpoint
     * @param array $query = []
     * @param string $body = null
     * @param bool $raw = false
     *
     * @return mixed
     */
    public static function request(string $endpoint, array $query = [], string $body = null, bool $raw = false)
    {
        $endpoint = EndPoint::get($endpoint);

        $query += [
            'Timestamp' => gmdate(Constant::DATE_FORMAT, time()),
            'AWSAccessKeyId' => Config::get('Access_Key_ID'),
            'Action' => $endpoint['action'],
            'MarketplaceId' => Config::get('Marketplace_Id'),
            'MarketplaceId.Id.1' => Config::get('Marketplace_Id'),
            'SellerId' => Config::get('Seller_Id'),
            'SignatureMethod' => Constant::SIGNATURE_METHOD,
            'SignatureVersion' => Constant::SIGNATURE_VERSION,
            'Version' => $endpoint['date'],
        ];

        if (Config::get('MWSAuthToken')) {
            $query['MWSAuthToken'] = Config::get('MWSAuthToken');
        }

        return static::exec($endpoint, array_filter($query), $body, $raw);
    }

    /**
     * @param array $endpoint
     * @param array $query = []
     * @param string $body = null
     * @param bool $raw = false
     *
     * @return mixed
     */
    protected static function exec(array $endpoint, array $query = [], string $body = null, bool $raw = false)
    {
        $headers = [
            'Accept' => 'application/xml',
            'x-amazon-user-agent' => Config::get('Application_Name').'/'.Config::get('Application_Version'),
        ];

        if ($endpoint['action'] === 'SubmitFeed') {
            $headers['Content-MD5'] = base64_encode(md5($body, true));
            $headers['Content-Type'] = 'text/xml; charset=iso-8859-1';
            $headers['Host'] = Config::get('Region_Host');

            unset($query['MarketplaceId.Id.1'], $query['SellerId']);
        }

        ksort($query);

        $query['Signature'] = static::getSignature($endpoint, $query);

        $curl = new Curl;

        $response = $curl
            ->setUrl(Config::get('Region_Url').$endpoint['path'])
            ->setHeaders($headers)
            ->setMethod($endpoint['method'])
            ->setBody($body)
            ->setQuery($query)
            ->exec();

        if ($raw) {
            return $response;
        }

        if (strstr($curl->getInfo('content_type'), '/xml') === false) {
            return $response;
        }

        return Xml::toArray($response)[$endpoint['action'].'Result'] ?? [];
    }

    /**
     * @param array $endpoint
     * @param array $query = []
     *
     * @return string
     */
    protected static function getSignature(array $endpoint, array $query): string
    {
        $string = $endpoint['method']
            ."\n".Config::get('Region_Host')
            ."\n".$endpoint['path']
            ."\n".http_build_query($query, '', '&', PHP_QUERY_RFC3986);

        return base64_encode(hash_hmac('sha256', $string, Config::get('Secret_Access_Key'), true));
    }
}

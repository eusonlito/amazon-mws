<?php declare(strict_types=1);

namespace MCS;

use DateTime;
use Exception;
use SplTempFileObject;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use League\Csv\Reader;
use League\Csv\Writer;
use Spatie\ArrayToXml\ArrayToXml;
use MCS\MWSEndPoint;

class MWSClient
{
    protected const SIGNATURE_METHOD = 'HmacSHA256';
    protected const SIGNATURE_VERSION = '2';
    protected const DATE_FORMAT = 'Y-m-d\\TH:i:s.\\0\\0\\0\\Z';
    protected const APPLICATION_NAME = 'MCS/MwsClient';

    protected $config = [
        'Seller_Id' => null,
        'Marketplace_Id' => null,
        'Access_Key_ID' => null,
        'Secret_Access_Key' => null,
        'MWSAuthToken' => null,
        'Application_Version' => '0.0.*',
    ];

    protected $marketplaceIds = [
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

    protected $requiredKeys = [
        'Marketplace_Id',
        'Seller_Id',
        'Access_Key_ID',
        'Secret_Access_Key'
    ];

    protected $debugNextFeed = false;
    protected $client = null;

    public function __construct(array $config)
    {
        foreach ($config as $key => $value) {
            if (array_key_exists($key, $this->config)) {
                $this->config[$key] = $value;
            }
        }

        foreach ($this->requiredKeys as $key) {
            if (empty($this->config[$key])) {
                throw new Exception('Required field '.$key.' is not set');
            }
        }

        if (empty($this->marketplaceIds[$this->config['Marketplace_Id']])) {
            throw new Exception('Invalid Marketplace Id');
        }

        $this->config['Application_Name'] = self::APPLICATION_NAME;
        $this->config['Region_Host'] = $this->marketplaceIds[$this->config['Marketplace_Id']];
        $this->config['Region_Url'] = 'https://'.$this->config['Region_Host'];
    }

    /**
     * Call this method to get the raw feed instead of sending it
     */
    public function debugNextFeed()
    {
        $this->debugNextFeed = true;
    }

    /**
     * A method to quickly check if the supplied credentials are valid
     *
     * @return bool
     */
    public function validateCredentials(): bool
    {
        try {
            $this->ListOrderItems('validate');
        } catch (Exception $e) {
            return ($e->getMessage() === 'Invalid AmazonOrderId: validate');
        }
    }

    /**
     * Returns the current competitive price of a product, based on ASIN.
     *
     * @param array $asinList = []
     *
     * @return array
     */
    public function getCompetitivePricingForASIN(array $asinList = []): array
    {
        if (count($asinList) > 20) {
            throw new Exception('Maximum amount of ASIN\'s for this call is 20');
        }

        $counter = 1;
        $query = [];

        foreach ($asinList as $key) {
            $query['ASINList.ASIN.'.($counter++)] = $key;
        }

        $response = $this->request('GetCompetitivePricingForASIN', $query);

        if (empty($response['GetCompetitivePricingForASINResult'])) {
            return [];
        }

        $response = $this->responseByKeys($response['GetCompetitivePricingForASINResult']);

        $array = [];

        foreach ($response as $product) {
            $price = $product['Product']['CompetitivePricing']['CompetitivePrices']['CompetitivePrice']['Price'] ?? false;

            if ($price) {
                $array[$product['Product']['Identifiers']['MarketplaceASIN']['ASIN']] = $price;
            }
        }

        return $array;
    }

    /**
     * Returns the current competitive price of a product, based on SKU.
     *
     * @param array $skuList = []
     *
     * @return array
     */
    public function getCompetitivePricingForSKU(array $skuList = []): array
    {
        if (count($skuList) > 20) {
            throw new Exception('Maximum amount of SKU\'s for this call is 20');
        }

        $counter = 1;
        $query = [];

        foreach ($skuList as $key) {
            $query['SellerSKUList.SellerSKU.'.($counter++)] = $key;
        }

        $response = $this->request('GetCompetitivePricingForSKU', $query);

        if (empty($response['GetCompetitivePricingForSKUResult'])) {
            return [];
        }

        $response = $this->responseByKeys($response['GetCompetitivePricingForSKUResult']);

        $array = [];

        foreach ($response as $product) {
            $price = $product['Product']['CompetitivePricing']['CompetitivePrices']['CompetitivePrice']['Price'] ?? false;

            if ($price === false) {
                continue;
            }

            $sku = $product['Product']['Identifiers']['SKUIdentifier']['SellerSKU'];

            $array[$sku]['Price'] = $price;
            $array[$sku]['Rank'] = $product['Product']['SalesRankings']['SalesRank'][1];
        }

        return $array;
    }

    /**
     * Returns lowest priced offers for a single product, based on ASIN.
     *
     * @param string $asin
     * @param string $ItemCondition = 'New' - Should be one in: New, Used, Collectible, Refurbished, Club
     *
     * @return array
     */
    public function getLowestPricedOffersForASIN(string $asin, string $ItemCondition = 'New'): array
    {
        return $this->request('GetLowestPricedOffersForASIN', [
            'ASIN' => $asin,
            'ItemCondition' => $ItemCondition,
        ]);
    }

    /**
     * Returns pricing information for your own offer listings, based on SKU.
     *
     * @param array  $skuList = []
     * @param string $ItemCondition = ''
     *
     * @return array
     */
    public function getMyPriceForSKU(array $skuList = [], string $ItemCondition = ''): array
    {
        if (count($skuList) > 20) {
            throw new Exception('Maximum amount of SKU\'s for this call is 20');
        }

        $counter = 1;
        $query = [];

        if ($ItemCondition) {
            $query['ItemCondition'] = $ItemCondition;
        }

        foreach ($skuList as $key) {
            $query['SellerSKUList.SellerSKU.'.($counter++)] = $key;
        }

        $response = $this->request('GetMyPriceForSKU', $query);

        if (empty($response['GetMyPriceForSKUResult'])) {
            return [];
        }

        $response = $this->responseByKeys($response['GetMyPriceForSKUResult']);

        $array = [];

        foreach ($response as $product) {
            $attributes = $product['@attributes'];
            $sku = $attributes['SellerSKU'];

            if (empty($attributes['status']) || ($attributes['status'] !== 'Success')) {
                $array[$sku] = false;
            } else {
                $array[$sku] = $product['Product']['Offers']['Offer'] ?? [];
            }
        }

        return $array;
    }

    /**
     * Returns pricing information for your own offer listings, based on ASIN.
     *
     * @param array $asinList = []
     * @param string $ItemCondition = ''
     *
     * @return array
     */
    public function getMyPriceForASIN(array $asinList = [], string $ItemCondition = ''): array
    {
        if (count($asinList) > 20) {
            throw new Exception('Maximum amount of SKU\'s for this call is 20');
        }

        $counter = 1;
        $query = [];

        if ($ItemCondition) {
            $query['ItemCondition'] = $ItemCondition;
        }

        foreach ($asinList as $key) {
            $query['ASINList.ASIN.'.($counter++)] = $key;
        }

        $response = $this->request('GetMyPriceForASIN', $query);

        if (empty($response['GetMyPriceForASINResult'])) {
            return [];
        }

        $response = $this->responseByKeys($response['GetMyPriceForASINResult']);

        $array = [];

        foreach ($response as $product) {
            $attributes = $product['@attributes'];

            if (isset($attributes['status']) && ($attributes['status'] === 'Success')) {
                $array[$attributes['ASIN']] = $product['Product']['Offers']['Offer'] ?? false;
            } else {
                $array[$attributes['ASIN']] = false;
            }
        }

        return $array;
    }

    /**
     * Returns pricing information for the lowest-price active offer listings for up to 20 products, based on ASIN.
     *
     * @param array $asinList = [] array of ASIN values
     * @param string $ItemCondition = '' Should be one in: New, Used, Collectible, Refurbished, Club. Default: All
     *
     * @return array
     */
    public function getLowestOfferListingsForASIN(array $asinList = [], string $ItemCondition = ''): array
    {
        if (count($asinList) > 20) {
            throw new Exception('Maximum amount of ASIN\'s for this call is 20');
        }

        $counter = 1;
        $query = [];

        if ($ItemCondition) {
            $query['ItemCondition'] = $ItemCondition;
        }

        foreach ($asinList as $key) {
            $query['ASINList.ASIN.'.($counter++)] = $key;
        }

        $response = $this->request('GetLowestOfferListingsForASIN', $query);

        if (empty($response['GetLowestOfferListingsForASINResult'])) {
            return [];
        }

        $response = $this->responseByKeys($response['GetLowestOfferListingsForASINResult']);

        $array = [];

        foreach ($response as $product) {
            $asin = $product['Product']['Identifiers']['MarketplaceASIN']['ASIN'];
            $array[$asin] = $product['Product']['LowestOfferListings']['LowestOfferListing'] ?? false;
        }

        return $array;
    }

    /**
     * Returns orders created or updated during a time frame that you specify.
     *
     * @param object DateTime $from, beginning of time frame
     * @param boolean $allMarketplaces, list orders from all marketplaces
     * @param array $states = ['Unshipped', 'PartiallyShipped'], an array containing orders states you want to filter on
     * @param array $FulfillmentChannel = ['MFN']
     * @param object DateTime $till = null, end of time frame
     *
     * @return array
     */
    public function ListOrders(
        DateTime $from,
        bool $allMarketplaces = false,
        array $states = ['Unshipped', 'PartiallyShipped'],
        array $fulfillmentChannels = ['MFN'],
        DateTime $till = null
    ): array {
        $query = ['CreatedAfter' => gmdate(self::DATE_FORMAT, $from->getTimestamp())];

        if ($till) {
            $query['CreatedBefore'] = gmdate(self::DATE_FORMAT, $till->getTimestamp());
        }

        $counter = 1;

        foreach ($states as $status) {
            $query['OrderStatus.Status.'.($counter++)] = $status;
        }

        if ($allMarketplaces) {
            $counter = 1;

            foreach ($this->marketplaceIds as $key => $value) {
                $query['MarketplaceId.Id.'.($counter++)] = $key;
            }
        }

        if ($fulfillmentChannels) {
            $counter = 1;

            foreach ($fulfillmentChannels as $fulfillmentChannel) {
                $query['FulfillmentChannel.Channel.'.($counter++)] = $fulfillmentChannel;
            }
        }

        $response = $this->request('ListOrders', $query);

        $list = $response['ListOrdersResult'] ?? [];

        if (empty($list['Orders']['Order'])) {
            return [];
        }

        if (empty($list['NextToken'])) {
            return $this->responseByKeys($list['Orders']['Order']);
        }

        $data['ListOrders'] = $list['Orders']['Order'];
        $data['NextToken'] = $list['NextToken'];

        return $data;
    }

    /**
     * Returns orders created or updated during a time frame that you specify.
     *
     * @param string $nextToken
     *
     * @return array
     */
    public function ListOrdersByNextToken(string $nextToken): array
    {
        $response = $this->request('ListOrdersByNextToken', ['NextToken' => $nextToken]);

        if (empty($response['ListOrdersByNextTokenResult']['Orders']['Order'])) {
            return [];
        }

        $list = $response['ListOrdersByNextTokenResult'];

        if (empty($list['NextToken'])) {
            $this->responseByKeys($list['Orders']['Order']);
        }

        $data['ListOrders'] = $list['Orders']['Order'];
        $data['NextToken'] = $list['NextToken'];

        return $data;
    }

    /**
     * Returns an order based on the AmazonOrderId values that you specify.
     *
     * @param string $AmazonOrderId
     *
     * @return ?array if the order is found, null if not
     */
    public function getOrder(string $AmazonOrderId): ?array
    {
        $response = $this->request('GetOrder', ['AmazonOrderId.Id.1' => $AmazonOrderId]);

        return $response['GetOrderResult']['Orders']['Order'] ?? null;
    }

    /**
     * Returns order items based on the AmazonOrderId that you specify.
     *
     * @param string $AmazonOrderId
     *
     * @return array
     */
    public function ListOrderItems(string $AmazonOrderId): array
    {
        $response = $this->request('ListOrderItems', ['AmazonOrderId' => $AmazonOrderId]);
        $result = array_values($response['ListOrderItemsResult']['OrderItems']);

        return isset($result[0]['QuantityOrdered']) ? $result : $result[0];
    }

    /**
     * Returns the parent product categories that a product belongs to, based on SellerSKU.
     *
     * @param string $SellerSKU
     *
     * @return ?array if found, null if not found
     */
    public function getProductCategoriesForSKU(string $SellerSKU): ?array
    {
        $result = $this->request('GetProductCategoriesForSKU', ['SellerSKU' => $SellerSKU]);

        return $result['GetProductCategoriesForSKUResult']['Self'] ?? null;
    }

    /**
     * Returns the parent product categories that a product belongs to, based on ASIN.
     *
     * @param string $ASIN
     *
     * @return ?array if found, null if not found
     */
    public function getProductCategoriesForASIN(string $ASIN): ?array
    {
        $result = $this->request('GetProductCategoriesForASIN', ['ASIN' => $ASIN]);

        return $result['GetProductCategoriesForASINResult']['Self'] ?? null;
    }

    /**
     * Returns a list of products and their attributes, based on a list of ASIN, GCID, SellerSKU, UPC, EAN, ISBN, and JAN values.
     *
     * @param array $asinList A list of id's
     * @param string [$type = 'ASIN']  the identifier name
     *
     * @return array
     */
    public function getMatchingProductForId(array $asinList, string $type = 'ASIN'): array
    {
        $asinList = array_unique($asinList);

        if (count($asinList) > 5) {
            throw new Exception('Maximum number of id\'s = 5');
        }

        $counter = 1;
        $query = ['IdType' => $type];

        foreach ($asinList as $asin) {
            $query['IdList.Id.'.($counter++)] = $asin;
        }

        $response = $this->request('GetMatchingProductForId', $query, null, true);

        $languages = ['de-DE', 'en-EN', 'es-ES', 'fr-FR', 'it-IT', 'en-US'];
        $replace = ['</ns2:ItemAttributes>' => '</ItemAttributes>'];

        foreach ($languages as $language) {
            $replace['<ns2:ItemAttributes xml:lang="'.$language.'">'] = '<ItemAttributes><Language>'.$language.'</Language>';
        }

        $replace['ns2:'] = '';

        $response = $this->xmlToArray(strtr($response, $replace));

        $list = $response['GetMatchingProductForIdResult'] ?? [];

        if (isset($list['@attributes'])) {
            $list = [$list];
        }

        $response = [
            'found' => [],
            'not_found' => [],
        ];

        if (empty($list) || !is_array($list)) {
            return $response;
        }

        foreach ($list as $result) {
            $asin = $result['@attributes']['Id'];

            if ($result['@attributes']['status'] !== 'Success') {
                $response['not_found'][] = $asin;
                continue;
            }

            if (isset($result['Products']['Product']['AttributeSets'])) {
                $products[0] = $result['Products']['Product'];
            } else {
                $products = $result['Products']['Product'];
            }

            foreach ($products as $product) {
                $array = [];

                if (isset($product['Identifiers']['MarketplaceASIN']['ASIN'])) {
                    $array['ASIN'] = $product['Identifiers']['MarketplaceASIN']['ASIN'];
                }

                $attributes = $product['AttributeSets']['ItemAttributes'];

                foreach ($attributes as $key => $value) {
                    if (is_string($key) && is_string($value)) {
                        $array[$key] = $value;
                    }
                }

                if (isset($attributes['Feature'])) {
                    $array['Feature'] = $attributes['Feature'];
                }

                if (isset($attributes['PackageDimensions'])) {
                    $array['PackageDimensions'] = array_map('floatval', $attributes['PackageDimensions']);
                }

                if (isset($attributes['ListPrice'])) {
                    $array['ListPrice'] = $attributes['ListPrice'];
                }

                if (isset($attributes['SmallImage'])) {
                    $image = $attributes['SmallImage']['URL'];

                    $array['medium_image'] = $image;
                    $array['small_image'] = str_replace('._SL75_', '._SL50_', $image);
                    $array['large_image'] = str_replace('._SL75_', '', $image);
                }

                if (isset($product['Relationships']['VariationParent']['Identifiers']['MarketplaceASIN']['ASIN'])) {
                    $array['Parentage'] = 'child';
                    $array['Relationships'] = $product['Relationships']['VariationParent']['Identifiers']['MarketplaceASIN']['ASIN'];
                }

                if (isset($product['Relationships']['VariationChild'])) {
                    $array['Parentage'] = 'parent';
                }

                if (isset($product['SalesRankings']['SalesRank'])) {
                    $array['SalesRank'] = $product['SalesRankings']['SalesRank'];
                }

                $response['found'][$asin][] = $array;
            }
        }

        return $response;
    }

    /**
     * Returns a list of products and their attributes, ordered by relevancy, based on a search query that you specify.
     *
     * @param string $query the open text query
     * @param string $searchContextId = '' the identifier for the context within which the given search will be performed. see: http://docs.developer.amazonservices.com/en_US/products/Products_QueryContextIDs.html
     *
     * @return array
     */
    public function ListMatchingProducts(string $search, string $searchContextId = ''): array
    {
        if (trim($search) === '') {
            throw new Exception('Missing query');
        }

        $query = [
            'Query' => urlencode($search),
            'QueryContextId' => $searchContextId,
        ];

        $response = $this->request('ListMatchingProducts', $query, null, true);

        $languages = ['de-DE', 'en-EN', 'es-ES', 'fr-FR', 'it-IT', 'en-US'];
        $replace = ['</ns2:ItemAttributes>' => '</ItemAttributes>'];

        foreach ($languages as $language) {
            $replace['<ns2:ItemAttributes xml:lang="'.$language.'">'] = '<ItemAttributes><Language>'.$language.'</Language>';
        }

        $replace['ns2:'] = '';

        $response = $this->xmlToArray(strtr($response, $replace));

        if (empty($response['ListMatchingProductsResult'])) {
            return ['ListMatchingProductsResult' => []];
        }

        return $response['ListMatchingProductsResult'];
    }

    /**
     * Returns a list of reports that were created in the previous 90 days.
     *
     * @param array $ReportTypeList = []
     *
     * @return array
     */
    public function getReportList(array $ReportTypeList = []): array
    {
        $query = [];
        $counter = 1;

        foreach ($ReportTypeList as $ReportType) {
            $query['ReportTypeList.Type.'.($counter++)] = $ReportType;
        }

        return $this->request('GetReportList', $query);
    }

    /**
     * Returns your active recommendations for a specific category or for all categories for a specific marketplace.
     *
     * @param string $recommendationCategory = [] - One of: Inventory, Selection, Pricing, Fulfillment, ListingQuality, GlobalSelling, Advertising
     *
     * @return ?array
     */
    public function ListRecommendations(array $recommendationCategory = []): ?array
    {
        $query = [];

        if ($recommendationCategory) {
            $query['RecommendationCategory'] = $recommendationCategory;
        }

        $result = $this->request('ListRecommendations', $query);

        return $result['ListRecommendationsResult'] ?? null;
    }

    /**
     * Returns a list of marketplaces that the seller submitting the request can sell in, and a list of participations that include seller-specific information in that marketplace
     *
     * @return array
     */
    public function ListMarketplaceParticipations(): array
    {
        $result = $this->request('ListMarketplaceParticipations');

        return $result['ListMarketplaceParticipationsResult'] ?? $result;
    }

    /**
     * Delete product's based on SKU
     *
     * @param string $array array containing sku's
     *
     * @return array feed submission result
     */
    public function deleteProductBySKU(array $array): array
    {
        $feed = [
            'MessageType' => 'Product',
            'Message' => [],
        ];

        foreach ($array as $sku) {
            $feed['Message'][] = [
                'MessageID' => rand(),
                'OperationType' => 'Delete',
                'Product' => ['SKU' => $sku],
            ];
        }

        return $this->submitFeed('_POST_PRODUCT_DATA_', $feed);
    }

    /**
     * Update a product's stock quantity
     *
     * @param array $array array containing sku as key and quantity as value
     *
     * @return array feed submission result
     */
    public function updateStock(array $array): array
    {
        $feed = [
            'MessageType' => 'Inventory',
            'Message' => [],
        ];

        foreach ($array as $sku => $quantity) {
            $feed['Message'][] = [
                'MessageID' => rand(),
                'OperationType' => 'Update',
                'Inventory' => [
                    'SKU' => $sku,
                    'Quantity' => (int) $quantity,
                ],
            ];
        }

        return $this->submitFeed('_POST_INVENTORY_AVAILABILITY_DATA_', $feed);
    }

    /**
     * Update a product's stock quantity
     *
     * @param array $array array containing arrays with next keys: [sku, quantity, latency]
     *
     * @return array feed submission result
     */
    public function updateStockWithFulfillmentLatency(array $array): array
    {
        $feed = [
            'MessageType' => 'Inventory',
            'Message' => [],
        ];

        foreach ($array as $item) {
            $feed['Message'][] = [
                'MessageID' => rand(),
                'OperationType' => 'Update',
                'Inventory' => [
                    'SKU' => $item['sku'],
                    'Quantity' => (int) $item['quantity'],
                    'FulfillmentLatency' => $item['latency'],
                ],
            ];
        }

        return $this->submitFeed('_POST_INVENTORY_AVAILABILITY_DATA_', $feed);
    }

    /**
     * Update a product's price
     * Dates in DateTime object
     * Price has to be formatted as XSD Numeric Data Type (http://www.w3schools.com/xml/schema_dtypes_numeric.asp)
     *
     * @param array $standardprice an array containing sku as key and price as value
     * @param array $salesprice an optional array with sku as key and value consisting of an array with key/value pairs for SalePrice, StartDate, EndDate
     *
     * @return array feed submission result
     */
    public function updatePrice(array $standardprice, array $saleprice = []): array
    {
        $feed = [
            'MessageType' => 'Price',
            'Message' => [],
        ];

        foreach ($standardprice as $sku => $price) {
            $feed['Message'][] = [
                'MessageID' => rand(),
                'Price' => [
                    'SKU' => $sku,
                    'StandardPrice' => [
                        '_value' => strval($price),
                        '_attributes' => [
                            'currency' => 'DEFAULT',
                        ],
                    ],
                ],
            ];

            $price = $saleprice[$sku] ?? null;

            if (!is_array($price)) {
                continue;
            }

            $feed['Message'][count($feed['Message']) - 1]['Price']['Sale'] = [
                'StartDate' => $price['StartDate']->format(self::DATE_FORMAT),
                'EndDate' => $price['EndDate']->format(self::DATE_FORMAT),
                'SalePrice' => [
                    '_value' => strval($price['SalePrice']),
                    '_attributes' => [
                        'currency' => 'DEFAULT',
                    ]
                ],
            ];
        }

        return $this->submitFeed('_POST_PRODUCT_PRICING_DATA_', $feed);
    }

    /**
     * Post to create or update a product (_POST_FLAT_FILE_LISTINGS_DATA_)
     *
     * @param object|array $MWSProducts - MWSProduct or array of MWSProduct objects
     *
     * @return array
     */
    public function postProduct($MWSProducts): array
    {
        if (!is_array($MWSProducts)) {
            $MWSProducts = [$MWSProducts];
        }

        $csv = Writer::createFromFileObject(new SplTempFileObject());

        $csv->setDelimiter("\t");
        $csv->setInputEncoding('iso-8859-1');

        $csv->insertOne(['TemplateType=Offer', 'Version=2014.0703']);

        $header = [
            'sku', 'price', 'quantity', 'product-id',
            'product-id-type', 'condition-type', 'condition-note',
            'ASIN-hint', 'title', 'product-tax-code', 'operation-type',
            'sale-price', 'sale-start-date', 'sale-end-date', 'leadtime-to-ship',
            'launch-date', 'is-giftwrap-available', 'is-gift-message-available',
            'fulfillment-center-id', 'main-offer-image', 'offer-image1',
            'offer-image2', 'offer-image3', 'offer-image4', 'offer-image5',
        ];

        $csv->insertOne($header);
        $csv->insertOne($header);

        foreach ($MWSProducts as $product) {
            $csv->insertOne(array_values($product->toArray()));
        }

        return $this->submitFeed('_POST_FLAT_FILE_LISTINGS_DATA_', $csv);
    }

    /**
     * Returns the feed processing report and the Content-MD5 header.
     *
     * @param string $FeedSubmissionId
     *
     * @return array
     */
    public function getFeedSubmissionResult(string $FeedSubmissionId): array
    {
        $result = $this->request('GetFeedSubmissionResult', ['FeedSubmissionId' => $FeedSubmissionId]);

        return $result['Message']['ProcessingReport'] ?? $result;
    }

    /**
     * Returns a list of all feed submissions submitted in the previous 90 days.
     *
     * @return array
     */
    public function getFeedSubmissionList(): array
    {
        $result = $this->request('GetFeedSubmissionList');

        return $result['Message']['ProcessingReport'] ?? $result;
    }

    /**
     * Uploads a feed for processing by Amazon MWS.
     *
     * @param string $FeedType (http://docs.developer.amazonservices.com/en_US/feeds/Feeds_FeedType.html)
     * @param mixed $feedContent Array will be converted to xml using https://github.com/spatie/array-to-xml. Strings will not be modified.
     * @param boolean $debug Return the generated xml and don't send it to amazon
     *
     * @return array
     */
    public function SubmitFeed(string $FeedType, $feedContent, bool $debug = false, array $options = []): array
    {
        if (is_array($feedContent)) {
            $feedContent = $this->arrayToXml(array_merge([
                'Header' => [
                    'DocumentVersion' => 1.01,
                    'MerchantIdentifier' => $this->config['Seller_Id'],
                ],
            ], $feedContent));
        }

        if ($debug === true) {
            return $feedContent;
        }

        if ($this->debugNextFeed) {
            $this->debugNextFeed = false;

            return $feedContent;
        }

        $purgeAndReplace = $options['PurgeAndReplace'] ?? false;

        $query = [
            'FeedType' => $FeedType,
            'PurgeAndReplace' => ($purgeAndReplace ? 'true' : 'false'),
            'Merchant' => $this->config['Seller_Id'],
            'MarketplaceId.Id.1' => false,
            'MarketplaceIdList.Id.1' => $this->config['Marketplace_Id'],
            'SellerId' => false,
        ];

        $response = $this->request('SubmitFeed', $query, $feedContent);

        return $response['SubmitFeedResult']['FeedSubmissionInfo'];
    }

    /**
     * Convert an array to xml
     *
     * @param array $array to convert
     * @param string $customRoot = 'AmazonEnvelope'
     *
     * @return string
     */
    protected function arrayToXml(array $array, string $customRoot = 'AmazonEnvelope'): string
    {
        return ArrayToXml::convert($array, $customRoot);
    }

    /**
     * Convert an xml string to an array
     *
     * @param string $xmlstring
     *
     * @return array
     */
    protected function xmlToArray(string $xmlstring): array
    {
        return json_decode(json_encode(simplexml_load_string($xmlstring)), true);
    }

    /**
     * Creates a report request and submits the request to Amazon MWS.
     *
     * @param string $report (http://docs.developer.amazonservices.com/en_US/reports/Reports_ReportType.html)
     * @param DateTime $StartDate = null
     * @param DateTime $EndDate = null
     *
     * @return string ReportRequestId
     */
    public function RequestReport(string $report, DateTime $StartDate = null, DateTime $EndDate = null): string
    {
        $query = [
            'MarketplaceIdList.Id.1' => $this->config['Marketplace_Id'],
            'ReportType' => $report,
        ];

        if ($StartDate) {
            $query['StartDate'] = gmdate(self::DATE_FORMAT, $StartDate->getTimestamp());
        }

        if ($EndDate) {
            $query['EndDate'] = gmdate(self::DATE_FORMAT, $EndDate->getTimestamp());
        }

        $result = $this->request('RequestReport', $query);

        if (empty($result['RequestReportResult']['ReportRequestInfo']['ReportRequestId'])) {
            throw new Exception('Error trying to request report');
        }

        return $result['RequestReportResult']['ReportRequestInfo']['ReportRequestId'];
    }

    /**
     * Get a report's content
     *
     * @param string $ReportId
     *
     * @return array on succes
     */
    public function getReport(string $ReportId): ?array
    {
        $status = $this->getReportRequestStatus($ReportId);

        if (empty($status)) {
            return null;
        }

        if ($status['ReportProcessingStatus'] === '_DONE_NO_DATA_') {
            return [];
        }

        if ($status['ReportProcessingStatus'] !== '_DONE_') {
            return null;
        }

        $result = $this->request('GetReport', ['ReportId' => $status['GeneratedReportId']]);

        if (is_string($result)) {
            $csv = Reader::createFromString($result);
            $csv->setDelimiter("\t");

            $headers = $csv->fetchOne();
            $result = [];

            foreach ($csv->setOffset(1)->fetchAll() as $row) {
                $result[] = array_combine($headers, $row);
            }
        }

        return $result;
    }

    /**
     * Get a report's processing status
     *
     * @param string  $ReportId
     *
     * @return array if the report is found
     */
    public function getReportRequestStatus(string $ReportId): ?array
    {
        $result = $this->request('GetReportRequestList', ['ReportRequestIdList.Id.1' => $ReportId]);

        return $result['GetReportRequestListResult']['ReportRequestInfo'] ?? null;
    }

    /**
     * Get a list's inventory for Amazon's fulfillment
     *
     * @param array $skuList = []
     *
     * @throws Exception
     *
     * @return array
     */
    public function ListInventorySupply(array $skuList = []): array
    {
        if (count($skuList) > 50) {
            throw new Exception('Maximum amount of SKU\'s for this call is 50');
        }

        $counter = 1;
        $query = [];

        foreach ($skuList as $key) {
            $query['SellerSkus.member.'.($counter++)] = $key;
        }

        $response = $this->request('ListInventorySupply', $query);

        return $response['ListInventorySupplyResult']['InventorySupplyList']['member'] ?? [];
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
    protected function request(string $endPoint, array $query = [], string $body = null, bool $raw = false)
    {
        $endPoint = MWSEndPoint::get($endPoint);

        $query += [
            'Timestamp' => gmdate(self::DATE_FORMAT, time()),
            'AWSAccessKeyId' => $this->config['Access_Key_ID'],
            'Action' => $endPoint['action'],
            'MarketplaceId' => $this->config['Marketplace_Id'],
            'MarketplaceId.Id.1' => $this->config['Marketplace_Id'],
            'SellerId' => $this->config['Seller_Id'],
            'SignatureMethod' => self::SIGNATURE_METHOD,
            'SignatureVersion' => self::SIGNATURE_VERSION,
            'Version' => $endPoint['date'],
        ];

        if ($this->config['MWSAuthToken']) {
            $query['MWSAuthToken'] = $this->config['MWSAuthToken'];
        }

        try {
            return $this->doRequest($endPoint, $query, $body, $raw);
        } catch (BadResponseException $e) {
            return $this->requestError($e);
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
    protected function doRequest(array $endPoint, array $query = [], string $body = null, bool $raw = false)
    {
        $headers = [
            'Accept' => 'application/xml',
            'x-amazon-user-agent' => $this->config['Application_Name'].'/'.$this->config['Application_Version'],
        ];

        if ($endPoint['action'] === 'SubmitFeed') {
            $headers['Content-MD5'] = base64_encode(md5($body, true));
            $headers['Content-Type'] = 'text/xml; charset=iso-8859-1';
            $headers['Host'] = $this->config['Region_Host'];

            unset($query['MarketplaceId.Id.1'], $query['SellerId']);
        }

        $requestOptions = [
            'headers' => $headers,
            'body' => $body,
        ];

        ksort($query);

        $query['Signature'] = $this->getSignature($endPoint, $query);

        $requestOptions['query'] = $query;

        if ($this->client === null) {
            $this->client = new Client;
        }

        $response = $this->client->request(
            $endPoint['method'],
            $this->config['Region_Url'].$endPoint['path'],
            $requestOptions
        );

        $body = (string)$response->getBody();

        if ($raw) {
            return $body;
        }

        if (strpos(strtolower($response->getHeader('Content-Type')[0]), 'xml') !== false) {
            return $this->xmlToArray($body);
        }

        return $body;
    }

    /**
     * @param BadResponseException $e
     *
     * @throws Exception
     */
    protected function requestError(BadResponseException $e)
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
    protected function getSignature(array $endPoint, array $query): string
    {
        $string = $endPoint['method']
            ."\n".$this->config['Region_Host']
            ."\n".$endPoint['path']
            ."\n".http_build_query($query, null, '&', PHP_QUERY_RFC3986);

        return base64_encode(hash_hmac('sha256', $string, $this->config['Secret_Access_Key'], true));
    }

    /**
     * @param array $response
     *
     * @return array
     */
    protected function responseByKeys(array $response): array
    {
        return (array_keys($response) === range(0, count($response) - 1)) ? $response : [$response];
    }

    public function setClient(Client $client)
    {
        $this->client = $client;
    }
}

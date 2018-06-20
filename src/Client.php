<?php declare (strict_types = 1);

namespace AmazonMWS;

use DateTime;
use Exception;
use SplTempFileObject;

class Client
{
    /**
     * @var array
     */
    protected $languages = ['de-DE', 'en-EN', 'es-ES', 'fr-FR', 'it-IT', 'en-US'];

    /**
     * @var bool
     */
    protected $debugNextFeed = false;

    /**
     * @param array $config
     *
     * @return self
     */
    public function __construct(array $config)
    {
        Config::set($config);
    }

    /**
     * Call this method to get the raw feed instead of sending it
     *
     * @return void
     */
    public function debugNextFeed(): void
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
     * https://docs.developer.amazonservices.com/en_US/products/Products_GetCompetitivePricingForASIN.html
     *
     * Returns the current competitive price of a product, based on ASIN.
     *
     * @param array $asinList = []
     *
     * @return array
     */
    public function GetCompetitivePricingForASIN(array $asinList = []): array
    {
        if (count($asinList) > 20) {
            throw new Exception('Maximum amount of ASIN\'s for this call is 20');
        }

        $counter = 1;
        $query = ['MarketplaceId.Id.1' => false];

        foreach ($asinList as $key) {
            $query['ASINList.ASIN.'.($counter++)] = $key;
        }

        $response = $this->request('GetCompetitivePricingForASIN', $query);

        if (empty($response)) {
            return [];
        }

        $array = [];

        foreach ($this->responseByKeys($response) as $product) {
            $price = $product['Product']['CompetitivePricing']['CompetitivePrices']['CompetitivePrice']['Price'] ?? false;

            if ($price) {
                $array[$product['Product']['Identifiers']['MarketplaceASIN']['ASIN']] = $price;
            }
        }

        return $array;
    }

    /**
     * https://docs.developer.amazonservices.com/en_US/products/Products_GetCompetitivePricingForSKU.html
     *
     * Returns the current competitive price of a product, based on SKU.
     *
     * @param array $skuList = []
     *
     * @return array
     */
    public function GetCompetitivePricingForSKU(array $skuList = []): array
    {
        if (count($skuList) > 20) {
            throw new Exception('Maximum amount of SKU\'s for this call is 20');
        }

        $counter = 1;
        $query = ['MarketplaceId.Id.1' => false];

        foreach ($skuList as $key) {
            $query['SellerSKUList.SellerSKU.'.($counter++)] = $key;
        }

        $response = $this->request('GetCompetitivePricingForSKU', $query);

        if (empty($response)) {
            return [];
        }

        $array = [];

        foreach ($this->responseByKeys($response) as $product) {
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
     * https://docs.developer.amazonservices.com/en_US/products/Products_GetLowestPricedOffersForASIN.html
     *
     * Returns lowest priced offers for a single product, based on ASIN.
     *
     * @param string $asin
     * @param string $ItemCondition = 'New' - Should be one in: New, Used, Collectible, Refurbished, Club
     *
     * @return array
     */
    public function GetLowestPricedOffersForASIN(string $asin, string $ItemCondition = 'New'): array
    {
        return $this->request('GetLowestPricedOffersForASIN', [
            'ASIN' => $asin,
            'ItemCondition' => $ItemCondition,
        ]);
    }

    /**
     * https://docs.developer.amazonservices.com/en_US/products/Products_GetMyPriceForSKU.html
     *
     * Returns pricing information for your own offer listings, based on SKU.
     *
     * @param array  $skuList = []
     * @param string $ItemCondition = ''
     *
     * @return array
     */
    public function GetMyPriceForSKU(array $skuList = [], string $ItemCondition = ''): array
    {
        if (count($skuList) > 20) {
            throw new Exception('Maximum amount of SKU\'s for this call is 20');
        }

        $counter = 1;
        $query = ['MarketplaceId.Id.1' => false];

        if ($ItemCondition) {
            $query['ItemCondition'] = $ItemCondition;
        }

        foreach ($skuList as $key) {
            $query['SellerSKUList.SellerSKU.'.($counter++)] = $key;
        }

        $response = $this->request('GetMyPriceForSKU', $query);

        if (empty($response)) {
            return [];
        }

        $array = [];

        foreach ($this->responseByKeys($response) as $product) {
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
     * https://docs.developer.amazonservices.com/en_US/products/Products_GetMyPriceForASIN.html
     *
     * Returns pricing information for your own offer listings, based on ASIN.
     *
     * @param array $asinList = []
     * @param string $ItemCondition = ''
     *
     * @return array
     */
    public function GetMyPriceForASIN(array $asinList = [], string $ItemCondition = ''): array
    {
        if (count($asinList) > 20) {
            throw new Exception('Maximum amount of SKU\'s for this call is 20');
        }

        $counter = 1;
        $query = ['MarketplaceId.Id.1' => false];

        if ($ItemCondition) {
            $query['ItemCondition'] = $ItemCondition;
        }

        foreach ($asinList as $key) {
            $query['ASINList.ASIN.'.($counter++)] = $key;
        }

        $response = $this->request('GetMyPriceForASIN', $query);

        if (empty($response)) {
            return [];
        }

        $array = [];

        foreach ($this->responseByKeys($response) as $product) {
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
     * https://docs.developer.amazonservices.com/en_US/products/Products_GetLowestOfferListingsForASIN.html
     *
     * Returns pricing information for the lowest-price active offer listings for up to 20 products, based on ASIN.
     *
     * @param array $asinList = [] array of ASIN values
     * @param string $ItemCondition = '' Should be one in: New, Used, Collectible, Refurbished, Club. Default: All
     *
     * @return array
     */
    public function GetLowestOfferListingsForASIN(array $asinList = [], string $ItemCondition = ''): array
    {
        if (count($asinList) > 20) {
            throw new Exception('Maximum amount of ASIN\'s for this call is 20');
        }

        $counter = 1;
        $query = ['MarketplaceId.Id.1' => false];

        if ($ItemCondition) {
            $query['ItemCondition'] = $ItemCondition;
        }

        foreach ($asinList as $key) {
            $query['ASINList.ASIN.'.($counter++)] = $key;
        }

        $response = $this->request('GetLowestOfferListingsForASIN', $query);

        if (empty($response)) {
            return [];
        }

        $array = [];

        foreach ($this->responseByKeys($response) as $product) {
            $asin = $product['Product']['Identifiers']['MarketplaceASIN']['ASIN'];
            $array[$asin] = $product['Product']['LowestOfferListings']['LowestOfferListing'] ?? false;
        }

        return $array;
    }

    /**
     * https://docs.developer.amazonservices.com/en_US/orders-2013-09-01/Orders_ListOrders.html
     *
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
        $query = [
            'MarketplaceId' => false,
            'CreatedAfter' => gmdate(Constant::DATE_FORMAT, $from->getTimestamp()),
        ];

        if ($till) {
            $query['CreatedBefore'] = gmdate(Constant::DATE_FORMAT, $till->getTimestamp());
        }

        $counter = 1;

        foreach ($states as $status) {
            $query['OrderStatus.Status.'.($counter++)] = $status;
        }

        if ($allMarketplaces) {
            $counter = 1;

            foreach (Config::get('marketplaces') as $key => $value) {
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

        if (empty($response['Orders']['Order'])) {
            return [];
        }

        if (empty($response['NextToken'])) {
            return $this->responseByKeys($response['Orders']['Order']);
        }

        $data['ListOrders'] = $response['Orders']['Order'];
        $data['NextToken'] = $response['NextToken'];

        return $data;
    }

    /**
     * https://docs.developer.amazonservices.com/en_US/orders-2013-09-01/Orders_ListOrderItemsByNextToken.html
     *
     * Returns orders created or updated during a time frame that you specify.
     *
     * @param string $nextToken
     *
     * @return array
     */
    public function ListOrdersByNextToken(string $nextToken): array
    {
        $response = $this->request('ListOrdersByNextToken', ['NextToken' => $nextToken]);

        if (empty($response['Orders']['Order'])) {
            return [];
        }

        if (empty($response['NextToken'])) {
            $this->responseByKeys($response['Orders']['Order']);
        }

        $data['ListOrders'] = $response['Orders']['Order'];
        $data['NextToken'] = $response['NextToken'];

        return $data;
    }

    /**
     * https://docs.developer.amazonservices.com/en_US/orders-2013-09-01/Orders_GetOrder.html
     *
     * Returns an order based on the AmazonOrderId values that you specify.
     *
     * @param string $AmazonOrderId
     *
     * @return ?array if the order is found, null if not
     */
    public function GetOrder(string $AmazonOrderId): ?array
    {
        $response = $this->request('GetOrder', ['AmazonOrderId.Id.1' => $AmazonOrderId]);

        return $response['Orders']['Order'] ?? null;
    }

    /**
     * https://docs.developer.amazonservices.com/en_US/orders-2013-09-01/Orders_ListOrderItems.html
     *
     * Returns order items based on the AmazonOrderId that you specify.
     *
     * @param string $AmazonOrderId
     *
     * @return array
     */
    public function ListOrderItems(string $AmazonOrderId): array
    {
        $response = $this->request('ListOrderItems', ['AmazonOrderId' => $AmazonOrderId]);

        $result = array_values($response['OrderItems']);

        return isset($result[0]['QuantityOrdered']) ? $result : $result[0];
    }

    /**
     * https://docs.developer.amazonservices.com/en_US/products/Products_GetProductCategoriesForSKU.html
     *
     * Returns the parent product categories that a product belongs to, based on SellerSKU.
     *
     * @param string $SellerSKU
     *
     * @return ?array if found, null if not found
     */
    public function GetProductCategoriesForSKU(string $SellerSKU): ?array
    {
        return $this->request('GetProductCategoriesForSKU', [
            'MarketplaceId.Id.1' => false,
            'SellerSKU' => $SellerSKU,
        ])['Self'] ?? null;
    }

    /**
     * https://docs.developer.amazonservices.com/en_US/products/Products_GetProductCategoriesForASIN.html
     *
     * Returns the parent product categories that a product belongs to, based on ASIN.
     *
     * @param string $ASIN
     *
     * @return ?array if found, null if not found
     */
    public function GetProductCategoriesForASIN(string $ASIN): ?array
    {
        return $this->request('GetProductCategoriesForASIN', [
            'MarketplaceId.Id.1' => false,
            'ASIN' => $ASIN,
        ])['Self'] ?? null;
    }

    /**
     * https://docs.developer.amazonservices.com/en_US/products/Products_GetMatchingProductForId.html
     *
     * Returns a list of products and their attributes, based on a list of ASIN, GCID, SellerSKU, UPC, EAN, ISBN, and JAN values.
     *
     * @param array $asinList A list of id's
     * @param string [$type = 'ASIN']  the identifier name
     *
     * @return array
     */
    public function GetMatchingProductForId(array $asinList, string $type = 'ASIN'): array
    {
        $asinList = array_unique($asinList);

        if (count($asinList) > 5) {
            throw new Exception('Maximum number of id\'s = 5');
        }

        $counter = 1;
        $query = [
            'MarketplaceId.Id.1' => false,
            'IdType' => $type,
        ];

        foreach ($asinList as $asin) {
            $query['IdList.Id.'.($counter++)] = $asin;
        }

        $response = $this->request('GetMatchingProductForId', $query, null, true);
        $response = $this->replaceLanguages($response);

        $response = Xml::toArray($response)['GetMatchingProductForIdResult'] ?? [];

        $result = [
            'found' => [],
            'not_found' => [],
        ];

        if (empty($response)) {
            return $result;
        }

        if (isset($response['@attributes'])) {
            $response = [$response];
        }

        foreach ($response as $item) {
            $asin = $item['@attributes']['Id'];

            if ($item['@attributes']['status'] !== 'Success') {
                $result['not_found'][] = $asin;
                continue;
            }

            if (isset($item['Products']['Product']['AttributeSets'])) {
                $products[0] = $item['Products']['Product'];
            } else {
                $products = $item['Products']['Product'];
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

                $result['found'][$asin][] = $array;
            }
        }

        return $result;
    }

    /**
     * https://docs.developer.amazonservices.com/en_US/products/Products_ListMatchingProducts.html
     *
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
            'MarketplaceId.Id.1' => false,
            'Query' => urlencode($search),
            'QueryContextId' => $searchContextId,
        ];

        $response = $this->request('ListMatchingProducts', $query, null, true);
        $response = $this->replaceLanguages($response);

        return Xml::toArray($response)['ListMatchingProductsResult'] ?? [];
    }

    /**
     * https://docs.developer.amazonservices.com/en_US/reports/Reports_GetReportList.html
     *
     * Returns a list of reports that were created in the previous 90 days.
     *
     * @param array $ReportTypeList = []
     *
     * @return array
     */
    public function GetReportList(array $ReportTypeList = []): array
    {
        $query = [];
        $counter = 1;

        foreach ($ReportTypeList as $ReportType) {
            $query['ReportTypeList.Type.'.($counter++)] = $ReportType;
        }

        return $this->request('GetReportList', $query);
    }

    /**
     * https://docs.developer.amazonservices.com/en_US/recommendations/Recommendations_ListRecommendations.html
     *
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

        return $this->request('ListRecommendations', $query);
    }

    /**
     * https://docs.developer.amazonservices.com/en_US/sellers/Sellers_ListMarketplaceParticipations.html
     *
     * Returns a list of marketplaces that the seller submitting the request can sell in, and a list of participations that include seller-specific information in that marketplace
     *
     * @return array
     */
    public function ListMarketplaceParticipations(): array
    {
        return $this->request('ListMarketplaceParticipations');
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

        return $this->SubmitFeed('_POST_PRODUCT_DATA_', $feed);
    }

    /**
     * https://docs.developer.amazonservices.com/en_US/feeds/Feeds_SubmitFeed.html
     *
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

        return $this->SubmitFeed('_POST_INVENTORY_AVAILABILITY_DATA_', $feed);
    }

    /**
     * https://docs.developer.amazonservices.com/en_US/feeds/Feeds_SubmitFeed.html
     *
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

        return $this->SubmitFeed('_POST_INVENTORY_AVAILABILITY_DATA_', $feed);
    }

    /**
     * https://docs.developer.amazonservices.com/en_US/feeds/Feeds_SubmitFeed.html
     *
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
                'StartDate' => $price['StartDate']->format(Constant::DATE_FORMAT),
                'EndDate' => $price['EndDate']->format(Constant::DATE_FORMAT),
                'SalePrice' => [
                    '_value' => strval($price['SalePrice']),
                    '_attributes' => [
                        'currency' => 'DEFAULT',
                    ],
                ],
            ];
        }

        return $this->SubmitFeed('_POST_PRODUCT_PRICING_DATA_', $feed);
    }

    /**
     * https://docs.developer.amazonservices.com/en_US/feeds/Feeds_SubmitFeed.html
     *
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

        return $this->SubmitFeed('_POST_FLAT_FILE_LISTINGS_DATA_', $csv);
    }

    /**
     * https://docs.developer.amazonservices.com/en_US/feeds/Feeds_GetFeedSubmissionResult.html
     *
     * Returns the feed processing report and the Content-MD5 header.
     *
     * @param string $FeedSubmissionId
     *
     * @return array
     */
    public function GetFeedSubmissionResult(string $FeedSubmissionId): array
    {
        $result = $this->request('GetFeedSubmissionResult', ['FeedSubmissionId' => $FeedSubmissionId]);

        return $result['Message']['ProcessingReport'] ?? $result;
    }

    /**
     * https://docs.developer.amazonservices.com/en_US/feeds/Feeds_GetFeedSubmissionList.html
     *
     * Returns a list of all feed submissions submitted in the previous 90 days.
     *
     * @return array
     */
    public function GetFeedSubmissionList(): array
    {
        $result = $this->request('GetFeedSubmissionList');

        return $result['Message']['ProcessingReport'] ?? $result;
    }

    /**
     * https://docs.developer.amazonservices.com/en_US/feeds/Feeds_SubmitFeed.html
     *
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
            $feedContent = Xml::toXml(array_merge([
                'Header' => [
                    'DocumentVersion' => 1.01,
                    'MerchantIdentifier' => Config::get('Seller_Id'),
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
            'Merchant' => Config::get('Seller_Id'),
            'MarketplaceId.Id.1' => false,
            'SellerId' => false,
        ];

        return $this->request('SubmitFeed', $query, $feedContent)['FeedSubmissionInfo'];
    }

    /**
     * https://docs.developer.amazonservices.com/en_US/reports/Reports_RequestReport.html
     *
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
        $query = ['ReportType' => $report];

        if ($StartDate) {
            $query['StartDate'] = gmdate(Constant::DATE_FORMAT, $StartDate->getTimestamp());
        }

        if ($EndDate) {
            $query['EndDate'] = gmdate(Constant::DATE_FORMAT, $EndDate->getTimestamp());
        }

        $result = $this->request('RequestReport', $query);

        if (empty($result['ReportRequestInfo']['ReportRequestId'])) {
            throw new Exception('Error trying to request report');
        }

        return $result['ReportRequestInfo']['ReportRequestId'];
    }

    /**
     * https://docs.developer.amazonservices.com/en_US/reports/Reports_GetReport.html
     *
     * Get a report's content
     *
     * @param string $ReportId
     *
     * @return array on succes
     */
    public function GetReport(string $ReportId): ?array
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
     * https://docs.developer.amazonservices.com/en_US/reports/Reports_GetReportRequestList.html
     *
     * Get a report's processing status
     *
     * @param string  $ReportId
     *
     * @return array if the report is found
     */
    public function GetReportRequestList(string $ReportId): ?array
    {
        $result = $this->request('GetReportRequestList', ['ReportRequestIdList.Id.1' => $ReportId]);

        return $result['ReportRequestInfo'] ?? null;
    }

    /**
     * https://docs.developer.amazonservices.com/en_US/fba_inventory/FBAInventory_ListInventorySupply.html
     *
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

        return $response['InventorySupplyList']['member'] ?? [];
    }

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
    protected function request(string $endpoint, array $query = [], string $body = null, bool $raw = false)
    {
        return Request::request($endpoint, $query, $body, $raw);
    }

    /**
     * @param string $response
     *
     * @return string
     */
    protected function replaceLanguages(string $response): string
    {
        $replace = ['</ns2:ItemAttributes>' => '</ItemAttributes>'];

        foreach ($this->languages as $language) {
            $replace['<ns2:ItemAttributes xml:lang="'.$language.'">'] = '<ItemAttributes><Language>'.$language.'</Language>';
        }

        $replace['ns2:'] = '';

        return strtr($response, $replace);
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
}

<?php declare (strict_types=1);

namespace AmazonMWS;

class Constant
{
    /**
     * @var const
     */
    public const SIGNATURE_METHOD = 'HmacSHA256';

    /**
     * @var const
     */
    public const SIGNATURE_VERSION = '2';

    /**
     * @var const
     */
    public const DATE_FORMAT = 'Y-m-d\\TH:i:s.\\0\\0\\0\\Z';

    /**
     * @var const
     */
    public const APPLICATION_NAME = 'AmazonMWS/Client';
}

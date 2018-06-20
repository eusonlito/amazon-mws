<?php declare (strict_types = 1);

namespace AmazonMWS;

use Exception;

class Curl
{
    /**
     * @var resource
     */
    protected $curl;

    /**
     * @var string
     */
    protected $url = '';

    /**
     * @var string
     */
    protected $method = 'GET';

    /**
     * @var string
     */
    protected $body = '';

    /**
     * @var array
     */
    protected $query = [];

    /**
     * @var array
     */
    protected $info = [];

    /**
     * @var string
     */
    protected $userAgent = Constant::APPLICATION_NAME;

    /**
     * @return self
     */
    public function __construct()
    {
        $this->curl = curl_init();

        $this->setOption(CURLOPT_RETURNTRANSFER, true);
        $this->setOption(CURLOPT_SSL_VERIFYPEER, false);
        $this->setOption(CURLOPT_SSL_VERIFYHOST, false);
        $this->setOption(CURLOPT_TIMEOUT, 5);
        $this->setOption(CURLOPT_MAXREDIRS, 5);
        $this->setOption(CURLOPT_FOLLOWLOCATION, true);
        $this->setOption(CURLOPT_COOKIESESSION, false);

        $this->setHeaders([
            'User-Agent: '.$this->userAgent,
            'Connection: keep-alive',
            'Cache-Control: max-age=0',
        ]);
    }

    /**
     * @param int $option
     * @param mixed $value
     *
     * @return self
     */
    public function setOption(int $option, $value): self
    {
        curl_setopt($this->curl, $option, $value);

        return $this;
    }

    /**
     * @param string $url
     *
     * @return self
     */
    public function setUrl(string $url): self
    {
        $this->url = $url;

        return $this;
    }

    /**
     * @param array $headers
     *
     * @return self
     */
    public function setHeaders(array $headers): self
    {
        $this->setOption(CURLOPT_HTTPHEADER, $headers);

        return $this;
    }

    /**
     * @param string $method
     *
     * @return self
     */
    public function setMethod(string $method): self
    {
        $this->method = strtoupper($method);

        return $this;
    }

    /**
     * @param ?string $body
     *
     * @return self
     */
    public function setBody( ? string $body) : self
    {
        $this->body = $body;

        return $this;
    }

    /**
     * @param ?array $query
     *
     * @return self
     */
    public function setQuery( ? array $query) : self
    {
        $this->query = $query;

        return $this;
    }

    /**
     * @return string
     */
    public function exec(): string
    {
        if ($this->method === 'GET') {
            $this->execGET();
        } else {
            $this->execPOST();
        }

        $this->setOption(CURLOPT_URL, $this->url);

        $response = curl_exec($this->curl);

        $this->info = curl_getinfo($this->curl);

        curl_close($this->curl);

        if ($this->info['http_code'] !== 200) {
            $this->error($response);
        }

        return $response;
    }

    /**
     * @param string $key = ''
     *
     * @return mixed
     */
    public function getInfo(string $key = '')
    {
        return $key ? $this->info[$key] : $this->info;
    }

    /**
     * @return self
     */
    protected function execGET(): self
    {
        $this->setOption(CURLOPT_POST, false);

        $this->url .= '?'.$this->buildQuery();

        return $this;
    }

    /**
     * @return self
     */
    protected function execPOST(): self
    {
        $this->setOption(CURLOPT_POST, true);
        $this->setOption(CURLOPT_POSTFIELDS, $this->buildQuery());

        return $this;
    }

    /**
     * @return string
     */
    protected function buildQuery(): string
    {
        return http_build_query($this->query, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * @param string $response
     *
     * @throws \Exception
     */
    protected function error(string $response)
    {
        if (strpos($response, '<ErrorResponse') === false) {
            $message = $response;
        } else {
            $message = Xml::toArray($response)['Error']['Message'];
        }

        throw new Exception($message, $this->info['http_code']);
    }
}

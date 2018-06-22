<?php declare (strict_types = 1);

namespace AmazonMWS;

use DOMDocument;
use DOMNode;

class Xml
{
    /**
     * @param string $xml
     *
     * @return array
     */
    public static function toArray(string $xml): array
    {
        return json_decode(json_encode(simplexml_load_string($xml)), true);
    }

    /**
     * @param array $array
     *
     * @return string
     */
    public static function toString(array $array): string
    {
        $dom = new DOMDocument('1.0');

        static::toStringRecursive($dom, $array, $dom);

        return $dom->saveXML();
    }

    /**
     * @param \DOMNode $root
     * @param array $array
     * @param \DOMDocument $dom
     *
     * @return void
     */
    private static function toStringRecursive(DOMNode $root, array $array, DOMDocument $dom): void
    {
        foreach ($array as $key => $item) {
            $isArray = is_array($item);
            $isNumeric = is_numeric($key);

            if ($isArray && isset($item['_value']) && isset($item['_attributes'])) {
                static::domWithAttributes($root, $key, $item, $dom);
                continue;
            }

            if ($isArray && $isNumeric) {
                static::toStringRecursive($root, $item, $dom);
                continue;
            }

            if (($isArray === false) || $isNumeric) {
                static::domChild($root, $key, $item, $dom);
                continue;
            }

            static::domNodes($root, $key, $item, $dom);
        }
    }

    /**
     * @param \DOMNode $root
     * @param string $key
     * @param array $item
     * @param \DOMDocument $dom
     *
     * @return void
     */
    private static function domWithAttributes(DOMNode $root, string $key, array $item, DOMDocument $dom): void
    {
        $node = $dom->createElement($key, $item['_value']);

        foreach ($item['_attributes'] as $key => $value) {
            $node->setAttribute($key, $value);
        }

        $root->appendChild($node);
    }

    /**
     * @param \DOMNode $root
     * @param string $key
     * @param mixed $item
     * @param \DOMDocument $dom
     *
     * @return void
     */
    private static function domChild(DOMNode $root, string $key, $item, DOMDocument $dom): void
    {
        $root->appendChild($dom->createElement($key, (string)$item));
    }

    /**
     * @param \DOMNode $root
     * @param string $key
     * @param array $item
     * @param \DOMDocument $dom
     *
     * @return void
     */
    private static function domNodes(DOMNode $root, string $key, array $item, DOMDocument $dom): void
    {
        $node = $dom->createElement($key);

        static::toStringRecursive($node, $item, $dom);

        $root->appendChild($node);
    }
}

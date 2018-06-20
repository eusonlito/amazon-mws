<?php declare (strict_types = 1);

namespace AmazonMWS;

use DOMDocument;

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
     * @param \DOMDocument &$root
     * @param array $array
     * @param \DOMDocument &$dom
     *
     * @return void
     */
    private static function toStringRecursive(&$root, $array, &$dom): void
    {
        foreach ($array as $key => $item) {
            $isArray = is_array($item);
            $isNumeric = is_numeric($key);

            if ($isArray && $isNumeric) {
                static::toStringRecursive($root, $item, $dom);
                continue;
            }

            if (($isArray === false) || $isNumeric) {
                $root->appendChild($dom->createElement($key, $item));
                continue;
            }

            $node = $dom->createElement($key);

            static::toStringRecursive($node, $item, $dom);

            $root->appendChild($node);
        }
    }
}

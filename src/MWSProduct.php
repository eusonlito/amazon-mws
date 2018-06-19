<?php declare (strict_types = 1);

namespace MCS;

class MWSProduct
{
    public $sku;
    public $price;
    public $quantity = 0;
    public $product_id;
    public $product_id_type;
    public $condition_type = 'New';
    public $condition_note;

    private $validationErrors = [];

    private $conditions = [
        'New', 'Refurbished', 'UsedLikeNew',
        'UsedVeryGood', 'UsedGood', 'UsedAcceptable',
    ];

    public function __construct(array $array = [])
    {
        foreach ($array as $property => $value) {
            $this->$property = $value;
        }
    }

    public function getValidationErrors(): array
    {
        return $this->validationErrors;
    }

    public function toArray(): array
    {
        return [
            'sku' => $this->sku,
            'price' => $this->price,
            'quantity' => $this->quantity,
            'product_id' => $this->product_id,
            'product_id_type' => $this->product_id_type,
            'condition_type' => $this->condition_type,
            'condition_note' => $this->condition_note,
        ];
    }

    public function validate(): bool
    {
        if ((mb_strlen($this->sku) < 1) || (strlen($this->sku) > 40)) {
            $this->validationErrors['sku'] = 'Should be longer then 1 character and shorter then 40 characters';
        }

        $this->price = str_replace(',', '.', $this->price);

        $priceExplode = explode('.', $this->price);

        if (count($priceExplode) !== 2) {
            $this->validationErrors['price'] = 'Looks wrong';
        } elseif (mb_strlen($priceExplode[0]) > 18) {
            $this->validationErrors['price'] = 'Too high';
        } elseif (mb_strlen($priceExplode[1]) > 2) {
            $this->validationErrors['price'] = 'Too many decimals';
        }

        $this->quantity = (int) $this->quantity;
        $this->product_id = (string) $this->product_id;

        $product_id_length = mb_strlen($this->product_id);

        switch ($this->product_id_type) {
            case 'ASIN':
                if ($product_id_length != 10) {
                    $this->validationErrors['product_id'] = 'ASIN should be 10 characters long';
                }

                break;

            case 'UPC':
                if ($product_id_length != 12) {
                    $this->validationErrors['product_id'] = 'UPC should be 12 characters long';
                }

                break;

            case 'EAN':
                if ($product_id_length != 13) {
                    $this->validationErrors['product_id'] = 'EAN should be 13 characters long';
                }

                break;

            default:
                $this->validationErrors['product_id_type'] = 'Not one of: ASIN,UPC,EAN';
        }

        if (!in_array($this->condition_type, $this->conditions)) {
            $this->validationErrors['condition_type'] = 'Not one of: '.implode($this->conditions, ',');
        }

        if ($this->condition_type !== 'New') {
            $length = mb_strlen($this->condition_note);

            if ($length < 1) {
                $this->validationErrors['condition_note'] = 'Required if condition_type not is New';
            } elseif ($length > 1000) {
                $this->validationErrors['condition_note'] = 'Should not exceed 1000 characters';
            }
        }

        return (bool)$this->validationErrors;
    }

    public function __set(string $property, $value)
    {
        if (property_exists($this, $property)) {
            return $this->$property;
        }
    }
}

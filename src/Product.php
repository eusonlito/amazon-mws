<?php declare (strict_types = 1);

namespace AmazonMWS;

class Product
{
    /**
     * @var string
     */
    public $sku;

    /**
     * @var float
     */
    public $price = 0.0;

    /**
     * @var int
     */
    public $quantity = 0;

    /**
     * @var string
     */
    public $product_id;

    /**
     * @var string
     */
    public $product_id_type;

    /**
     * @var string
     */
    public $condition_type = 'New';

    /**
     * @var string
     */
    public $condition_note;

    /**
     * @var array
     */
    private $errors = [];

    /**
     * @var array
     */
    private $conditions = [
        'New', 'Refurbished', 'UsedLikeNew',
        'UsedVeryGood', 'UsedGood', 'UsedAcceptable',
    ];

    /**
     * @param array $data = []
     *
     * @return self
     */
    public function __construct(array $data = [])
    {
        foreach ($data as $key => $value) {
            $this->$key = $value;
        }
    }

    /**
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @return array
     */
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

    /**
     * @return bool
     */
    public function validate(): bool
    {
        if ((mb_strlen($this->sku) < 1) || (strlen($this->sku) > 40)) {
            $this->errors['sku'] = 'Should be longer then 1 character and shorter then 40 characters';
        }

        $this->price = str_replace(',', '.', $this->price);

        $priceExplode = explode('.', $this->price);

        if (count($priceExplode) !== 2) {
            $this->errors['price'] = 'Looks wrong';
        } elseif (mb_strlen($priceExplode[0]) > 18) {
            $this->errors['price'] = 'Too high';
        } elseif (mb_strlen($priceExplode[1]) > 2) {
            $this->errors['price'] = 'Too many decimals';
        }

        $this->quantity = (int) $this->quantity;
        $this->product_id = (string) $this->product_id;

        $product_id_length = mb_strlen($this->product_id);

        switch ($this->product_id_type) {
            case 'ASIN':
                if ($product_id_length != 10) {
                    $this->errors['product_id'] = 'ASIN should be 10 characters long';
                }

                break;

            case 'UPC':
                if ($product_id_length != 12) {
                    $this->errors['product_id'] = 'UPC should be 12 characters long';
                }

                break;

            case 'EAN':
                if ($product_id_length != 13) {
                    $this->errors['product_id'] = 'EAN should be 13 characters long';
                }

                break;

            default:
                $this->errors['product_id_type'] = 'Not one of: ASIN,UPC,EAN';
        }

        if (!in_array($this->condition_type, $this->conditions)) {
            $this->errors['condition_type'] = 'Not one of: '.implode($this->conditions, ',');
        }

        if ($this->condition_type !== 'New') {
            $length = mb_strlen($this->condition_note);

            if ($length < 1) {
                $this->errors['condition_note'] = 'Required if condition_type not is New';
            } elseif ($length > 1000) {
                $this->errors['condition_note'] = 'Should not exceed 1000 characters';
            }
        }

        return (bool) $this->errors;
    }

    /**
     * @param string $property
     * @param mixed $value
     *
     * @return mixed
     */
    public function __set(string $property, $value)
    {
        if (property_exists($this, $property)) {
            return $this->$property;
        }
    }
}

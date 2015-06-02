<?php

namespace Pim\Component\Connector\ArrayConverter\Flat\Product\Converter;

use Pim\Component\Connector\ArrayConverter\Flat\Product\Splitter\FieldSplitter;

/**
 * Converts flat price into structured one.
 *
 * @author    Olivier Soulet <olivier.soulet@akeneo.com>
 * @copyright 2015 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class PriceConverter extends AbstractValueConverter
{
    /**
     * @param FieldSplitter $fieldSplitter
     * @param array         $supportedFieldType
     */
    public function __construct(FieldSplitter $fieldSplitter, array $supportedFieldType)
    {
        parent::__construct($fieldSplitter);
        $this->supportedFieldType = $supportedFieldType;
    }

    /**
     * {@inheritdoc}
     */
    public function convert(array $attributeFieldInfo, $value)
    {
        if ('' !== $value) {
            $data = $this->fieldSplitter->splitCollection($value);
        } else {
            $data = [];
        }

        $data = array_map(function ($priceValue) use ($attributeFieldInfo) {
            return $this->convertPrice($attributeFieldInfo, $priceValue);
        }, $data);

        return [$attributeFieldInfo['attribute']->getCode() => [[
            'locale' => $attributeFieldInfo['locale_code'],
            'scope'  => $attributeFieldInfo['scope_code'],
            'data'   => $data,
        ]]];
    }

    /**
     * @param array  $attributeFieldInfo
     * @param string $value
     *
     * @return array
     */
    protected function convertPrice(array $attributeFieldInfo, $value)
    {
        if ('' === $value) {
            $priceValue = null;
            $currency = null;
        } else {
            $tokens = $this->fieldSplitter->splitUnitValue($value);
            $priceValue = isset($tokens[0]) ? $tokens[0] : null;
            $currency = isset($tokens[1]) ? $tokens[1] : null;
        }

        return ['data' => $priceValue, 'currency' => $currency];
    }
}
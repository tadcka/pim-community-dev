<?php

namespace Pim\Bundle\TransformBundle\Transformer\Guesser;

use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use Pim\Bundle\TransformBundle\Transformer\ColumnInfo\ColumnInfoInterface;

/**
 * Chained guesser
 *
 * @author    Antoine Guigan <antoine@akeneo.com>
 * @copyright 2013 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class ChainedGuesser implements GuesserInterface
{
    /**
     * @var array
     */
    protected $guessers = array();

    /**
     * Adds a guesser
     *
     * @param \Pim\Bundle\TransformBundle\Transformer\Guesser\GuesserInterface $guesser
     */
    public function addGuesser(GuesserInterface $guesser)
    {
        $this->guessers[] = $guesser;
    }

    /**
     * {@inheritdoc}
     */
    public function getTransformerInfo(ColumnInfoInterface $columnInfo, ClassMetadata $metadata)
    {
        foreach ($this->guessers as $guesser) {
            if ($transformerInfo = $guesser->getTransformerInfo($columnInfo, $metadata)) {
                return $transformerInfo;
            }
        }
    }
}

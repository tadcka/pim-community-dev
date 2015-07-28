<?php

namespace Pim\Bundle\EnrichBundle\Normalizer;

use Pim\Bundle\CatalogBundle\Manager\LocaleManager;
use Pim\Bundle\CatalogBundle\Model\ProductInterface;
use Pim\Bundle\EnrichBundle\Provider\StructureVersion\StructureVersionProviderInterface;
use Pim\Bundle\VersioningBundle\Manager\VersionManager;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Product normalizer
 *
 * @author    Julien Sanchez <julien@akeneo.com>
 * @copyright 2015 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class ProductNormalizer implements NormalizerInterface
{
    /** @var string[] */
    protected $supportedFormat = ['internal_api'];

    /** @var NormalizerInterface */
    protected $productNormalizer;

    /** @var NormalizerInterface */
    protected $versionNormalizer;

    /** @var VersionManager */
    protected $versionManager;

    /** @var LocaleManager */
    protected $localeManager;

    /** @var StructureVersionProviderInterface */
    protected $structureVersionProvider;

    /**
     * @param NormalizerInterface               $productNormalizer
     * @param NormalizerInterface               $versionNormalizer
     * @param VersionManager                    $versionManager
     * @param LocaleManager                     $localeManager
     * @param StructureVersionProviderInterface $structureVersionProvider
     */
    public function __construct(
        NormalizerInterface $productNormalizer,
        NormalizerInterface $versionNormalizer,
        VersionManager $versionManager,
        LocaleManager $localeManager,
        StructureVersionProviderInterface $structureVersionProvider
    ) {
        $this->productNormalizer        = $productNormalizer;
        $this->versionNormalizer        = $versionNormalizer;
        $this->versionManager           = $versionManager;
        $this->localeManager            = $localeManager;
        $this->structureVersionProvider = $structureVersionProvider;
    }

    /**
     * {@inheritdoc}
     */
    public function normalize($product, $format = null, array $context = [])
    {
        $normalizedProduct = $this->productNormalizer->normalize($product, 'json', $context);

        $oldestLog = $this->versionManager->getOldestLogEntry($product);
        $newestLog = $this->versionManager->getNewestLogEntry($product);

        $normalizedProduct['meta'] = [
            'id'                => $product->getId(),
            'created'           => null !== $oldestLog ?
                $this->versionNormalizer->normalize($oldestLog, 'internal_api') :
                null,
            'updated'           => null !== $newestLog ?
                $this->versionNormalizer->normalize($newestLog, 'internal_api') :
                null,
            'model_type'        => 'product',
            'structure_version' => $this->structureVersionProvider->getStructureVersion()
        ] + $this->getLabels($product) + $this->getAssociationMeta($product);

        return $normalizedProduct;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization($data, $format = null)
    {
        return $data instanceof ProductInterface && in_array($format, $this->supportedFormat);
    }

    /**
     * @param ProductInterface $product
     *
     * @return array
     */
    protected function getLabels(ProductInterface $product)
    {
        $labels = [];

        foreach ($this->localeManager->getActiveCodes() as $localeCode) {
            $labels[$localeCode] = $product->getLabel($localeCode);
        }

        return ['label' => $labels];
    }

    /**
     * @param ProductInterface $product
     *
     * @return array
     */
    protected function getAssociationMeta(ProductInterface $product)
    {
        $meta = [];
        $associations = $product->getAssociations();

        foreach ($associations as $association) {
            $associationType = $association->getAssociationType();
            $meta[$associationType->getCode()]['groupIds'] = array_map(
                function ($group) {
                    return $group->getId();
                },
                $association->getGroups()->toArray()
            );
        }

        return ['associations' => $meta];
    }
}

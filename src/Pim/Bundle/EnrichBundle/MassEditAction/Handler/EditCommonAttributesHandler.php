<?php

namespace Pim\Bundle\EnrichBundle\MassEditAction\Handler;

use Akeneo\Bundle\BatchBundle\Entity\StepExecution;
use Akeneo\Bundle\BatchBundle\Item\AbstractConfigurableStepElement;
use Akeneo\Bundle\BatchBundle\Step\StepExecutionAwareInterface;
use Akeneo\Component\StorageUtils\Cursor\PaginatorFactoryInterface;
use Akeneo\Component\StorageUtils\Cursor\PaginatorInterface;
use Akeneo\Component\StorageUtils\Detacher\ObjectDetacherInterface;
use Akeneo\Component\StorageUtils\Saver\BulkSaverInterface;
use Pim\Bundle\CatalogBundle\Model\ProductInterface;
use Pim\Bundle\CatalogBundle\Query\ProductQueryBuilderFactoryInterface;
use Pim\Bundle\CatalogBundle\Query\ProductQueryBuilderInterface;
use Pim\Bundle\CatalogBundle\Repository\AttributeRepositoryInterface;
use Pim\Bundle\CatalogBundle\Repository\ProductMassActionRepositoryInterface;
use Pim\Bundle\CatalogBundle\Updater\ProductUpdaterInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\ValidatorInterface;

/**
 * BatchBundle step element, it applies the mass edit common attributes
 * to products given in configuration.
 *
 * @author    Adrien Pétremann <adrien.petremann@akeneo.com>
 * @copyright 2015 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class EditCommonAttributesHandler extends AbstractConfigurableStepElement implements StepExecutionAwareInterface
{
    /** @var StepExecution */
    protected $stepExecution;

    /** @var ProductMassActionRepositoryInterface */
    protected $massActionRepository;

    /** @var ProductQueryBuilderFactoryInterface */
    protected $pqbFactory;

    /** @var PaginatorFactoryInterface */
    protected $paginatorFactory;

    /** @var ValidatorInterface */
    protected $validator;

    /** @var AttributeRepositoryInterface */
    protected $attributeRepository;

    /** @var ProductUpdaterInterface */
    protected $productUpdater;

    /** @var BulkSaverInterface */
    protected $productSaver;

    /** @var ObjectDetacherInterface */
    protected $objectDetacher;

    /** @var array */
    protected $skippedAttributes = [];

    /**
     * @param ProductQueryBuilderFactoryInterface  $pqbFactory
     * @param ProductUpdaterInterface              $productUpdater
     * @param BulkSaverInterface                   $productSaver
     * @param ObjectDetacherInterface              $objectDetacher
     * @param PaginatorFactoryInterface            $paginatorFactory
     * @param ValidatorInterface                   $validator
     * @param ProductMassActionRepositoryInterface $massActionRepository
     * @param AttributeRepositoryInterface         $attributeRepository
     */
    public function __construct(
        ProductQueryBuilderFactoryInterface $pqbFactory,
        ProductUpdaterInterface $productUpdater,
        BulkSaverInterface $productSaver,
        ObjectDetacherInterface $objectDetacher,
        PaginatorFactoryInterface $paginatorFactory,
        ValidatorInterface $validator,
        ProductMassActionRepositoryInterface $massActionRepository,
        AttributeRepositoryInterface $attributeRepository
    ) {
        $this->pqbFactory = $pqbFactory;
        $this->paginatorFactory = $paginatorFactory;
        $this->validator = $validator;
        $this->productUpdater = $productUpdater;
        $this->productSaver = $productSaver;
        $this->objectDetacher = $objectDetacher;
        $this->attributeRepository = $attributeRepository;
        $this->massActionRepository = $massActionRepository;
    }

    /**
     * @param array $configuration
     */
    public function execute(array $configuration)
    {
        $cursor = $this->getProductsCursor($configuration['filters']);
        $paginator = $this->paginatorFactory->createPaginator($cursor);

        $commonAttributeCodes = $this->findCommonAttributeCodes($paginator);
        $commonActions = $this->filterActions($configuration['actions'], $commonAttributeCodes);

        if (!empty($commonAttributeCodes) && !empty($commonActions)) {
            $actions = $configuration['actions'];

            foreach ($paginator as $productsPage) {
                $updatedProducts = $this->updateProducts($productsPage, $actions, $commonAttributeCodes);
                $validProducts = $this->getValidProducts($updatedProducts);

                $this->saveProducts($validProducts, $this->getSavingOptions());
                $this->detachProducts($updatedProducts);
            }
        } else {
            $this->skipAllProducts($cursor->count());
        }

        $this->generateAttributesWarning();

        $values = array_column($configuration['actions'], 'value');
        $this->removeTemporaryFiles($values);
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigurationFields()
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function setStepExecution(StepExecution $stepExecution)
    {
        $this->stepExecution = $stepExecution;

        return $this;
    }

    /**
     * @return ProductQueryBuilderInterface
     */
    protected function getProductQueryBuilder()
    {
        return $this->pqbFactory->create();
    }

    /**
     * @param array $filters
     *
     * @return \Akeneo\Component\StorageUtils\Cursor\CursorInterface
     */
    protected function getProductsCursor(array $filters)
    {
        $productQueryBuilder = $this->getProductQueryBuilder();

        $resolver = new OptionsResolver();
        $resolver->setRequired(['field', 'operator', 'value']);
        $resolver->setOptional(['locale', 'scope']);
        $resolver->setDefaults(['locale' => null, 'scope' => null]);

        foreach ($filters as $filter) {
            $filter = $resolver->resolve($filter);
            $context = ['locale' => $filter['locale'], 'scope' => $filter['scope']];
            $productQueryBuilder->addFilter($filter['field'], $filter['operator'], $filter['value'], $context);
        }

        return $productQueryBuilder->execute();
    }

    /**
     * @param ConstraintViolationListInterface $violations
     * @param ProductInterface                 $product
     */
    protected function addWarningMessage(ConstraintViolationListInterface $violations, ProductInterface $product)
    {
        foreach ($violations as $violation) {
            // TODO re-format the message, property path doesn't exist for class constraint
            // for instance cf VariantGroupAxis
            $invalidValue = $violation->getInvalidValue();
            if (is_object($invalidValue) && method_exists($invalidValue, '__toString')) {
                $invalidValue = (string) $invalidValue;
            } elseif (is_object($invalidValue)) {
                $invalidValue = get_class($invalidValue);
            }
            $errors = sprintf(
                "%s: %s: %s\n",
                $violation->getPropertyPath(),
                $violation->getMessage(),
                $invalidValue
            );
            $this->stepExecution->addWarning($this->getName(), $errors, [], $product);
        }
    }

    /**
     * Add a skipped attribute to the list
     *
     * @param $attributeCode
     */
    protected function addSkippedAttribute($attributeCode)
    {
        $this->skippedAttributes[] = $attributeCode;
    }

    /**
     * Set data from $actions to the given $products
     *
     * @param array $products
     * @param array $actions
     * @param array $commonAttributeCodes
     *
     * @return array $products
     */
    protected function updateProducts(array $products, array $actions, array $commonAttributeCodes)
    {
        foreach ($products as $product) {
            foreach ($actions as $action) {
                if (in_array($action['field'], $commonAttributeCodes)) {
                    $this->productUpdater->setData($product, $action['field'], $action['value'], $action['options']);
                } else {
                    $this->addSkippedAttribute($action['field']);
                }
            }
        }

        return $products;
    }

    /**
     * @param array $products
     */
    protected function detachProducts(array $products)
    {
        foreach ($products as $product) {
            $this->objectDetacher->detach($product);
        }
    }

    /**
     * Return the options to use when save all products
     *
     * @return array
     */
    protected function getSavingOptions()
    {
        return [
            'recalculate' => false,
            'flush'       => true,
            'schedule'    => false
        ];
    }

    /**
     * Find common attributes for the product retrieved with the given $paginator.
     *
     * @param PaginatorInterface $paginator
     *
     * @return array
     *
     */
    protected function findCommonAttributeCodes(PaginatorInterface $paginator)
    {
        $commonAttributeIds = [];
        $commonAttributeCodes = [];

        foreach ($paginator as $productsPage) {
            $productIds = [];
            foreach ($productsPage as $product) {
                $productIds[] = $product->getId();
            }

            $commonAttributeIds = array_merge(
                $this->massActionRepository->findCommonAttributeIds($productIds),
                $commonAttributeIds
            );
        }

        $commonAttributeIds = array_unique($commonAttributeIds);
        $commonAttributes = $this->attributeRepository->findWithGroups($commonAttributeIds);

        foreach ($commonAttributes as $attribute) {
            $commonAttributeCodes[] = $attribute->getCode();
        }

        return $commonAttributeCodes;
    }

    /**
     * Filter the given $actions, return only $actions on attributes
     * present in $commonAttributeCodes.
     *
     * @param array $actions
     * @param array $commonAttributeCodes
     *
     * @return array
     */
    protected function filterActions(array $actions, array $commonAttributeCodes)
    {
        if (empty($commonAttributeCodes)) {
            return [];
        }

        $commonActions = [];

        foreach ($actions as $action) {
            if (in_array($action['field'], $commonAttributeCodes)) {
                $commonActions[] = $action;
            }
        }

        return $commonActions;
    }

    /**
     * Remove temporary files used to set product media
     *
     * @param array $values
     */
    protected function removeTemporaryFiles(array $values)
    {
        $filePaths = array_column($values, 'filePath');

        foreach ($filePaths as $filePath) {
            unlink($filePath);
        }
    }

    /**
     * Mark all products as skipped and add a proper warning to notify user on reporting screen
     *
     * @param $productCount
     */
    protected function skipAllProducts($productCount)
    {
        $this->stepExecution->incrementSummaryInfo('skipped_products', $productCount);

        $this->stepExecution->addWarning(
            $this->getName(),
            'pim_enrich.mass_edit_action.edit-common-attributes.message.no_valid_attribute',
            [],
            []
        );
    }

    /**
     * Return valid products that should be saved and mark others as skipped
     *
     * @param array $updatedProducts
     *
     * @return array
     */
    protected function getValidProducts(array $updatedProducts)
    {
        $validProducts = [];

        foreach ($updatedProducts as $product) {
            $violations = $this->validator->validate($product);

            if (0 === $violations->count()) {
                $validProducts[] = $product;
            } else {
                $this->addWarningMessage($violations, $product);
                $this->stepExecution->incrementSummaryInfo('skipped_products');
            }
        }

        return $validProducts;
    }

    /**
     * Save products and mark them as edited
     *
     * @param array $validProducts
     * @param array $getSavingOptions
     */
    protected function saveProducts(array $validProducts, array $getSavingOptions)
    {
        $this->productSaver->saveAll($validProducts, $getSavingOptions);
        $this->stepExecution->incrementSummaryInfo('mass_edited', count($validProducts));
    }

    /**
     * Generate warnings for the StepExecution for each skipped attribute
     */
    protected function generateAttributesWarning()
    {
        $skippedAttribute = array_unique($this->skippedAttributes);

        foreach ($skippedAttribute as $skippedAttributeCode) {
            $this->stepExecution->incrementSummaryInfo('skipped_attributes');

            $this->stepExecution->addWarning(
                $this->getName(),
                'pim_enrich.mass_edit_action.edit-common-attributes.message.invalid_attribute',
                ['attribute_code' => $skippedAttributeCode],
                []
            );
        }
    }
}
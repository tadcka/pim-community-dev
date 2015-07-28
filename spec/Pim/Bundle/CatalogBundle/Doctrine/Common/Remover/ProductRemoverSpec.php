<?php

namespace spec\Pim\Bundle\CatalogBundle\Doctrine\Common\Remover;

use Akeneo\Component\StorageUtils\Remover\RemovingOptionsResolverInterface;
use Doctrine\Common\Persistence\ObjectManager;
use PhpSpec\ObjectBehavior;
use Pim\Bundle\CatalogBundle\Event\ProductEvents;
use Pim\Bundle\CatalogBundle\Model\ProductInterface;
use Prophecy\Argument;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ProductRemoverSpec extends ObjectBehavior
{
    function let(
        ObjectManager $objectManager,
        RemovingOptionsResolverInterface $optionsResolver,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->beConstructedWith($objectManager, $optionsResolver, $eventDispatcher);
    }

    function it_is_a_remover()
    {
        $this->shouldImplement('Akeneo\Component\StorageUtils\Remover\RemoverInterface');
    }

    function it_dispatches_an_event_when_removing_a_product(
        $eventDispatcher,
        $objectManager,
        $optionsResolver,
        ProductInterface $product
    ) {
        $optionsResolver->resolveRemoveOptions([])->willReturn(['flush' => true]);
        $eventDispatcher->dispatch(
            ProductEvents::PRE_REMOVE,
            Argument::type('Akeneo\Component\StorageUtils\Event\RemoveEvent')
        )->shouldBeCalled();

        $objectManager->remove($product)->shouldBeCalled();
        $objectManager->flush()->shouldBeCalled();

        $eventDispatcher->dispatch(
            ProductEvents::POST_REMOVE,
            Argument::type('Akeneo\Component\StorageUtils\Event\RemoveEvent')
        )->shouldBeCalled();

        $this->remove($product);
    }

    function it_throws_exception_when_remove_anything_else_than_a_product()
    {
        $anythingElse = new \stdClass();
        $this
            ->shouldThrow(
                new \InvalidArgumentException(
                    sprintf(
                        'Expects an "Pim\Bundle\CatalogBundle\Model\ProductInterface", "%s" provided.',
                        get_class($anythingElse)
                    )
                )
            )
            ->duringRemove($anythingElse);
    }
}

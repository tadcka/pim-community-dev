<?php

namespace Pim\Bundle\AnalyticsBundle\DataCollector;

use Akeneo\Component\Analytics\DataCollectorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Class TokenStorageDataCollector
 *
 * @author    Nicolas Dupont <nicolas@akeneo.com>
 * @copyright 2015 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class TokenStorageDataCollector implements DataCollectorInterface
{
    /** @var TokenStorageInterface */
    protected $tokenStorage;

    /**
     * @param TokenStorageInterface $tokenStorage
     */
    public function __construct(TokenStorageInterface $tokenStorage)
    {
        $this->tokenStorage = $tokenStorage;
    }

    /**
     * {@inheritdoc}
     */
    public function collect()
    {
        $userId = null;
        if (null !== $token = $this->tokenStorage->getToken()) {
            if (is_object($user = $token->getUser())) {
                $userId = $user->getId();
            }
        }

        return ['pim_user_id' => $userId];
    }
}

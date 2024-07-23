<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Brevo\Services;

use Brevo\Api\BrevoClient;
use Thelia\Model\CustomerQuery;

class BrevoCustomerService
{
    private $brevoClient;

    public function __construct(BrevoClient $brevoClient)
    {
        $this->brevoClient = $brevoClient;
    }

    /**
     * @throws \Exception
     */
    public function createUpdateContact($customerId)
    {
        $customer = CustomerQuery::create()->findPk($customerId);

        try {
            $contact = $this->brevoClient->checkIfContactExist($customer->getEmail());

            return $this->brevoClient->updateContact($contact[0]->getId(), $customer);
        } catch (\Exception $exception) {
            if ($exception->getCode() !== 404) {
                throw $exception;
            }

            return $this->brevoClient->createContact($customer->getEmail());
        }
    }
}

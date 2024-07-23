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

/*      Copyright (c) OpenStudio */
/*      email : dev@thelia.net */
/*      web : http://www.thelia.net */

/*      For the full copyright and license information, please view the LICENSE.txt */
/*      file that was distributed with this source code. */

namespace Brevo\EventListeners;

use Brevo\Api\BrevoClient;
use Brevo\Brevo;
use Brevo\Brevo as BrevoModule;
use Brevo\Model\BrevoNewsletter;
use Brevo\Model\BrevoNewsletterQuery;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Newsletter\NewsletterEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Translation\Translator;
use Thelia\Log\Tlog;
use Thelia\Model\ConfigQuery;
use Thelia\Model\NewsletterQuery;

/**
 * Class NewsletterListener.
 *
 * @author Chabreuil Antoine <achabreuil@openstudio.com>
 */
class NewsletterListener implements EventSubscriberInterface
{
    private $api;

    public function __construct(BrevoClient $api)
    {
        $this->api = $api;
    }

    public function subscribe(NewsletterEvent $event): void
    {
        if (null !== BrevoNewsletterQuery::create()->findPk($event->getId())) {
            return;
        }

        try {
            $contact = $this->api->subscribe($event);
            $function = 'registration';
            $status = $contact[1];
            $data = ['id' => $contact[0]['id']];
            $logMessage = $this->logAfterAction(
                sprintf("Email address successfully added for %s '%s'", $function, $event->getEmail()),
                sprintf(
                    "The email address %s was refused by brevo for action '%s'",
                    $event->getEmail(),
                    $function
                ),
                $status,
                $data
            );

            if ($logMessage) {
                $model = BrevoNewsletterQuery::create()->findOneByEmail($event->getEmail()) ?? new BrevoNewsletter();
                $model
                    ->setRelationId($data['id'])
                    ->setEmail($event->getEmail())
                    ->save();
            }
        } catch (\Exception $ex) {
            Tlog::getInstance()->error('Failed to process newsletter subscription : '.$ex->getMessage());
        }
    }

    public function update(NewsletterEvent $event): void
    {
        if (null === BrevoNewsletterQuery::create()->findPk($event->getId()) && null === NewsletterQuery::create()->findPk($event->getId())) {
            return;
        }

        try {
            $contact = $this->api->update($event);
            $function = 'update';
            $status = $contact[1];
            $data = ['id' => $contact[0]['id']];
            $logMessage = $this->logAfterAction(
                sprintf("Email address successfully added for %s '%s'", $function, $event->getEmail()),
                sprintf(
                    "The email address %s was refused by brevo for action '%s'",
                    $event->getEmail(),
                    $function
                ),
                $status,
                $data
            );

            if ($logMessage) {
                $model = BrevoNewsletterQuery::create()->findOneByEmail($event->getEmail()) ?? new BrevoNewsletter();
                $model
                    ->setRelationId($data['id'])
                    ->setEmail($event->getEmail())
                    ->save();
            }
        } catch (\Exception $ex) {
            Tlog::getInstance()->error('Failed to process newsletter subscription update : '.$ex->getMessage());
        }
    }

    public function unsubscribe(NewsletterEvent $event): void
    {
        try {
            $contact = $this->api->unsubscribe($event->getEmail());

            if (null === $model = BrevoNewsletterQuery::create()->findOneByEmail($event->getEmail())) {
                return;
            }

            $status = $contact[1];
            $data = ['id' => $model->getRelationId()];
            $logMessage = $this->logAfterAction(
                sprintf("The email address '%s' was successfully unsubscribed from the list", $event->getEmail()),
                sprintf("The email address '%s' was not unsubscribed from the list", $event->getEmail()),
                $status,
                $data
            );

            if ($logMessage) {
                $model
                    ->setRelationId(null)
                    ->save();
            }
        } catch (\Exception $ex) {
            Tlog::getInstance()->error('Failed to process newsletter unsubscription : '.$ex->getMessage());
        }
    }

    protected function isStatusOk($status)
    {
        return $status >= 200 && $status < 300;
    }

    protected function logAfterAction($successMessage, $errorMessage, $status, $data)
    {
        if ($this->isStatusOk($status)) {
            Tlog::getInstance()->info($successMessage);

            return true;
        } else {
            Tlog::getInstance()->error(sprintf('%s. Status code: %d, data: %s', $errorMessage, $status, $data));

            if (ConfigQuery::read(Brevo::CONFIG_THROW_EXCEPTION_ON_ERROR, false)) {
                throw new \InvalidArgumentException(
                    Translator::getInstance()?->trans(
                        'An error occurred during the newsletter registration process',
                        [],
                        BrevoModule::MESSAGE_DOMAIN
                    )
                );
            }

            return false;
        }
    }

    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * The array keys are event names and the value can be:
     *
     *  * The method name to call (priority defaults to 0)
     *  * An array composed of the method name to call and the priority
     *  * An array of arrays composed of the method names to call and respective
     *    priorities, or 0 if unset
     *
     * For instance:
     *
     *  * array('eventName' => 'methodName')
     *  * array('eventName' => array('methodName', $priority))
     *  * array('eventName' => array(array('methodName1', $priority), array('methodName2'))
     *
     * @return array The event names to listen to
     *
     * @api
     */
    public static function getSubscribedEvents()
    {
        return [
            TheliaEvents::NEWSLETTER_SUBSCRIBE => ['subscribe', 192], // Come before, as if it crashes, it won't be saved by thelia
            TheliaEvents::NEWSLETTER_UPDATE => ['update', 192],
            TheliaEvents::NEWSLETTER_UNSUBSCRIBE => ['unsubscribe', 192],
        ];
    }
}

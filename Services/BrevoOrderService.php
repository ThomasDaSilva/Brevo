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

use Propel\Runtime\Exception\PropelException;
use Thelia\Log\Tlog;
use Thelia\Model\Base\ProductQuery;
use Thelia\Model\Order;

class BrevoOrderService
{
    private $brevoApiService;

    public function __construct(BrevoApiService $brevoApiService)
    {
        $this->brevoApiService = $brevoApiService;
    }

    /**
     * @throws PropelException
     */
    public function exportOrder(Order $order, $locale): void
    {
        $data = $this->getOrderData($order, $locale);

        try {
            $this->brevoApiService->sendPostEvent('https://api.brevo.com/v3/orders/status', $data);
        } catch (\Exception $exception) {
            Tlog::getInstance()->error('Brevo order export error:'.$exception->getMessage());
        }
    }

    /**
     * @throws PropelException
     */
    public function exportOrderInBatch($limit, $offset, $locale): void
    {
        $orders = ProductQuery::create()
            ->setLimit($limit)
            ->setOffset($offset)
        ;

        $data = [];

        /** @var Order $order */
        foreach ($orders as $order) {
            $data[] = $this->getOrderData($order, $locale);
        }

        $batchData['orders'] = $data;

        try {
            $this->brevoApiService->sendPostEvent('https://api.brevo.com/v3/orders/status/batch', $batchData);
        } catch (\Exception $exception) {
            Tlog::getInstance()->error('Brevo order export error:'.$exception->getMessage());
        }
    }

    /**
     * @throws PropelException
     */
    protected function getOrderData(Order $order, $locale): array
    {
        $invoiceAddress = $order->getOrderAddressRelatedByInvoiceOrderAddressId();
        $addressCountry = $invoiceAddress->getCountry();
        $addressCountry->setLocale($locale);

        $coupons = array_map(static function ($coupon) {
            return $coupon['Code'];
        }, $order->getOrderCoupons()->toArray());

        return [
            'email' => $order->getCustomer()->getEmail(),
            'products' => $this->getOrderProductsData($order),
            'billing' => [
                'address' => $invoiceAddress->getAddress1(),
                'city' => $invoiceAddress->getCity(),
                'countryCode' => $addressCountry->getIsoalpha2(),
                'phone' => $invoiceAddress->getCellphone(),
                'postCode' => $invoiceAddress->getZipcode(),
                'paymentMethod' => $order->getPaymentModuleInstance()->getCode(),
                'region' => $addressCountry->getTitle(),
            ],
            'coupon' => $coupons,
            'id' => $order->getRef(),
            'createdAt' => $order->getCreatedAt()->format("Y-m-d\TH:m:s\Z"),
            'updatedAt' => $order->getUpdatedAt()->format("Y-m-d\TH:m:s\Z"),
            'status' => $order->getOrderStatus()->getCode(),
            'amount' => round($order->getTotalAmount($tax), 2),
        ];
    }

    /**
     * @throws PropelException
     */
    protected function getOrderProductsData(Order $order): array
    {
        $orderProductsData = [];
        foreach ($order->getOrderProducts() as $orderProduct) {
            $orderProductsData[] = [
                'productId' => $orderProduct->getProductRef(),
                'quantity' => $orderProduct->getQuantity(),
                // 'variantId' => (string) $orderProduct->getProductSaleElementsId(),
                'price' => round((float) $orderProduct->getPrice(), 2),
            ];
        }

        return $orderProductsData;
    }
}

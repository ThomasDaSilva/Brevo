<?php

namespace Brevo\Services;

use Thelia\Log\Tlog;
use Thelia\Model\Category;
use Thelia\Model\CategoryQuery;
use Thelia\Model\Product;
use Thelia\Tools\URL;

class BrevoCategoryService
{
    private $brevoApiService;

    public function __construct(BrevoApiService $brevoApiService)
    {
        $this->brevoApiService = $brevoApiService;
    }

    public function getObjName(): string
    {
        return 'category';
    }

    public function getCount(): int
    {
        return CategoryQuery::create()->count();
    }

    public function export(Category $category, $locale): void
    {
        $data = $this->getData($category, $locale);
        $data['updateEnabled'] = true;

        try {
            $this->brevoApiService->sendPostEvent('https://api.brevo.com/v3/categories', $data);
        } catch (\Exception $exception) {
            Tlog::getInstance()->error('Brevo category export error:'.$exception->getMessage());
        }
    }

    public function exportInBatch($limit, $offset, $locale): void
    {
        $categories = CategoryQuery::create()
            ->setLimit($limit)
            ->setOffset($offset)
        ;

        $data = [];

        /** @var Product $product */
        foreach ($categories as $category) {
            $data[] = $this->getData($category, $locale);
        }

        $batchData['categories'] = $data;
        $batchData['updateEnabled'] = true;

        try {
            $this->brevoApiService->sendPostEvent('https://api.brevo.com/v3/categories/batch', $batchData);
        } catch (\Exception $exception) {
            Tlog::getInstance()->error('Brevo category export error:'.$exception->getMessage());
        }
    }

    public function getData(Category $category, $locale): array
    {
        $category->setLocale($locale);

         return [
            'id' => (string)$category->getId(),
            'name' => $category->getTitle(),
            'url' => URL::getInstance()->absoluteUrl($category->getUrl()),
        ];
    }
}

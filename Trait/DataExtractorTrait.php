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

/*      web : https://www.openstudio.fr */

/*      For the full copyright and license information, please view the LICENSE */
/*      file that was distributed with this source code. */

/**
 * Created by Franck Allimant, OpenStudio <fallimant@openstudio.fr>
 * Projet: thelia25
 * Date: 17/11/2023.
 */

namespace Brevo\Trait;

use Brevo\Brevo;
use Propel\Runtime\Connection\ConnectionWrapper;
use Propel\Runtime\Propel;
use Thelia\Exception\TheliaProcessException;
use Thelia\Log\Tlog;
use Thelia\Model\ConfigQuery;

trait DataExtractorTrait
{
    public function getMappedValues(
        array $jsonMapping,
        string $mapKey,
        string $sourceTableName,
        string $selectorFieldName,
        mixed $selector,
        int $selectorType = \PDO::PARAM_INT
    ): array {
        try {
            if (empty($jsonMapping)) {
                return [];
            }

            if (!\array_key_exists($mapKey, $jsonMapping)) {
                return [];
            }

            $attributes = [];

            /** @var ConnectionWrapper $con */
            $con = Propel::getConnection();

            foreach ($jsonMapping[$mapKey] as $key => $dataQuery) {
                if (!\array_key_exists('select', $dataQuery)) {
                    throw new \Exception("Mapping error : 'select' element missing in ".$key.' query');
                }

                try {
                    $sql = 'SELECT '.$dataQuery['select'].' AS '.$key.' FROM '.$sourceTableName;

                    if (\array_key_exists('join', $dataQuery)) {
                        if (!\is_array($dataQuery['join'])) {
                            $dataQuery['join'] = [$dataQuery['join']];
                        }

                        foreach ($dataQuery['join'] as $join) {
                            $sql .= ' LEFT JOIN '.$join;
                        }
                    }

                    $sql .= ' WHERE '.$selectorFieldName.' = :selector';

                    if (\array_key_exists('groupBy', $dataQuery)) {
                        $sql .= ' GROUP BY '.$dataQuery['groupBy'];
                    }

                    $stmt = $con->prepare($sql);
                    $stmt->bindValue(':selector', $selector, $selectorType);
                    $stmt->execute();

                    while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                        //  value should be less than 255 characters
                        $attributes[$key] = mb_substr($row[$key] ?? '', 0, 254);
                        if (\array_key_exists($key, $jsonMapping) && \array_key_exists($row[$key], $jsonMapping[$key])) {
                            $attributes[$key] = $jsonMapping[$key][$row[$key]];
                        }
                    }
                } catch (\Exception $ex) {
                    Tlog::getInstance()->error(
                        'Failed to execute SQL request to map Brevo attribute "'.$key.'". Error is '.$ex->getMessage().", request is : $sql");
                }
            }

            return $attributes;
        } catch (\Exception $ex) {
            throw new TheliaProcessException(
                'Mapping error : configuration is missing or invalid, please go to the module configuration and define the JSON mapping to match thelia attribute with brevo attribute. Error is : '.$ex->getMessage()
            );
        }
    }

    public function getCustomerAttribute($customerId): array
    {
        $mappingString = ConfigQuery::read(Brevo::BREVO_ATTRIBUTES_MAPPING);

        if (empty($mappingString)) {
            return [];
        }

        if (null === $mapping = json_decode($mappingString, true)) {
            throw new TheliaProcessException('Customer attribute mapping error: JSON data seems invalid, pleas echeck syntax.');
        }

        return $this->getMappedValues(
            $mapping,
            'customer_query',
            'customer',
            'customer.id',
            $customerId,
        );
    }
}
<?php

namespace Walmart;

class XmlParser
{

    protected static array $upperFields = [
        'currency',
        'status',
        'unitOfMeasurement',
        'chargeType',
        'fulfillmentOption',
        'shipMethod',
        'methodCode',
    ];

    public static function parse($xml, $type = null)
    {
        $xml = simplexml_load_string($xml);

        if($type === null) {
            return $xml;
        }else {
            switch ($type) {
                case '\Walmart\Models\MP\CA\Orders\OrdersListType':
                    $array = self::xmlToArray($xml);
                    /*dump($array);
                    $obj = self::arrayToObject($array, '\Walmart\Models\MP\CA\Orders');
                    dump($obj);
                    return self::parseOrder($xml);*/
                    break;
                case '\Walmart\Models\MP\CA\Items\ItemResponses':
                    $array = self::xmlToArray($xml);
                    //return self::parseItems($xml);
                    break;
            }
            return $array;
        }
    }

    protected static function parseItems($xml)
    {
        $namespaces = $xml->getNamespaces(true);
        $return = new \Walmart\Models\MP\CA\Items\ItemResponses();
        foreach ($xml as $key => $value) {
            $parsedValue = self::parseXmlRecursive($value);
            $setter = 'set' . ucfirst($key);
            if (method_exists($return, $setter)) {
                $return->$setter($parsedValue);
            } else {
                $return->$key = $parsedValue;
            }
        }

        return $return;
    }

    protected static function parseOrder($xml)
    {
        $namespaces = $xml->getNamespaces(true);
        $return = new \Walmart\Models\MP\CA\Orders\OrdersListType();
        $ns3 = $xml->children($namespaces['ns3']);
        $return->setMeta(self::setMeta($ns3->meta, '\Walmart\Models\MP\CA\Orders'));
        $return->setElements(self::setElements($ns3->elements, '\Walmart\Models\MP\CA\Orders'));
        return $return;
    }

    static function setMeta($meta, $namespace = null)
    {
        $metaType = $namespace.'\MetaType';
        $return = new $metaType();
        $return->setTotalCount($meta->totalCount);
        $return->setLimit($meta->limit);
        return $return;
    }

    public static function setElements($elements, string $namespace = null)
    {
        $elementsType = $namespace . '\ElementsType';
        $return = new $elementsType();
        $return->setOrder(self::setOrders($elements->order, $namespace.'\Order'));
        return $return;
    }

    protected static function setOrders($orders, $type = null)
    {
        $return = [];
        foreach ($orders as $order) {
            $orderReturn = new $type();
            foreach ($order as $key => $value) {
                $parsedValue = self::parseXmlRecursive($value);
                $setter = 'set' . ucfirst($key);
                if (method_exists($orderReturn, $setter)) {
                    $orderReturn->$setter($parsedValue);
                } else {
                    $orderReturn->$key = $parsedValue;
                }
            }
            $return[] = $orderReturn;
        }
        return $return;
    }


    protected static function parseXmlRecursive(\SimpleXMLElement $node)
    {
        $namespaces = $node->getNamespaces(true);
        $ns = $namespaces ? reset($namespaces) : null;
        $children = $node->children($ns);
        if (!count($children)) {
            return self::castValue((string) $node);
        }
        $result = [];

        /*foreach ($children as $child) {
            $name = $child->getName();
            $value = self::parseXmlRecursive($child);
            if (isset($result[$name])) {
                if (!is_array($result[$name]) || !array_is_list($result[$name])) {
                    $result[$name] = [$result[$name]];
                }
                $result[$name][] = $value;
            } else {
                $result[$name] = $value;
            }
        }*/

        foreach ($children as $child) {
            $name = $child->getName();
            $value = self::parseXmlRecursive($child);
            $result[$name][] = $value;
        }

        foreach ($result as $key => $values) {
            if (count($values) === 1) {
                $result[$key] = $values[0];
            }
        }
        return $result;
    }

    protected static function castValue(string $value)
    {
        $value = trim($value);
        if (preg_match('/^-?\d+$/', $value)) {
            return (int) $value;
        }
        if (is_numeric($value)) {
            return (float) $value;
        }
        return $value;
    }

    protected static function xmlToArray(\SimpleXMLElement $node)
    {
        $children = [];
        foreach ($node->children() as $child) {
            $children[] = $child;
        }
        foreach ($node->getNamespaces(true) as $ns) {
            foreach ($node->children($ns) as $child) {
                $children[] = $child;
            }
        }
        if (!$children) {
            return self::castValue((string) $node);
        }
        $result = [];
        foreach ($children as $child) {
            $key = $child->getName();
            $key = lcfirst($key);
            $value = self::xmlToArray($child);
            if (array_key_exists($key, $result)) {
                if (!is_array($result[$key]) || !array_is_list($result[$key])) {
                    $result[$key] = [$result[$key]];
                }
                $result[$key][] = $value;
            } else {
                $result[$key] = $value;
            }
        }
        return $result;
    }

    protected static function arrayToObject(
        array $data,
        string $namespace,
        ?object $object = null
    ): object {
        if ($object === null) {
            $object = new \stdClass();
        }
        foreach ($data as $key => $value) {
            $value = self::normalizeValue($key, $value);
            $setter = 'set' . ucfirst($key);
            $className = $namespace . '\\' . ucfirst($key);
            if (is_array($value) && array_is_list($value)) {
                $items = [];
                foreach ($value as $item) {
                    if (is_array($item)) {
                        if (class_exists($className)) {
                            $items[] = self::arrayToObject($item, $namespace, new $className());
                        } else if (class_exists($className.'Type')) {
                            $className = $className.'Type';
                            $items[] = self::arrayToObject($item, $namespace, new $className());
                        } else {
                            $items[] = self::arrayToObject($item, $namespace);
                        }
                    } else {
                        $items[] = $item;
                    }
                }

                if (method_exists($object, $setter)) {
                    $object->$setter($items);
                } else {
                    $object->$key = $items;
                }

                continue;
            }
            if (is_array($value)) {
                if (class_exists($className)) {
                    $childObject = new $className();
                } else if (class_exists($className.'Type')) {
                    $className = $className.'Type';
                    $childObject = new $className();
                } else {
                    $childObject = new \stdClass();
                }

                $childObject = self::arrayToObject($value, $namespace, $childObject);

                if (method_exists($object, $setter)) {
                    $object->$setter($childObject);
                } else {
                    $object->$key = $childObject;
                }

                continue;
            }
            if (method_exists($object, $setter)) {
                $object->$setter($value);
            } else {
                $object->$key = $value;
            }
        }
        return $object;
    }

    protected static function normalizeValue(string $key, $value)
    {
        if (is_string($value) && in_array($key, self::$upperFields, true)) {
            return strtoupper($value);
        }
        return $value;
    }

}

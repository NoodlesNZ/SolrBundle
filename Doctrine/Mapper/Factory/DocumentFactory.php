<?php

namespace FS\SolrBundle\Doctrine\Mapper\Factory;

use Doctrine\Common\Collections\Collection;
use FS\SolrBundle\Doctrine\Annotation\Field;
use FS\SolrBundle\Doctrine\Mapper\MetaInformationFactory;
use FS\SolrBundle\Doctrine\Mapper\MetaInformationInterface;
use FS\SolrBundle\Doctrine\Mapper\SolrMappingException;
use Ramsey\Uuid\Uuid;
use Solarium\QueryType\Update\Query\Document\Document;

class DocumentFactory
{
    /**
     * @var MetaInformationFactory
     */
    private $metaInformationFactory;

    /**
     * @param MetaInformationFactory $metaInformationFactory
     */
    public function __construct(MetaInformationFactory $metaInformationFactory)
    {
        $this->metaInformationFactory = $metaInformationFactory;
    }

    /**
     * @param MetaInformationInterface $metaInformation
     *
     * @return null|Document
     */
    public function createDocument(MetaInformationInterface $metaInformation)
    {
        $fields = $metaInformation->getFields();
        if (count($fields) == 0) {
            return null;
        }

        $document = new Document();

        $documentId = $metaInformation->getDocumentKey();
        if ($metaInformation->generateDocumentId()) {
            $documentId = $metaInformation->getDocumentName() . '_' . Uuid::uuid1()->toString();
        }
        $document->setKey(MetaInformationInterface::DOCUMENT_KEY_FIELD_NAME, $documentId);

        $document->setBoost($metaInformation->getBoost());

        foreach ($fields as $field) {
            if (!$field instanceof Field) {
                continue;
            }

            $value = $field->getValue();
            if ($value instanceof Collection) {
                $document->addField($field->getNameWithAlias(), $this->mapCollection($field), $field->getBoost());
            } elseif (is_object($value)) {
                $document->addField($field->getNameWithAlias(), $this->mapObject($field), $field->getBoost());
            } else {
                $document->addField($field->getNameWithAlias(), $field->getValue(), $field->getBoost());
            }

            if ($field->getFieldModifier()) {
                $document->setFieldModifier($field->getNameWithAlias(), $field->getFieldModifier());
            }
        }

        return $document;
    }

    /**
     * @param Field $field
     *
     * @return array|string
     *
     * @throws SolrMappingException if getter return value is object
     */
    private function mapObject(Field $field)
    {
        $value = $field->getValue();
        $getter = $field->getGetterName();
        if (!empty($getter)) {
            $getterReturnValue = $this->callGetterMethod($value, $getter);

            if (is_object($getterReturnValue)) {
                throw new SolrMappingException('The configured getter must return a string or array, got object');
            }

            return $getterReturnValue;
        }

        $metaInformation = $this->metaInformationFactory->loadInformation($value);

        $field = array();
        $document = $this->createDocument($metaInformation);
        foreach ($document as $fieldName => $value) {
            $field[$fieldName] = $value;
        }

        return $field;
    }

    /**
     * @param object $object
     * @param string $getter
     *
     * @return mixed
     */
    private function callGetterMethod($object, $getter)
    {
        $methodName = $getter;
        if (strpos($getter, '(') !== false) {
            $methodName = substr($getter, 0, strpos($getter, '('));
        }

        $method = new \ReflectionMethod($object, $methodName);
        // getter with arguments
        if (strpos($getter, ')') !== false) {
            $getterArguments = explode(',', substr($getter, strpos($getter, '(') + 1, -1));
            $getterArguments = array_map(function ($parameter) {
                return trim(preg_replace('#[\'"]#', '', $parameter));
            }, $getterArguments);

            return $method->invokeArgs($object, $getterArguments);
        }

        return $method->invoke($object);
    }

    /**
     * @param Field $field
     *
     * @return array
     */
    private function mapCollection(Field $field)
    {
        /** @var Collection $value */
        $value = $field->getValue();
        $getter = $field->getGetterName();
        if (!empty($getter)) {
            $values = array();
            foreach ($value as $relatedObj) {
                $values[] = $relatedObj->{$getter}();
            }

            return $values;
        }

        $collection = array();
        foreach ($value as $object) {
            $metaInformation = $this->metaInformationFactory->loadInformation($object);

            $field = array();
            $document = $this->createDocument($metaInformation);
            foreach ($document as $fieldName => $value) {
                $field[$fieldName] = $value;
            }

            $collection[] = $field;
        }

        return $collection;
    }
}
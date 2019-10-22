<?php

namespace W2w\Lib\Apie\Normalizers;

use PhpValueObjects\AbstractStringValueObject;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use W2w\Lib\Apie\ValueObjects\ValueObjectInterface;

/**
 * Normalizer that normalizes value objects implementing ValueObjectInterface
 */
class ValueObjectNormalizer implements NormalizerInterface, DenormalizerInterface
{
    /**
     * @param mixed $data
     * @param string $class
     * @param string|null $format
     * @param array $context
     * @return ValueObjectInterface
     */
    public function denormalize($data, $class, $format = null, array $context = [])
    {
        return $class::fromNative($data);
    }

    /**
     * @param mixed $data
     * @param string $type
     * @param string|null $format
     * @return bool
     */
    public function supportsDenormalization($data, $type, $format = null)
    {
        return is_a($type, ValueObjectInterface::class, true);
    }

    /**
     * @param AbstractStringValueObject $object
     * @param string|null $format
     * @param array $context
     * @return mixed
     */
    public function normalize($object, $format = null, array $context = [])
    {
        return $object->toNative();
    }

    /**
     * @param mixed $data
     * @param null $format
     * @return bool
     */
    public function supportsNormalization($data, $format = null)
    {
        return $data instanceof ValueObjectInterface;
    }
}

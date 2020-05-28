<?php

namespace Spatie\Enum;

use BadMethodCallException;
use ReflectionClass;
use Spatie\Enum\Exceptions\DuplicateLabelsException;
use Spatie\Enum\Exceptions\DuplicateValuesException;

/**
 * @property-read string value
 * @property-read string label
 */
abstract class Enum
{
    /** @var string|int */
    protected $value;

    protected string $label;

    private static array $definitionCache = [];

    public static function toArray(): array
    {
        $array = [];

        foreach (static::resolveDefinition() as $definition) {
            $array[$definition->value] = $definition->label;
        }

        return $array;
    }

    /**
     * @param string|int $value
     */
    public function __construct($value)
    {
        $definition = $this->findDefinition($value);

        if ($definition === null) {
            $enumClass = static::class;

            throw new BadMethodCallException("There's no value {$value} defined for enum {$enumClass}, consider adding it in the docblock definition.");
        }

        $this->value = $definition->value;
        $this->label = $definition->label;
    }

    public function __get($name)
    {
        if ($name === 'label') {
            return $this->label;
        }

        if ($name === 'value') {
            return $this->value;
        }
    }

    public static function __callStatic(string $name, array $arguments)
    {
        return new static($name);
    }

    public function __call($name, $arguments)
    {
        if (strpos($name, 'is') === 0) {
            $other = new static(lcfirst(str_replace('is', '', $name)));

            return $this->equals($other);
        }
    }

    public function equals(Enum ...$others): bool
    {
        foreach ($others as $other) {
            if (
                get_class($this) === get_class($other)
                && $this->value === $other->value
            ) {
                return true;
            }
        }

        return false;
    }

    protected static function values(): array
    {
        return [];
    }

    protected static function labels(): array
    {
        return [];
    }

    private function findDefinition($input): ?EnumDefinition
    {
        foreach (static::resolveDefinition() as $definition) {
            if ($definition->equals($input)) {
                return $definition;
            }
        }

        return null;
    }

    /**
     * @return \Spatie\Enum\EnumDefinition[]|null
     */
    private static function resolveDefinition(): array
    {
        $className = static::class;

        if (static::$definitionCache[$className] ?? null) {
            return static::$definitionCache[$className];
        }

        $reflectionClass = new ReflectionClass($className);

        $docComment = $reflectionClass->getDocComment();

        preg_match_all('/@method static self ([\w_]+)\(\)/', $docComment, $matches);

        $definition = [];

        $valueMap = static::values();

        if (self::arrayHasDuplicates($valueMap)) {
            throw new DuplicateValuesException(static::class);
        }

        $labelMap = static::labels();

        if (self::arrayHasDuplicates($labelMap)) {
            throw new DuplicateLabelsException(static::class);
        }

        foreach ($matches[1] as $methodName) {
            $definition[$methodName] = new EnumDefinition(
                $methodName,
                $valueMap[$methodName] ?? $methodName,
                $labelMap[$methodName] ?? $methodName,
            );
        }

        return static::$definitionCache[$className] ??= $definition;
    }

    private static function arrayHasDuplicates(array $array): bool
    {
        return count($array) > count(array_unique($array));
    }
}

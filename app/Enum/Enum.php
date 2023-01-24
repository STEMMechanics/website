<?php

namespace App\Enum;

use ReflectionClass;

class Enum
{
    /**
     * Caches reflections of enum subclasses.
     *
     * @var array<class-string<static>, ReflectionClass<static>>
     */
    public static $reflectionCache = [];


    /**
     * Returns a reflection of the enum subclass.
     *
     * @return ReflectionClass<static>
     */
    public static function getReflection(): ReflectionClass
    {
        $class = static::class;

        return static::$reflectionCache[$class] ??= new ReflectionClass($class);
    }


    /**
     * Returns the constants in the enum subclass
     *
     * @return array<static>
     */
    public static function getConstants(): array
    {
        return static::getReflection()->getConstants();
    }


    /**
     * Returns the constants values in the enum subclass
     *
     * @return array<static>
     */
    public static function getConstantValues(): array
    {
        return array_values(static::getReflection()->getConstants());
    }
}

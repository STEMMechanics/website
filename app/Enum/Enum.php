<?php

namespace App\Enum;

use ReflectionClass;

class Enum
{
    /**
     * Message list
     * 
     * @var array<string<static>>
     */
    public static $messages = [];

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

    /**
     * Returns a message from the enum subclass
     * 
     * @return string
     */
    public static function getMessage(int $messageIndex, string $defaultMessage = 'Unknown'): string
    {
        if(array_key_exists($messageIndex, self::$messages) === true) {
            return self::$messages[$messageIndex];
        }

        return $defaultMessage;
    }
}

<?php

namespace App\MediaServices;

class MediaServiceData
{
    /**
     * The data array.
     *
     * @var array
     */
    protected $data = [];

    /**
     * Get the data from the data array.
     *
     * @param string $key The key to get.
     * @param mixed $default The default value to return if the key does not exist.
     * @return mixed The value of the key.
     */
    public function get(string $key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * Set the data in the data array.
     *
     * @param string $key The key to set.
     * @param mixed $value The value to set.
     * @return void
     */
    public function set(string $key, $value): void
    {
        $this->data[$key] = $value;
    }

    /**
     * Get the data from the data array or create it if it does not exist.
     *
     * @param string $key The key to get.
     * @param callable $default The default value to return if the key does not exist.
     * @return mixed The value of the key.
     */
    public function getChainData(string $key, callable $default)
    {
        if (!isset($this->data[$key])) {
            $this->data[$key] = $default();
        }

        return $this->data[$key];
    }
}

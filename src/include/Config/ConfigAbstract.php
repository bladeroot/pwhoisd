<?php declare(strict_types=1);
/**
 * HSDN PHP Whois Server Daemon
 *
 * @author      HSDN Team
 * @copyright   (c) 2015, Information Networks Ltd.
 * @link        http://www.hsdn.org
 */

namespace pWhoisd\Config;

use RuntimeException;

/**
 * Abstract Config implementing ConfigInterface.
 */
abstract class ConfigAbstract implements ConfigInterface
{
    protected array $data = [];

    public function set(string|array $key, mixed $value = null): self
    {
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $this->set($k, $v);
            }
        } elseif (strpos($key, '.') !== false) {
            $this->setDotNotationKey($key, $value);
        } elseif (is_array($value) && $this->containsOnlyStringKeys($value)) {
            foreach ($value as $k => $v) {
                $this->set($key.'.'.$k, $v);
            }
        } else {
            $this->data[$key] = $value;
        }

        return $this;
    }

    public function has(?string $key = null): bool
    {
        if (is_null($key)) {
            return !empty($this->data);
        }

        $segs = explode('.', $key);
        $root = $this->data;

        foreach ($segs as $part) {
            if (!is_array($root) || !array_key_exists($part, $root)) {
                return false;
            }

            $root = $root[$part];
        }

        return true;
    }

    public function get(?string $key = null, mixed $default = null): mixed
    {
        if (is_null($key)) {
            return $this->data;
        }

        if (!$this->has($key)) {
            if ($default === null) {
                throw new RuntimeException('Specified key not found in configuration');
            }

            return $default;
        }

        $segs = explode('.', $key);
        $root = $this->data;

        foreach ($segs as $part) {
            $root = $root[$part];
        }

        return $root;
    }

    public function remove(string $key): self
    {
        if ($this->has($key)) {
            $segs = explode('.', $key);
            $root = &$this->data;

            foreach ($segs as $part) {
                $parent = &$root;
                $root = &$root[$part];
            }

            unset($parent[$part]);
        }

        return $this;
    }

    public function clear(): void
    {
        $this->data = [];
    }

    /**
     * Handle setting a configuration value with a dot notation key.
     */
    protected function setDotNotationKey(string $key, mixed $value): void
    {
        $splitKey = explode('.', $key);
        $root = &$this->data;

        while ($part = array_shift($splitKey)) {
            if (!isset($root[$part]) && count($splitKey)) {
                $root[$part] = [];
            }

            $root = &$root[$part];
        }

        $root = $value;
    }

    /**
     * Check if an array contains only string keys.
     */
    protected function containsOnlyStringKeys(array $array): bool
    {
        return count($array) === count(array_filter(array_keys($array), 'is_string'));
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->set($offset, $value);
    }

    public function offsetExists(mixed $offset): bool
    {
        return $this->has($offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->get($offset);
    }

    public function offsetUnset(mixed $offset): void
    {
        $this->remove($offset);
    }
}

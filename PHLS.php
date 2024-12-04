<?php

/**
 * PHSD is a PHP Library for handling local data storage with expiration.
 * 
 * @category Library
 * @package  PHSD
 */
class PHLS {
    private static $data = [];
    private static $file = '.env';

    /**
     * Initialize the data storage from the file.
     */
    private static function init() {
        if (file_exists(self::$file)) {
            self::$data = json_decode(file_get_contents(self::$file), true) ?: [];
        } else {
            self::$data = [];
        }
        self::expireAllExpired();
    }

    /**
     * Parse a nested key string into an array of keys.
     * Example: 'user=>settings=>theme' => ['user', 'settings', 'theme']
     *
     * @param string $key The nested key string.
     * @return array An array of individual keys.
     */
    private static function parseNestedKey($key) {
        return explode('=>', $key);
    }

    /**
     * Save the data storage to the file, each data on a new line.
     */
    private static function save() {
        $jsonData = json_encode(self::$data, JSON_PRETTY_PRINT);
        file_put_contents(self::$file, $jsonData . PHP_EOL);
    }

    /**
     * Add a key-value pair to the data storage with optional expiration.
     * Supports nested keys (e.g., 'user=>settings=>theme').
     *
     * @param string $key The key to add.
     * @param mixed $value The value to add.
     * @param int|null $expiration Expiration time in minutes (optional).
     * @param bool $array If true, allow multiple values on a key; otherwise, overwrite existing values.
     * @return void
     */
    public static function add($key, $value, $expiration = null, $array = false) {
        self::init();
        self::expireAllExpired();
        $keys = self::parseNestedKey($key);
        $dataPointer = &self::$data;

        foreach ($keys as $keyPart) {
            if (!isset($dataPointer[$keyPart])) {
                $dataPointer[$keyPart] = [];
            }
            $dataPointer = &$dataPointer[$keyPart];
        }

        if ($array) {
            $dataPointer[] = [
                'value' => $value,
                'expiration' => self::calculateExpiration($expiration)
            ];
        } else {
            $dataPointer = [
                'value' => $value,
                'expiration' => self::calculateExpiration($expiration)
            ];
        }

        self::save();
    }

    /**
     * Add a new value under a key while limiting the maximum number of values.
     * New values are added at the beginning; if the array exceeds the limit, older values are removed.
     *
     * @param string $key The key to store the value under.
     * @param mixed $value The value to add.
     * @param int $limit The maximum number of values to store (default: 20).
     * @param int|null $expiration Expiration time in minutes (optional).
     * @return void
     */
    public static function limitizer($key, $value, $limit = 20, $expiration = null) {
        self::init();
        self::expireAllExpired();
    
        if (!isset(self::$data[$key])) {
            self::$data[$key] = [];
        }
    
        array_unshift(self::$data[$key], ['value' => $value, 'expiration' => self::calculateExpiration($expiration)]);
    
        if (count(self::$data[$key]) > $limit) {
            self::$data[$key] = array_slice(self::$data[$key], 0, $limit);
        }
    
        self::save();
    }

    /**
     * Update the value and expiration of an existing key.
     *
     * @param string $key The nested key string.
     * @param mixed $value The new value.
     * @param int|null $expiration New expiration time in minutes (optional).
     * @return void
     */
    public static function update($key, $value, $expiration = null) {
        self::init();
        $keys = self::parseNestedKey($key);
        $dataPointer = &self::$data;

        foreach ($keys as $keyPart) {
            if (!isset($dataPointer[$keyPart])) {
                return;
            }
            $dataPointer = &$dataPointer[$keyPart];
        }

        $dataPointer = [
            'value' => $value,
            'expiration' => self::calculateExpiration($expiration)
        ];

        self::save();
    }

    /**
     * Remove a specific nested key from the data storage.
     *
     * @param string $key The nested key string.
     * @return void
     */
    public static function remove($key) {
        self::init();
        self::expireAllExpired();
        $keys = self::parseNestedKey($key);
        $dataPointer = &self::$data;

        foreach ($keys as $keyPart) {
            if (!isset($dataPointer[$keyPart])) {
                return;
            }
            $lastPointer = &$dataPointer;
            $dataPointer = &$dataPointer[$keyPart];
        }

        unset($lastPointer[$keyPart]);
        self::save();
    }

    /**
     * Retrieve the value for a given nested key.
     *
     * @param string $key The nested key string.
     * @return mixed|null The value or null if not found or expired.
     */
    public static function get($key) {
        self::init();
        $keys = self::parseNestedKey($key);
        $dataPointer = self::$data;
    
        foreach ($keys as $keyPart) {
            if (isset($dataPointer[$keyPart])) {
                $dataPointer = $dataPointer[$keyPart];
            } else {
                return null;
            }
        }
    
        if (is_array($dataPointer)) {
            $validValues = array_filter($dataPointer, function ($item) {
                return !self::isExpired($item);
            });
    
            if (count($validValues) > 1) {
                return array_column($validValues, 'value');
            }
    
            $singleValue = reset($validValues);
            return $singleValue ? $singleValue['value'] : null;
        }
    
        return self::isExpired($dataPointer) ? null : $dataPointer['value'];
    }

    /**
     * Get all key-value pairs from the data storage.
     *
     * @return array The entire data storage.
     */
    public static function getAll() {
        self::init();
        self::expireAllExpired();
        return self::$data;
    }

    /**
     * Expire a specific key immediately.
     *
     * @param string $key The key to expire.
     * @return void
     */
    public static function expire($key) {
        if (self::exists($key)) {
            self::$data[$key]['expiration'] = time();
            self::save();
        }
    }

    /**
     * Expire all keys immediately.
     *
     * @return void
     */
    public static function expireAll() {
        foreach (self::$data as $key => $value) {
            self::$data[$key]['expiration'] = time();
        }
        self::save();
    }

    /**
     * Get details of all expired keys.
     *
     * @return array The expired key-value pairs.
     */
    public static function getExpiredDetails() {
        $expired = [];
        foreach (self::$data as $key => $value) {
            if (isset($value['expiration']) && $value['expiration'] !== null && $value['expiration'] <= time()) {
                $expired[$key] = $value;
            }
        }
        return $expired;
    }

    /**
     * Get details of all active keys.
     *
     * @return array The active key-value pairs.
     */
    public static function getActiveDetails() {
        $active = [];
        foreach (self::$data as $key => $value) {
            if ($value['expiration'] === null || $value['expiration'] > time()) {
                $active[$key] = $value;
            }
        }
        return $active;
    }

    /**
     * Remove all keys from the data storage.
     *
     * @return void
     */
    public static function removeAll() {
        self::$data = [];
        self::save();
    }

    /**
     * Remove all expired keys from the data storage.
     *
     * @return void
     */
    public static function expireAllExpired() {
        self::$data = self::cleanExpired(self::$data);
        self::save();
    }
    
    /**
     * Check if a key exists in the data storage.
     *
     * @param string $key The nested key string.
     * @return bool True if the key exists, false otherwise.
     */
    private static function exists($key) {
        self::init();
        $keys = self::parseNestedKey($key);
        $dataPointer = self::$data;

        foreach ($keys as $keyPart) {
            if (isset($dataPointer[$keyPart])) {
                $dataPointer = $dataPointer[$keyPart];
            } else {
                return false;
            }
        }

        return true;
    }

    /**
     * Recursively remove expired or empty entries from an array.
     * If all values under a key are expired or empty, remove the key as well.
     *
     * @param array $data The data array to clean.
     * @return array Cleaned data array.
     */
    private static function cleanExpired($data) {
        foreach ($data as $key => $value) {
            if (is_array($value) && isset($value['expiration'])) {
                if (self::isExpired($value)) {
                    unset($data[$key]);
                }
            } elseif (is_array($value)) {
                $data[$key] = self::cleanExpired($value);

                if (empty($data[$key])) {
                    unset($data[$key]);
                }
            }
        }
        return $data;
    }

    /**
     * Calculate the expiration timestamp based on the given expiration time in minutes.
     *
     * @param int|null $expiration Expiration time in minutes.
     * @return int|null Expiration timestamp or null if not set.
     */
    private static function calculateExpiration($expiration) {
        return $expiration !== null ? time() + ($expiration * 60) : null;
    }

    /**
     * Check if a value is expired.
     *
     * @param array $value The value array.
     * @return bool True if expired, false otherwise.
     */
    private static function isExpired($value) {
        if (is_array($value) && isset($value['expiration'])) {
            return $value['expiration'] !== null && $value['expiration'] <= time();
        }
        return false;
    }
}
?>

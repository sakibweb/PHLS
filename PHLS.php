<?php
/**
 * Class PHLS
 * Author: Sakibur Rahman @sakibweb
 * The PHLS class provides methods to manage stored values with expiration times.
 */
 
class PHLS {
    /** @var array $storage The storage for values with expiration times. */
    private static $storage = [];

    /**
     * Add a value to the storage with an optional expiration time.
     *
     * @param string $key The key to store the value.
     * @param mixed $value The value to store.
     * @param int|null $expiration The expiration time for the value in minutes. If 0, value is removed immediately.
     * @return void
     */
    public static function add($key, $value, $expiration = null) {
        if ($expiration === 0) {
            self::remove($key);
        } else {
            self::$storage[$key] = [
                'value' => $value,
                'expiration' => self::calculateExpiration($expiration)
            ];
        }
    }

    /**
     * Update a value associated with a key in the storage.
     *
     * @param string $key The key of the value to update.
     * @param mixed $value The new value.
     * @return void
     */
    public static function update($key, $value) {
        if (self::exists($key)) {
            self::$storage[$key]['value'] = $value;
        }
    }

    /**
     * Remove a value associated with a key from the storage.
     *
     * @param string $key The key of the value to remove.
     * @return void
     */
    public static function remove($key) {
        if (self::exists($key)) {
            unset(self::$storage[$key]);
        }
    }

    /**
     * Check if a value associated with a key has expired.
     *
     * @param string $key The key of the value to check.
     * @return bool True if the value has expired, false otherwise.
     */
    public static function expired($key) {
        if (self::exists($key)) {
            return self::$storage[$key]['expiration'] <= time();
        }
        return true;
    }

    /**
     * Check if a value associated with a key is still active.
     *
     * @param string $key The key of the value to check.
     * @return bool True if the value is active, false if it has expired or doesn't exist.
     */
    public static function active($key) {
        return !self::expired($key);
    }

    /**
     * Retrieve a value associated with a key if it is still active.
     *
     * @param string $key The key of the value to retrieve.
     * @return mixed|null The value if active, or null if it has expired or doesn't exist.
     */
    public static function get($key) {
        if (self::active($key)) {
            return self::$storage[$key]['value'];
        }
        return null;
    }

    /**
     * Get details of all expired values in the storage.
     *
     * @return array An associative array containing details of expired values.
     */
    public static function getExpiredDetails() {
        $expiredDetails = [];
        foreach (self::$storage as $key => $data) {
            if (self::expired($key)) {
                $expiredDetails[$key] = $data;
            }
        }
        return $expiredDetails;
    }

    /**
     * Mark a value associated with a key as expired.
     *
     * @param string $key The key of the value to mark as expired.
     * @return void
     */
    public static function makeExpired($key) {
        if (self::exists($key)) {
            self::$storage[$key]['expiration'] = time() - 1;
        }
    }

    /**
     * Mark all values in the storage as expired.
     *
     * @return void
     */
    public static function expiredAll() {
        foreach (self::$storage as $key => $data) {
            self::makeExpired($key);
        }
    }

    /**
     * Remove all values from the storage.
     *
     * @return void
     */
    public static function removeAll() {
        self::$storage = [];
    }

    /**
     * Make all expired values inactive.
     *
     * @return void
     */
    public static function activeAll() {
        foreach (self::$storage as $key => $data) {
            if (self::expired($key)) {
                self::remove($key);
            }
        }
    }

    /**
     * Retrieve all active values from the storage.
     *
     * @return array An associative array containing active values.
     */
    public static function getAll() {
        $activeData = [];
        foreach (self::$storage as $key => $data) {
            if (self::active($key)) {
                $activeData[$key] = $data['value'];
            }
        }
        return $activeData;
    }

    /**
     * Check if a value associated with a key exists in the storage.
     *
     * @param string $key The key to check.
     * @return bool True if the key exists, false otherwise.
     */
    private static function exists($key) {
        return isset(self::$storage[$key]);
    }

    /**
     * Calculate the expiration time based on the provided minutes.
     *
     * @param int|null $expiration The expiration time in minutes.
     * @return int The calculated expiration time as a Unix timestamp.
     */
    private static function calculateExpiration($expiration) {
        if ($expiration === null) {
            return PHP_INT_MAX;
        }
        return time() + ($expiration * 60);
    }
}
?>

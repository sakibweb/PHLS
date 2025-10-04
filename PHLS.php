<?php
/**
 * PHLS: PHP High-Performance Local Storage Library (SQLite Powered)
 * A zero-configuration, file-based, high-concurrency key-value storage engine.
 *
 * @category Library
 * @package  PHLS
 * @author   SakibWeb
 */
class PHLS {
    private static ?\PDO $pdo = null;
    private static string $file = '.env';
    private static array $stmt_cache = [];
    private static bool $shutdown_registered = false;

    /**
     * Establishes a connection to the SQLite database and ensures the schema is up to date.
     */
    private static function connect() {
        if (self::$pdo === null) {
            try {
                $options = [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC];
                self::$pdo = new \PDO('sqlite:' . self::$file, null, null, $options);
                self::$pdo->exec('PRAGMA journal_mode = WAL;');
                self::$pdo->exec('PRAGMA synchronous = NORMAL;');
                self::$pdo->exec("CREATE TABLE IF NOT EXISTS storage (key TEXT PRIMARY KEY, value TEXT NOT NULL, expiration INTEGER)");
                self::$pdo->exec("CREATE TABLE IF NOT EXISTS storage_tags (tag TEXT NOT NULL, key TEXT NOT NULL, PRIMARY KEY (tag, key))");

                if (rand(1, 100) <= 5) self::autoCleanup();

                if (!self::$shutdown_registered) {
                    register_shutdown_function([self::class, 'disconnect']);
                    self::$shutdown_registered = true;
                }
            } catch (\PDOException $e) {
                throw new \RuntimeException("PHLS SQLite connection failed: ". $e->getMessage());
            }
        }
    }

    /**
     * Closes the database connection. Intended for use with register_shutdown_function.
     */
    public static function disconnect() {
        self::$pdo = null;
        self::$stmt_cache = [];
    }

    /**
     * Sets the storage file path. Must be called before any other method.
     * @param string $path The file path.
     */
    public static function setFile(string $path) {
        if (self::$pdo !== null) {
            trigger_error("PHLS::setFile() must be called before any database connection is made.", E_USER_WARNING);
            return;
        }
        self::$file = $path;
    }
    
    /**
     * (Internal) Adds or updates a key without starting a new transaction.
     * @param string $key The key.
     * @param mixed $value The value.
     * @param int|null $expiration The expiration time in minutes.
     * @param array $tags The tags associated with the key.
     */
    private static function _add(string $key, $value, ?int $expiration = null, array $tags = []) {
        $stmt = self::getStatement('add', "INSERT OR REPLACE INTO storage (key, value, expiration) VALUES (?, ?, ?)");
        $exp_time = ($expiration !== null) ? time() + ($expiration * 60) : null;
        $stmt->execute([$key, json_encode($value), $exp_time]);

        $delete_tags_stmt = self::getStatement('delete_tags', "DELETE FROM storage_tags WHERE key = ?");
        $delete_tags_stmt->execute([$key]);

        if (!empty($tags)) {
            $insert_tag_stmt = self::getStatement('insert_tag', "INSERT INTO storage_tags (tag, key) VALUES (?, ?)");
            foreach ($tags as $tag) $insert_tag_stmt->execute([$tag, $key]);
        }
    }

    /**
     * Adds or updates a key-value pair, wrapping the operation in a transaction.
     * @param string $key The key.
     * @param mixed $value The value.
     * @param int|null $expiration The expiration time in minutes.
     * @param array $tags The tags associated with the key.
     */
    public static function add(string $key, $value, ?int $expiration = null, array $tags = []) {
        self::connect();
        self::$pdo->beginTransaction();
        try {
            if (strpos($key, '=>') !== false) {
                self::setNested($key, $value, $expiration, $tags);
            } else {
                self::_add($key, $value, $expiration, $tags);
            }
            self::$pdo->commit();
        } catch (\Exception $e) {
            if (self::$pdo->inTransaction()) self::$pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Alias for add(). Included for API completeness.
     * @deprecated Use add() instead.
     * @see add()
     */
    public static function update(string $key, $value, ?int $expiration = null, array $tags = []) {
        self::add($key, $value, $expiration, $tags);
    }

    /**
     * Removes a key-value pair. Handles nested keys automatically.
     * @param string $key The key to remove.
     */
    public static function remove(string $key) {
        self::connect();
        self::$pdo->beginTransaction();
        try {
            if (strpos($key, '=>') !== false) {
                self::removeNested($key);
            } else {
                $stmt = self::getStatement('remove', "DELETE FROM storage WHERE key = ?");
                $stmt->execute([$key]);
                $stmt_tags = self::getStatement('remove_tags', "DELETE FROM storage_tags WHERE key = ?");
                $stmt_tags->execute([$key]);
            }
            self::$pdo->commit();
        } catch (\Exception $e) {
            if (self::$pdo->inTransaction()) self::$pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Manually expires a specific key.
     * @param string $key The key to expire.
     */
    public static function expire(string $key) {
        self::connect();
        $stmt = self::$pdo->prepare("UPDATE storage SET expiration = ? WHERE key = ?");
        $stmt->execute([time() - 1, $key]);
    }

    /**
     * Manually expires all keys.
     */
    public static function expireAllExpired() {
        self::connect();
        self::autoCleanup();
    }

    /**
     * Checks if a key exists and has expired.
     * @param string $key The key to check.
     * @return bool True if the key exists and is expired, false otherwise.
     */
    public static function isExpired(string $key): bool {
        self::connect();
        $stmt = self::$pdo->prepare("SELECT expiration FROM storage WHERE key = ?");
        $stmt->execute([$key]);
        $expiration = $stmt->fetchColumn();
        if ($expiration === false) return false;
        return ($expiration !== null && $expiration <= time());
    }

    /**
     * Gets details (value and expiration) of all expired keys.
     * @return array An array of expired keys with their values and expiration times.
     */
    public static function getExpiredDetails(): array {
        self::connect();
        $stmt = self::$pdo->prepare("SELECT key, value, expiration FROM storage WHERE expiration IS NOT NULL AND expiration <= ?");
        $stmt->execute([time()]);
        return self::decodeValues($stmt->fetchAll());
    }

    /**
     * Gets details (value and expiration) of all active (non-expired) keys.
     * @return array An array of active keys with their values and expiration times.
     */
    public static function getActiveDetails(): array {
        self::connect();
        $stmt = self::$pdo->prepare("SELECT key, value, expiration FROM storage WHERE expiration IS NULL OR expiration > ?");
        $stmt->execute([time()]);
        return self::decodeValues($stmt->fetchAll());
    }

    /**
     * Adds a value to an array, keeping the array size at a specified limit.
     * Note: This method works best on root keys for performance.
     * @param string $key The key holding the array.
     * @param mixed $value The new value to prepend.
     * @param int $limit The maximum size of the array.
     * @param int|null $expiration Expiration time for the entire array in minutes.
     */
    public static function limitizer(string $key, $value, int $limit = 20, ?int $expiration = null) {
        self::connect();
        self::$pdo->beginTransaction();
        try {
            $current_list = self::get($key) ?? [];
            if (!is_array($current_list)) $current_list = [];
            
            array_unshift($current_list, $value);
            if (count($current_list) > $limit) $current_list = array_slice($current_list, 0, $limit);
            
            self::_add($key, $current_list, $expiration); 
            self::$pdo->commit();
        } catch (\Exception $e) {
            if (self::$pdo->inTransaction()) self::$pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Caches and retrieves a prepared statement for performance.
     * @param string $name The unique name for the statement.
     * @param string $sql The SQL query.
     */
    private static function getStatement(string $name, string $sql): \PDOStatement {
        self::connect();
        if (!isset(self::$stmt_cache[$name])) {
            self::$stmt_cache[$name] = self::$pdo->prepare($sql);
        }
        return self::$stmt_cache[$name];
    }

    /**
     * Gets a nested value from a root key's data.
     * @return mixed|null The nested value or null if not found.
     */
    private static function getNested(string $key) {
        $keys = explode('=>', $key);
        $root_key = array_shift($keys);

        $data = self::get($root_key);

        if (!is_array($data)) return null;

        foreach ($keys as $key_part) {
            if (is_array($data) && isset($data[$key_part])) {
                $data = $data[$key_part];
            } else {
                return null;
            }
        }
        return $data;
    }

    /**
     * Sets a nested value within a root key's data.
     * @param string $key The full nested key (e.g., "user=>settings=>theme").
     * @param mixed $value The value to set.
     * @param int|null $expiration The expiration time in minutes for the root key.
     * @param array $tags The tags associated with the root key.
     * @return void 
     */
    private static function setNested(string $key, $value, ?int $expiration = null, array $tags = []) {
        $keys = explode('=>', $key);
        $root_key = array_shift($keys);
        $current_data = self::get($root_key) ?? [];
        if (!is_array($current_data)) $current_data = [];
        $data_pointer = &$current_data;
        foreach ($keys as $key_part) {
            if (!isset($data_pointer[$key_part]) || !is_array($data_pointer[$key_part])) $data_pointer[$key_part] = [];
            $data_pointer = &$data_pointer[$key_part];
        }
        $data_pointer = $value;
        self::_add($root_key, $current_data, $expiration, $tags);
    }

    /**
     * Removes a nested key from a root key's data.
     * @param string $key The full nested key (e.g., "user=>settings=>theme").
     */
    private static function removeNested(string $key) {
        $keys = explode('=>', $key);
        $root_key = $keys[0];
        $current_data = self::get($root_key);
        if (!is_array($current_data)) return;
        $data_pointer = &$current_data;
        $last_key = array_pop($keys);
        array_shift($keys);
        foreach ($keys as $key_part) {
            if (isset($data_pointer[$key_part]) && is_array($data_pointer[$key_part])) {
                $data_pointer = &$data_pointer[$key_part];
            } else { return; }
        }
        unset($data_pointer[$last_key]);
        self::_add($root_key, $current_data);
    }

    /**
     * Retrieves a value by its key. Handles nested keys automatically.
     * @param string $key The key to retrieve.
     * @return mixed|null The value or null if not found or expired.
     */
    public static function get(string $key) {
        if (strpos($key, '=>') !== false) return self::getNested($key);
        $stmt = self::getStatement('get', "SELECT value FROM storage WHERE key = ? AND (expiration IS NULL OR expiration > ?)");
        $stmt->execute([$key, time()]);
        $result = $stmt->fetchColumn();
        return ($result !== false) ? json_decode($result, true) : null;
    }
    
    /**
     * Retrieves all active (non-expired) key-value pairs.
     * @return array An associative array of all active key-value pairs.
     */
    public static function getAll(): array {
        self::connect();
        $stmt = self::$pdo->prepare("SELECT key, value FROM storage WHERE expiration IS NULL OR expiration > ?");
        $stmt->execute([time()]);
        $results = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
        foreach ($results as &$value) {
            $value = json_decode($value, true);
        }
        return $results;
    }

    /**
     * *** NEW: "Cache & Fetch" atomic operation. ***
     * Retrieves an item from the cache. If it doesn't exist, executes the callback,
     * stores the result in the cache, and returns it.
     *
     * @param string $key The cache key.
     * @param int $expiration Expiration time in minutes.
     * @param callable $callback The function to execute to generate the value.
     * @param array $tags Optional tags.
     * @return mixed The cached or newly generated value.
     */
    public static function remember(string $key, int $expiration, callable $callback, array $tags = []) {
        $value = self::get($key);
        if ($value !== null) return $value;

        $value = $callback();
        self::add($key, $value, $expiration, $tags);
        return $value;
    }

    /**
     * Atomically increments a numeric value.
     * If the key does not exist, it will be created with the initial amount.
     * @param string $key The key.
     * @param int $amount The amount to increment by.
     * @return int The new value.
     */
    public static function increment(string $key, int $amount = 1, ?int $expiration = null): int {
        self::connect();
        self::$pdo->beginTransaction();
        try {
            $current_value = (int)self::get($key) ?: 0;
            $new_value = $current_value + $amount;
            self::_add($key, $new_value, $expiration);
            self::$pdo->commit();
            return $new_value;
        } catch (\Exception $e) {
            if (self::$pdo->inTransaction()) self::$pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Atomically decrements a numeric value.
     * If the key does not exist, it will be created with the negative initial amount.
     * @param string $key The key.
     * @param int $amount The amount to decrement by.
     * @return int The new value.
     */
    public static function decrement(string $key, int $amount = 1, ?int $expiration = null): int {
        return self::increment($key, -$amount, $expiration);
    }
    
    /**
     * Decodes JSON values in a result set.
     * @param array $results The result set.
     * @return array The result set with decoded values.
     */
    private static function decodeValues(array $results): array {
        foreach ($results as &$row) $row['value'] = json_decode($row['value'], true);
        return $results;
    }

    /**
     * Flushes (removes) all cache entries associated with a given tag.
     * @param string $tag The tag to flush.
     * @return void
     * @throws \Exception If a database error occurs.
     */
    public static function flushByTag(string $tag) {
        self::connect();
        self::$pdo->beginTransaction();
        try {
            $select_stmt = self::getStatement('get_keys_by_tag', "SELECT key FROM storage_tags WHERE tag = ?");
            $select_stmt->execute([$tag]);
            $keys_to_delete = $select_stmt->fetchAll(\PDO::FETCH_COLUMN);

            if (!empty($keys_to_delete)) {
                $placeholders = '?' . str_repeat(',?', count($keys_to_delete) - 1);
                $delete_storage_stmt = self::getStatement('delete_storage_keys', "DELETE FROM storage WHERE key IN ($placeholders)");
                $delete_storage_stmt->execute($keys_to_delete);
                
                $delete_tags_stmt = self::getStatement('delete_tags_by_tag', "DELETE FROM storage_tags WHERE tag = ?");
                $delete_tags_stmt->execute([$tag]);
            }
            self::$pdo->commit();
        } catch (\Exception $e) {
            if (self::$pdo->inTransaction()) self::$pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Removes all entries from the database. Use with caution!
     */
    public static function removeAll() {
        self::connect();
        self::$pdo->exec("DELETE FROM storage");
        self::$pdo->exec("DELETE FROM storage_tags");
    }

    /**
     * Deletes all expired entries from the database.
     */
    private static function autoCleanup() {
        if (self::$pdo === null) return;
        $stmt = self::$pdo->prepare("DELETE FROM storage WHERE expiration IS NOT NULL AND expiration <= ?");
        $stmt->execute([time()]);
    }
}

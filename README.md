# PHLS
# PHLS: PHP High-Performance Local Storage & Caching

**PHLS** is a zero-configuration, file-based, high-concurrency key-value storage and caching engine for PHP, powered by **SQLite**. It provides a simple, fluent API for managing data locally with robust features like optional expiration, data tagging, atomic operations, and nested data structures.

Its high-performance, concurrent-safe design makes it ideal for caching, rate limiting, session management, and general-purpose local data storage in high-traffic web applications.

## Features

*   **High-Performance:** Powered by SQLite with WAL-mode for incredible speed and high concurrency.
*   **Zero Configuration:** Works out-of-the-box. Just include the file and start using it.
*   **Fluent API:** Simple and expressive methods for all common operations.
*   **Expiration:** Set an optional expiration time (in minutes) for any key.
*   **Nested Data:** Store and retrieve deeply nested data using `=>` syntax (e.g., `user=>settings=>theme`).
*   **Tag-Based Caching:** Assign tags to your data and flush multiple cache entries at once.
*   **Atomic Operations:**
    *   **`remember`:** "Cache & Fetch" logic in a single, atomic operation.
    *   **`increment`/`decrement`:** Safely modify counters without race conditions.
    *   **`limitizer`:** A transaction-safe way to manage fixed-size lists (perfect for rate limiting).
*   **Automatic Cleanup:** Expired data is automatically cleaned up to keep the storage optimized.

## Installation & Setup

1.  Include the `PHLS.php` file in your project.
    ```php
    require_once 'PHLS.php';
    ```
2.  (Optional) Set a custom path for the storage file. This must be done before any other `PHLS` call.
    ```php
    PHLS::setFile(__DIR__ . '/cache/my_app_storage.sqlite');
    ```

## Usage

### 1. Basic Operations (Add, Get, Remove)

#### Adding & Updating a Value
The `add()` method inserts a new key-value pair or updates an existing one.

```php
// Add a simple key that never expires
PHLS::add('site_name', 'My Awesome App');

// Add a key that expires in 60 minutes
PHLS::add('session_token', 'xyz-123-abc', 60); 
```

The `update()` method is an alias for `add()`.
```php
PHLS::update('site_name', 'My New App Name');
```

#### Retrieving a Value
The `get()` method retrieves a value. It returns `null` if the key doesn't exist or has expired.

```php
$appName = PHLS::get('site_name'); // Returns: 'My New App Name'

$token = PHLS::get('session_token'); // Returns 'xyz-123-abc' if not expired
```

#### Removing a Value
The `remove()` method deletes a key.

```php
PHLS::remove('session_token');
```

### 2. Nested Data

Store and retrieve data in a hierarchical structure using the `=>` separator.

```php
// Add a nested key
PHLS::add('user=>settings=>theme', 'dark', 1440); // Expires in 1 day

// Retrieve a nested value
$theme = PHLS::get('user=>settings=>theme'); // Returns: 'dark'

// Remove a nested key
PHLS::remove('user=>settings=>theme');
```

### 3. Advanced Caching Patterns

#### "Cache & Fetch" with `remember()`
This is the most powerful caching method. It retrieves an item, but if it's missing, it executes a callback, caches the result, and then returns it.

```php
$user_id = 123;

// Try to get user data from cache. If not found, run the function to get it from the database.
$user = PHLS::remember('user:' . $user_id, 60, function() use ($user_id) {
    // This code only runs if 'user:123' is not in the cache.
    echo "Fetching user from database...";
    return find_user_in_db($user_id); 
});

// The second time this code runs (within 60 mins), it will be instant, and 
// "Fetching user from database..." will not be printed.
```

#### Tag-Based Caching
Assign tags to your data to invalidate multiple related cache entries at once.

```php
// Store user data with tags
PHLS::add('user:123', $user_data, 60, ['users', 'user:123']);
PHLS::add('user:123:posts', $user_posts, 60, ['users', 'user:123', 'posts']);
PHLS::add('all_users_list', $all_users, 60, ['users', 'list']);

// Now, if user 123 updates their profile, you can flush all related caches
// without knowing the exact keys.
PHLS::flushByTag('user:123'); // Removes 'user:123' and 'user:123:posts'

// Or flush all user-related data
PHLS::flushByTag('users'); // Removes all three entries
```

### 4. Atomic Counters (`increment` & `decrement`)

Safely increase or decrease numeric values, perfect for counters.

```php
// Increment a page view counter
$new_views = PHLS::increment('page_views:/about-us');
echo "This page has been viewed {$new_views} times.";

// Increment by a specific amount
PHLS::increment('user:123:points', 10);

// Decrement a value
PHLS::decrement('inventory:product-a', 1);
```

### 5. Managing Lists with `limitizer()`

Add new items to the beginning of a list while keeping its size fixed. Perfect for "latest activity" logs or rate limiting.

```php
// Store the last 5 search queries
PHLS::limitizer('recent_searches', 'new search term 1', 5);
PHLS::limitizer('recent_searches', 'new search term 2', 5);

$searches = PHLS::get('recent_searches'); // Returns: ['new search term 2', 'new search term 1']
```

### 6. Expiration Management

#### Check if a Key has Expired
```php
if (PHLS::isExpired('old_session')) {
    echo "Session has expired.";
}
```

#### Manually Expire Keys
```php
// Expire a single key immediately
PHLS::expire('user:123:posts');

// Expire all keys
PHLS::expireAll();
```

#### Clean Up Expired Keys
While cleanup happens automatically, you can trigger it manually.
```php
PHLS::expireAllExpired();
```

### 7. Introspection (Viewing Stored Data)

#### Get All Active Keys and Values
```php
$all_data = PHLS::getAll();
print_r($all_data);
```

#### Get Details of Active/Expired Keys
These methods return the full stored object, including the `value` and `expiration` timestamp.
```php
$active_items = PHLS::getActiveDetails();
$expired_items = PHLS::getExpiredDetails();
```

### 8. Full Cleanup

To completely wipe the storage:
```php
PHLS::removeAll();
```

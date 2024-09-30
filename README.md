# PHLS
### PHLS is PHP Local Storage Library
PHLS is a PHP library for managing key-value data storage with optional expiration times. It provides a variety of methods to add, update, retrieve, and remove values, along with checking for expiration status. This document explains the available functions and provides example usage for each.

# Features
* Add: Insert a new key-value pair with an optional expiration time.
* Update: Modify the value of an existing key.
* Remove: Delete a specific key from the storage.
* Get: Retrieve the value associated with a given key.
* Limitizer: Add a new value under a key while limiting the maximum number of values.
* Expire: Manually expire a stored value.
* ExpireAll: Expire all stored values.
* RemoveAll: Remove all stored values.
* GetAll: Retrieve all stored values.
* GetExpiredDetails: Get details of all expired stored values.
* GetActiveDetails: Get details of all active stored values.
* ExpireAllExpired: Automatically remove all expired keys.
* IsExpired: Check if a specific value is expired.

# Usage
### Adding a Value
To add a new value with an optional expiration time:
```
PHLS::add('username', 'john_doe', 60); // Adds 'john_doe' with a key 'username' and an expiration of 60 minutes.
```
### Adding Nested Keys
To add a nested key-value pair:
```
PHLS::add('user=>settings=>theme', 'dark', 30); // Adds a nested key 'theme' under 'user=>settings' with a value of 'dark' and expires in 30 minutes.
```
### Limitizing a Value Array
To add a value to a key while maintaining a maximum number of stored values:
```
PHLS::limitizer('recent_searches', 'new_search_item', 5); // Adds 'new_search_item' to 'recent_searches' and limits the array size to 5.
```
### Updating a Value
To update the value of an existing value:
```
PHLS::update('username', 'jane_doe'); // Updates the value of 'username' to 'jane_doe'.
```
### Removing a Value
To remove a value:
```
PHLS::remove('username'); // Removes the 'username' key and its associated value.
```
### Retrieving a Value
To retrieve a stored value:
```
$value = PHLS::get('username'); // Returns the value of 'username' if it exists and has not expired.
```
### Retrieving All Stored Values
To get all stored key-value pairs:
```
$allValues = PHLS::getAll(); // Returns the entire storage data.
```
### Retrieving a Nested Value
To retrieve a nested value:
```
$theme = PHLS::get('user=>settings=>theme'); // Returns 'dark' if the nested key exists.
```
### Checking if a Value is Active
To check if a stored value exists and is active:
```
$is_active = PHLS::active('username');
```

### Checking if a Value has Expired
To check if a stored value has expired:
```
$is_expired = PHLS::isExpired(['value' => 'john_doe', 'expiration' => time() - 3600]); // Returns true because the expiration is in the past.
```
### Expiring a Specific Value
To mark a value as expired:
```
PHLS::expire('username'); // Manually expires the 'username' key.
```
### Expiring a Value
To expire a stored value:
```
PHLS::makeExpired('username');
```
### Expire All Values
To expire all stored values:
```
PHLS::expireAll(); // All values will be marked as expired.
```
Retrieving Expired Values
To get details of all expired keys:
```
$expiredDetails = PHLS::getExpiredDetails(); // Returns an array of expired key-value pairs.
```
### Removing All Values
To remove all stored values:
```
PHLS::removeAll(); // Clears all key-value pairs from the storage.
```
### Removing Nested Values
Removes the nested key:
```
PHLS::remove('user=>settings=>language'); // Removes the nested key for language.
```
### Retrieving All Values
To retrieve all stored values:
```
$values = PHLS::getAll();
```
### Getting Details of Expired Values
To get details of expired stored values:
```
$expired_details = PHLS::getExpiredDetails();
```
### Retrieving Active Values
To get details of all active keys:
```
$activeDetails = PHLS::getActiveDetails(); // Returns an array of active key-value pairs.
```

# Key Concepts
### Expiration Handling
- Expiration Time: All stored values can have an optional expiration time defined in minutes. When retrieving values, the expiration is checked to ensure the data is still valid.
- Manual Expiration: You can manually expire or remove values using the provided methods.
- Automatic Cleanup: The library automatically removes expired values when data is accessed or saved.
- Nested Keys: PHLS supports storing and accessing nested keys using the => separator. This allows you to organize data hierarchically.

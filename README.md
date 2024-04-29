# PHLS
### PHLS is PHP Local Storage Library
PHLS is a utility class for managing stored values with expiration times in PHP applications. It provides methods for adding, updating, removing, and accessing stored values, as well as checking their expiration status.

# Features
* Add: Add a new value with a specified key, value, and optional expiration time.
* Update: Update the value of an existing value.
* Remove: Remove a value with the specified key.
* Get: Retrieve the value of a stored value.
* IsActive: Check if a stored value exists and is active (not expired).
* Expire: Expire a stored value.
* ExpireAll: Expire all stored values.
* RemoveAll: Remove all stored values.
* GetAll: Retrieve all stored values.
* GetExpiredDetails: Get details of expired stored values.

# Usage
### Adding a Value
To add a new value:
```
PHLS::add('username', 'john_doe', 60); // Expires in 60 minutes
```
### Updating a Value
To update the value of an existing value:
```
PHLS::update('username', 'jane_doe');
```
### Removing a Value
To remove a value:
```
PHLS::remove('username');
```
### Retrieving a Value
To retrieve the value of a stored value:
```
$value = PHLS::get('username');
```
### Checking if a Value is Active
To check if a stored value exists and is active:
```
$is_active = PHLS::active('username');
```
### Checking if a Value has Expired
To check if a stored value has expired:
```
$is_expired = PHLS::expired('username');
```
### Expiring a Value
To expire a stored value:
```
PHLS::makeExpired('username');
```
### Expire All Values
To expire all stored values:
```
PHLS::expireAll();
```
### Removing All Values
To remove all stored values:
```
PHLS::removeAll();
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

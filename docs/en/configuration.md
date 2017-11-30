# Developer documentation
## Overview
Provides a basic mechanism by which user interactions can be logged and limited if desired. This module works on the idea of a named **event** that is logged and an optional set of **rules** restricting the number of events within a time period.
Events and rules are not automatically checked or limited, instead the developer is expected to implement this behaviour using the functionality provided.

## Basic usage
`IPLoggerService` should first be exposed to the piece of code in which you wish to log or check events e.g.
    
```php
<?php
class FooObject extends DataObject
{
...
    private static $dependencies = array(
		'loggerService' => '%$IPLoggerService'
    );

    public $loggerService;
...
}
```
    
You may then log an event `IPLoggerService->log()`
    
```php
$this->loggerService->log('test_event');
```
    
`IPLoggerService->checkAllowed()` is user to check if an event is allowed

    ```php
    $allowed = $this->loggerService->checkAllowed('test_event');
    if($allowed) {
        $this->loggerService->log('test_event');
        // Proceed as normal
        ...
    } else {
        // Take appropriate action
        ...
    }
	```

## Configuration

### Adding rules
In order to check if an event is allowed to happen a rule must first be defined. Rules are defined in yaml files under the _config directory e.g. `mysite/_config/rules.yml`.

Rules must have the following three values
 * **findtime** - The amount of time over which **hits** is calculated, must be in seconds.
 * **hits** - The number of times an event can happen within **findtime** seconds.
 * **bantime** - The number of seconds a user is banned for after exceeding **hits** number of events within **findtime** seconds. If this value is set to 0 the ban will be permanent.

Here is an example of a rule that allows an event to be carried out 3 times in sixty seconds, if the user exceeds this limit they will be banned for 5 minutes.

	```yml
    IPLoggerService:
     rules:
      foobar:
        findtime: 60
        hits: 3
        bantime: 300


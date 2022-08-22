# laravel-debugrequest

During development of Laravel projects, it is often required to use the log to debug aspects of the project. The log
easily becomes large and finding the right entries may become cumbersome. This package makes sure that every request
(both web requests and api requests) will have a log entry. This entry notes some request and session details, after
which the application logic may log its own messages. Finally, this package logs some more information.

To be very easily recognized, the first log entry is a collection of information, written in a "box", using regular
box drawing characters. The general layout is as follows:

```
╔═╡GET /╞══════════════════════════════════════╗
║ route: (anonymous)                                  ║
║ mw: web                                             ║
║ sid:1ykjpbMLmEeCHY5Uc7IiTpg7KKVzmOTBslki5VEi u:none ║
╚═════════════════════════════════════════════╝
```

This can be completed by session and request information.

If the application assigns some (temporary) values to the session, this information is displayed in a separate box at
the end of the request. And finally, the amount of time passed in milliseconds is displayed.

## Installation

```
composer require martenkoetsier/laravel-routelist
```

The automatic package discovery will add the service provider to your project and assign the middleware to the `web` and
`api` middleware groups.

Configuration can be changed:

```
php artisan vendor:publish --provider="Martenkoetsier\LaravelDebugrequest\Providers\LaravelDebugrequestProvider"
```

In this file, all configuration is setup with the default values.

## Implementation

This package is implemented as a middleware, which can be assigned to any route. By default, it is added to the `web`
and `api` middleware groups.

Since this is a middleware, part of the application call is processed before the log is generated. The developer may
change where this middleware is called in the `\App\Http\Kernel` class, but keep in mind that at least the session
should have been started and authorization should be ready.

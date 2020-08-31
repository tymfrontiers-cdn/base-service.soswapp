# base-service.soswapp

Basic web service for 7 OS Project.

## Installation & usage

`composer require tymfrontiers-cdn/base-service.soswapp`

Command path: `/app/tymfrontiers-cdn/base-service.soswapp`

### Setting up custom command path

Add code below between `##-APP-SHORTCUT-START` and `##-APP-SHORTCUT-END` in `.htaccess` file on your web project domain root.

```
##-tymfrontiers-cdn/base-service.soswapp-START
RewriteRule ^service/?$ /app/tymfrontiers-cdn/base-service.soswapp/ [QSA,NC,L]
RewriteRule ^service/([0-9a-zA-Z\-\.]{3,})/?$ /app/tymfrontiers-cdn/base-service.soswapp/service/$1.php [QSA,NC,L]
RewriteRule ^service/([0-9a-zA-Z\-\.]{3,})/([0-9a-zA-Z\-\.]{3,})/?$ /app/tymfrontiers-cdn/base-service.soswapp/service/$1-$2.php [QSA,NC,L]
RewriteRule ^service/(.*)$ /app/tymfrontiers-cdn/base-service.soswapp/$1 [QSA,NC,L]
##-tymfrontiers-cdn/base-service.soswapp-END
```

You can now access this app from `/app/service` or `/service`

### Services

#### Send mail

Sends email queued with [tymfrontiers-cdn/php-sos-emailer](https://github.com/tymfrontiers-cdn/php-sos-emailer)

`/run/sendmail` | `/service/run-sendmail.php`

**Parameters**

- `[limit]` _integer_ | Number of emails to send per request

**Authentication:** [tymfrontiers-cdn/php-api-authentication](https://github.com/tymfrontiers-cdn/php-api-authentication)

### Requires

- [tymfrontiers-cdn/php-api-authentication](https://github.com/tymfrontiers-cdn/php-api-authentication)
- [tymfrontiers-cdn/php-http-header](https://github.com/tymfrontiers-cdn/php-http-header)
- [tymfrontiers-cdn/php-http-client](https://github.com/tymfrontiers-cdn/php-http-client)
- [tymfrontiers-cdn/php-sos-emailer](https://github.com/tymfrontiers-cdn/php-sos-emailer)
- [tymfrontiers-cdn/php-generic](https://github.com/tymfrontiers-cdn/php-generic)
- [tymfrontiers-cdn/php-multiform](https://github.com/tymfrontiers-cdn/php-multiform)

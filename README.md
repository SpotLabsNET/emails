openclerk/emails
================

A library for sending emails and email management.

## Installing

Include `openclerk/emails` as a requirement in your project `composer.json`,
and run `composer update` to install it into your project. You'll also
have to include `pelago/emogrifier` [as an explicit dependency](https://github.com/jjriv/emogrifier/issues/113):

```json
{
  "require": {
    "openclerk/emails": "dev-master",
    "pelago/emogrifier": "dev-master"
  },
  "repositories": [{
    "type": "vcs",
    "url": "https://github.com/openclerk/emails"
  }, {
    "type": "vcs",
    "url": "https://github.com/jjriv/emogrifier"
  }]
}
```

Make sure that you run all of the migrations that can be discovered
through [component-discovery](https://github.com/soundasleep/component-discovery);
see the documentation on [openclerk/db](https://github.com/openclerk/db) for more information.

```php
$migrations = new AllMigrations(db());
if ($migrations->hasPending(db())) {
  $migrations->install(db(), $logger);
}
```

## Features

1. Send either text or HTML emails
1. Generate text multipart automatically with [html2text](https://github.com/soundasleep/html2text)
1. Automatically inline CSS styles with [emogrifier](https://github.com/jjriv/emogrifier)
1. Tracks e-mails sent using `emails` database table
1. Send emails to raw addresses or to User objects that return `getEmail()`

## Using

This project uses [openclerk/db](https://github.com/openclerk/db) for database
management and [openclerk/config](https://github.com/openclerk/config) for config management.

First configure the component with site-specific values:

```php
Openclerk\Config::merge(array(
  "phpmailer_host" => "mail.example.com",
  "phpmailer_username" => "mailer",
  "phpmailer_password" => "password",
  "phpmailer_from" => "mailer@example.com",
  "phpmailer_from_name" => "Example Mailer",
  "phpmailer_reply_to" => "mailer@example.com",
  "phpmailer_bcc" => "copy@example.com",   // if set, send a copy of all emails to this address
));
```

Now define templates in `emails/<id>.html`:

```html
<title>Test email sent {$now}</title>
<h1>Hi {$email},</h1>

<p>This is a test email sent {$now}.</p>
```

You can optionally specify a wrapping layout HTML file in `emails/layout.html`, and CSS
styles in `emails/layout.css`:

```html
<link href="layout.css" media="all" rel="stylesheet" type="text/css" />
<div class="content">
  {$content}
</div>
```

```css
html, body {
  background: #eee;
  font-family: 'Arial', sans-serif;
  margin: 0;
  padding: 0;
}
.body {
  background: #eee;
  padding: 15px;
}
.content {
  padding: 15px;
  background: white;
  border: 1px solid #ccc;
  color: #111;
  line-height: 130%;
}
```

Now you can send e-mails immediately:

```php
$user = Users\User::findUser(db(), 1);
if (!$user) {
  $user = "test@example.com";
}

$result = send_email($user, "<id>", array(
  "now" => date('r'),
));
```

## TODO

1. Queueing up/batch emails
1. Properly escape templates
1. Mock mailing
1. i18n
1. Tests
1. Publish on Packagist

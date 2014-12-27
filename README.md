openclerk/emails
================

A library for sending emails and email management.

## Installing

Include `openclerk/emails` as a requirement in your project `composer.json`,
and run `composer update` to install it into your project.

```json
{
  "require": {
    "openclerk/emails": "dev-master"
  },
  "repositories": [{
    "type": "vcs",
    "url": "https://github.com/openclerk/emails"
  }]
}
```

## Features

1. Send either text or HTML emails
1. Generate text multipart automatically with [html2text](https://github.com/soundasleep/html2text)
1. Automatically inline CSS styles with [emogrifier](https://github.com/jjriv/emogrifier) for [clients like Gmail](https://litmus.com/blog/understanding-gmail-and-css-part-1)
1. Track e-mails sent with the `email_sent` [event](https://github.com/openclerk/events)
1. Send emails to raw addresses or to User objects that return `getEmail()`

## Using

This project uses [openclerk/config](https://github.com/openclerk/config) for config management.

First configure the component with site-specific values (assumes SMTP):

```php
Openclerk\Config::merge(array(
  "phpmailer_host" => "mail.example.com",
  "phpmailer_username" => "mailer",
  "phpmailer_password" => "password",
  "phpmailer_from" => "mailer@example.com",
  "phpmailer_from_name" => "Example Mailer",
  "phpmailer_reply_to" => "mailer@example.com",
  "phpmailer_bcc" => "copy@example.com",   // if set, send a copy of all emails to this address

  // optional values
  // "emails_templates" => __DIR__ . "/../emails",
  // "emails_additional_css" => __DIR__ . "/../config/custom.css",
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

$result = Emails\Email::send($user, "<id>", array(
  "now" => date('r'),
));
```

## Tracking emails sent

The `email_sent` [event](https://github.com/openclerk/events) can be used to track
emails that have been sent, for example by inserting them into an `emails`
[database table](https://github.com/openclerk/db):

```php
Openclerk\Events::on('email_sent', function($email) {
  // insert in database keys
  $q = db()->prepare("INSERT INTO emails SET
    user_id=:user_id,
    to_name=:to_name,
    to_email=:to_email,
    subject=:subject,
    template_id=:template_id,
    arguments=:arguments");
  $q->execute(array(
    "user_id" => $email['user_id'],
    "to_name" => $email['to_name'],
    "to_email" => $email['to_email'],
    "subject" => $email['subject'],
    "template_id" => $email['template_id'],
    "arguments" => serialize($email['arguments']),
  ));
});
```

## TODO

1. Queueing up/batch emails
1. Properly escape templates
1. Mock mailing
1. i18n

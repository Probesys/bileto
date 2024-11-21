# Sending emails

Emails must send in a MessageHandler.
The handlers must match the format `Send*EmailHandler`.

Emails must be translated and sent in HTML and TXT.

```php
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

$email = new TemplatedEmail();
$email->subject($subject);
$email->locale($locale);
$email->context([
    'subject' => $subject,
]);
$email->htmlTemplate('emails/message.html.twig');
$email->textTemplate('emails/message.txt.twig');
```

Templates should extend the transactional templates:

```twig
{% extends 'emails/transactional.html.twig' %}

{% block title %}{{ subject }}{% endblock %}

{% block body %}
    <p>
        Some text
    </p>
{% endblock %}
```

Or for text emails:

```twig
{% extends 'emails/transactional.txt.twig' %}

{% block body %}
Some text
{% endblock %}
```

A bit of CSS is applied and can be found in the file [`assets/stylesheets/email.css`](/assets/stylesheets/email.css).

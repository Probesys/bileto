# Understanding GreenMail

To facilitate the configuration of Bileto in development, we use [GreenMail](https://greenmail-mail-test.github.io/greenmail/).

We use GreenMail as a “catch-all mails” server.
It means that any mail sent via this server will end-up in a GreenMail mailbox.
This avoids to send emails to a real address during development.

Later, the GreenMail server will also be used to read mails from an inbox with IMAP in order to create tickets.

## Setup

GreenMail is an application that run in a Docker container.
It is setup in [the `docker-compose.yml` file](/docker/docker-compose.yml) (see the `mailserver` service).

Only SMTP and IMAP protocols are served as we only need these at the moment.

## How to use

### In Bileto

_Bileto is not configured yet to send or read emails._

### With Thunderbird (or any external mail client)

It may be useful to read emails sent to an inbox managed by GreenMail.
For that, you can configure a new email account in Thunderbird for instance.

Once you’ve started GreenMail with Docker, configure a new “existing mail account” in Thunderbird.
Set the email address to the one you want (it can litteraly be any email address).
Click on “configure manually” and set the following parameters:

Incoming server:

- protocol: IMAP
- hostname: localhost
- port: 3143
- username: the email address you’re configuring

Outgoing server:

- hostname: localhost
- port: 3025
- username: the email address you’re configuring

You may have to confirm that you understand the risk when Thunderbird warns you about localhost not using encryption.

Now, if you send emails with this account, GreenMail will catch it and put it in a new mailbox created with login and password being the same as the “to” address.
To send an email to this account, you can setup another account and send an email to the first one.

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

SMTP is already configured in Bileto to use the GreenMail server.
You can find the configuration in the file [`.env`](/.env).
It uses the address `support@example.com` to send emails.

When you setup the database, it also configures an IMAP Mailbox with the same address.
If it’s not, run:

```console
$ ./docker/bin/console db:seeds:load
```

You can configure more mailboxes with IMAP in the interface.
As an admin, go to the “Settings > Mailboxes” and create a new mailbox.
Configure it with the following information:

- name: whatever name you want
- hostname: `mailserver`
- port: `3143`
- encryption method: `None`
- username: `alix@example.com` (for instance)
- password: `secret` (or whatever)
- folder: `INBOX`

Once created, you can test the connection to verify that it works.

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

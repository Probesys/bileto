# The email collector

Bileto allows you to configure mailboxes to receive emails.
These emails are retrieved with <abbr>IMAP</abbr> and can be transformed in tickets.

## The Mailbox entity

The [`Mailbox`](/src/Entity/Mailbox.php) entity stores the information about the mailboxes.
Mailboxes are managed in the [`MailboxesController`](/src/Controller/MailboxesController.php).
This controller allows you, among the rest, to collect the emails from the mailboxes.

## The FetchMailboxes handler

When emails are collected, the first thing that Bileto does is to fetch the “Unseen” emails from the mailboxes.
It is done in the [`FetchMailboxesHandler`](/src/MessageHandler/FetchMailboxesHandler.php).

Its job is to fetch the emails, save them as [`MailboxEmail`](/src/Entity/MailboxEmail.php) entities, and delete them.
If deletion fails, the emails are marked as “Seen”.

## The CreateTicketsFromMailboxEmails handler

Once the emails are fetched, it's time to import tickets from the `MailboxEmail`s.
This is the job of the [`CreateTicketsFromMailboxEmailsHandler`](/src/MessageHandler/CreateTicketsFromMailboxEmailsHandler.php).

For each `MailboxEmail`:

1. it gets the requester (i.e. the `Reply-To` or the `From` headers);
2. it gets the default organization of the requester;
3. it detects a potential ticket to which the email might reply;
4. if it detects a ticket, it checks that the requester can answer to it and that it is not closed;
5. otherwise it checks the requester has the permission to create tickets in the organization and it creates one based on the `Subject` and the `Body` of the email;
6. finally, it deletes the `MailboxEmail` from the database.

If anything goes wrong during this process, the error is logged in the relevant `MailboxEmail` `lastError` field.

To detect the ticket to which the email reply, we use two techniques:

- we track the `Message-ID` header from the sent notifications (see [`SendMessageEmailHandler`](/src/MessageHandler/SendMessageEmailHandler.php)).
  If the email answers to an ID that we track, we load the corresponding ticket.
- otherwise, we search for a `[#ID]` substring in the subject of the email.

## Scheduling

You don’t have to fetch the emails manually each time you receive an email to the support.
Indeed, the previous jobs are scheduled to be executed every minute.

It’s declared in the [`DefaultScheduleProvider`](/src/Scheduler/DefaultScheduleProvider.php).

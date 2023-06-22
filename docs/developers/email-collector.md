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

1. it gets the requester (i.e. the `From` header);
2. it gets the default organization of the requester;
3. it extracts a potential ticket ID from the email;
4. if there is an ID, it checks that the requester can answer to the ticket and that the ticket is not closed;
5. otherwise it checks the requester has the permission to create tickets in the organization and it creates one based on the `Subject`, the `Date` and the `Body` of the email;
6. finally, it deletes the `MailboxEmail` from the database.

If anything goes wrong during this process, the error is logged in the relevant `MailboxEmail` `lastError` field.

To extract the ticket ID from the email, at the moment we only search for a `[#ID]` substring in the subject of the email.
This technique is not very robust as it depends on the person to not rewrite the subject.
Later, we’ll also include the ticket ID in the `Message-ID` header as well as in the email body.

## Scheduling

You don’t have to fetch the emails manually each time you receive an email to the support.
Indeed, the previous jobs are scheduled to be executed every minute.

It’s declared in the [`DefaultScheduleProvider`](/src/Scheduler/DefaultScheduleProvider.php).

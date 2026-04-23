# Cleaning data

Some data may expire and need to be cleaned periodically.
This is the purpose of [the `CleanDataHandler`](/src/MessageHandler/CleanDataHandler.php).

It performs the following operations:

- Delete expired tokens.
- Delete session logs older than 6 months.
- Delete entity events related to deleted entities, older than 1 week.
- Anonymize or delete inactive users.

## Inactive users

A user is considered inactive when they only have authorizations of type
`user` (or none at all), and their last activity (or creation date if they
never had any) is older than `APP_USERS_INACTIVITY_TIME` months.

Two environment variables control this step, in order to help complying
with the GDPR:

- `APP_USERS_INACTIVITY_TIME` (default `12`): the number of months after
  which a user is considered inactive. Set to `0` or a negative value to
  disable the detection entirely (this also disables the automatic cleanup
  below).
- `APP_USERS_INACTIVITY_AUTO` (default `none`): the action to apply
  automatically. Can be `none` (do nothing, the admin must act manually),
  `anonymize` (irreversible) or `delete` (irreversible). An unknown value
  is logged as a warning and the cleanup is skipped.

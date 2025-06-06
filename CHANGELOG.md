# Changelog of Bileto

## unreleased

### Migration notes

It is now possible to declare trusted proxies to get the real client IP by
setting the `TRUSTED_PROXIES` environment variable.

## 2025-01-16 - 0.14.0

### Breaking changes

PostgreSQL 13+ is now required as [PostgreSQL 12 is no longer supported.](https://www.postgresql.org/support/versioning/)
MariaDB 10.5+ is now required as [MariaDB 10.4 is no longer supported.](https://mariadb.org/about/#maintenance-policy)

### Improvements

- Allow to select the organization when opening a ticket from the global list ([8c81af0](https://github.com/Probesys/bileto/commit/8c81af0))
- Group the actors by their role type in the "select" lists ([ed366c0](https://github.com/Probesys/bileto/commit/ed366c0))
- Allow to search tickets by team ([83f0008](https://github.com/Probesys/bileto/commit/83f0008), [44e2ddc](https://github.com/Probesys/bileto/commit/44e2ddc))
- Reopen resolved ticket if answerer is not an agent ([fd8152e](https://github.com/Probesys/bileto/commit/fd8152e))
- Display the date next to the time in the ticket timeline ([9b59a87](https://github.com/Probesys/bileto/commit/9b59a87))
- Add id/anchors on the timelines' items ([cd0d53e](https://github.com/Probesys/bileto/commit/cd0d53e))
- Allow to upload more different files ([03f07bf](https://github.com/Probesys/bileto/commit/03f07bf), [9b7d8b3](https://github.com/Probesys/bileto/commit/9b7d8b3))
- Send the email receipt only if the requester created the ticket ([99bc3f6](https://github.com/Probesys/bileto/commit/99bc3f6))
- Display the custom logo on the login page ([4ce31d3](https://github.com/Probesys/bileto/commit/4ce31d3))
- Remove the deprecated password help from the new user form ([fcd5de6](https://github.com/Probesys/bileto/commit/fcd5de6))
- Add more context to the MailboxEmail errors ([3d65c4c](https://github.com/Probesys/bileto/commit/3d65c4c))

### Bug fixes

- Fix the alignment of ticket's id and title ([c04ae99](https://github.com/Probesys/bileto/commit/c04ae99))
- Make sure to keep current actors in the ticket even if they lose their access ([3a0f138](https://github.com/Probesys/bileto/commit/3a0f138))
- Redirect to organization's contracts when changing contracts order ([9d80942](https://github.com/Probesys/bileto/commit/9d80942))
- Catch errors if the message notification reference cannot be saved ([8302cdb](https://github.com/Probesys/bileto/commit/8302cdb))
- Make sure to return a valid UTF8 string in DomHelper so that the message doesn't go empty ([92eb56e](https://github.com/Probesys/bileto/commit/92eb56e))
- Handle the answers to tickets containing messages with the same references ([025d484](https://github.com/Probesys/bileto/commit/025d484))
- Expire cache of authorizations after 10s so the worker considers authorization changes ([c0fec9a](https://github.com/Probesys/bileto/commit/c0fec9a))

### Technical

- Allow administrators to send personally identifiable information (PII) to Sentry ([5141ff7](https://github.com/Probesys/bileto/commit/5141ff7))
- Warn about datetimes' offsets in the documentation of data importation ([ae06de9](https://github.com/Probesys/bileto/commit/ae06de9))
- Allow to reimport message documents if the first import failed ([d32e7db](https://github.com/Probesys/bileto/commit/d32e7db))
- Require MariaDB >= 10.5 ([2ad0844](https://github.com/Probesys/bileto/commit/2ad0844))
- Require PostgreSQL >= 13 ([8eca54e](https://github.com/Probesys/bileto/commit/8eca54e))
- Update the dependencies ([ed0805c](https://github.com/Probesys/bileto/commit/ed0805c), [6cc7329](https://github.com/Probesys/bileto/commit/6cc7329), [a5a053a](https://github.com/Probesys/bileto/commit/a5a053a))
- Allow to delete messages with documents in database ([658c21f](https://github.com/Probesys/bileto/commit/658c21f))
- Allow to delete tickets with solution in database ([53f0061](https://github.com/Probesys/bileto/commit/53f0061))

### Developers

- Make the multiselect widget generic ([411185e](https://github.com/Probesys/bileto/commit/411185e))
- Add support for optgroup to the multiselect component ([c3af805](https://github.com/Probesys/bileto/commit/c3af805))
- Pull pgsql and mariadb images with the docker-pull command ([f91c9ef](https://github.com/Probesys/bileto/commit/f91c9ef))
- Update the copyright year ([c63525a](https://github.com/Probesys/bileto/commit/c63525a))

## 2024-12-12 - 0.13.0

### Breaking change

The `uploads/` directory is now placed under `var/` by default.
If you uploaded files on your instance of Bileto, you must either set the `APP_UPLOADS_DIRECTORY` environment variable to the previous `uploads/` directory, or move the directory under `var/`.
If you move it under `var/`, you no longer need to mount a dedicated Docker volume on `uploads/` as you should already have one mounter on `var/`.

### New

- Allow to search contracts ([2c83b91](https://github.com/Probesys/bileto/commit/2c83b91), [d146c59](https://github.com/Probesys/bileto/commit/d146c59))

### Improvements

- Display both owned and assigned tickets to agents on home page ([bf1b2c5](https://github.com/Probesys/bileto/commit/bf1b2c5), [7509502](https://github.com/Probesys/bileto/commit/7509502))
- Add a "Your assigned tickets" view ([818a0e9](https://github.com/Probesys/bileto/commit/818a0e9))
- Allow to select observers during ticket creation ([4562501](https://github.com/Probesys/bileto/commit/4562501))
- Rework solution approvement to allow agents to approve or refuse a solution ([ac25249](https://github.com/Probesys/bileto/commit/ac25249))
- Allow to update spent times ([74e322b](https://github.com/Probesys/bileto/commit/74e322b))
- Allow to create a contract from the global list of contracts ([1e2dc34](https://github.com/Probesys/bileto/commit/1e2dc34))
- Don't consider consumed contracts as finished ([eb7f77c](https://github.com/Probesys/bileto/commit/eb7f77c))
- Add information about the renewed contracts ([d310130](https://github.com/Probesys/bileto/commit/d310130))
- Set the default value of Contract time accounting unit to 30 ([53be1f1](https://github.com/Probesys/bileto/commit/53be1f1))
- Display the total time spent in the tickets ([12fd603](https://github.com/Probesys/bileto/commit/12fd603))
- Display total spent time in tickets lists ([97189b5](https://github.com/Probesys/bileto/commit/97189b5))
- Improve the UX of the "time spent" input ([6109e56](https://github.com/Probesys/bileto/commit/6109e56))
- Hide the organization submenu if user can see tickets only ([7699a93](https://github.com/Probesys/bileto/commit/7699a93))
- Add a link to the ticket in the receipt email ([6a8d13e](https://github.com/Probesys/bileto/commit/6a8d13e))
- Add the status to the message emails subject if finished ([9464577](https://github.com/Probesys/bileto/commit/9464577))
- Accept .eml files as attachments ([0ebb324](https://github.com/Probesys/bileto/commit/0ebb324))
- Remove the "Ticket already solved" checkbox ([d1c4cf5](https://github.com/Probesys/bileto/commit/d1c4cf5))
- Allow to customize the logo in the emails ([52e39f5](https://github.com/Probesys/bileto/commit/52e39f5))

### Bug fixes

- Display the images in notification emails correctly ([1bcf9c9](https://github.com/Probesys/bileto/commit/1bcf9c9))
- Find LDAP user by email if identifier is not set yet ([5f4f4ed](https://github.com/Probesys/bileto/commit/5f4f4ed))
- Change "assigné" to "attribué" in the French translation ([1ed9ade](https://github.com/Probesys/bileto/commit/1ed9ade))
- Fix the checkbox and radio buttons glitch aspect ([8460602](https://github.com/Probesys/bileto/commit/8460602))
- Don't flush if creating user with flush false and default authorization ([92c42ce](https://github.com/Probesys/bileto/commit/92c42ce))
- Fix overflowing centered popup containers ([8e1d428](https://github.com/Probesys/bileto/commit/8e1d428))

### Technical

- Move the uploads directory under var by default ([f51a61a](https://github.com/Probesys/bileto/commit/f51a61a))
- Update the NPM dependencies ([e02de09](https://github.com/Probesys/bileto/commit/e02de09), [8743710](https://github.com/Probesys/bileto/commit/8743710))
- Make sure folders under var are created in Docker image ([a7d9cf0](https://github.com/Probesys/bileto/commit/a7d9cf0))
- Ignore more files and folders in .dockerignore ([5420c3e](https://github.com/Probesys/bileto/commit/5420c3e))

### Documentation

- Remove warnings stating that Bileto is not ready for production ([e588c96](https://github.com/Probesys/bileto/commit/e588c96))
- Document database volume mounting in the Docker config for admins ([8107b35](https://github.com/Probesys/bileto/commit/8107b35))
- Make explicit that PR checklist is for reviewers ([fdd8a43](https://github.com/Probesys/bileto/commit/fdd8a43))

### Developers

- Allow to reaccount time spent ([88628e7](https://github.com/Probesys/bileto/commit/88628e7))
- Make sure ContractTimeAccounting add time spent to contracts ([b27013c](https://github.com/Probesys/bileto/commit/b27013c))
- Provide a button--ghost variant ([b1508f3](https://github.com/Probesys/bileto/commit/b1508f3))
- Refactor the "multiselect actors" form fields ([936c7bc](https://github.com/Probesys/bileto/commit/936c7bc))

## 2024-11-22 - 0.12.0-beta

### Improvements

- Improve the look of the email notifications ([728f88d](https://github.com/Probesys/bileto/commit/728f88d))
- Allow to answer to the receipt emails ([1d77cad](https://github.com/Probesys/bileto/commit/1d77cad))
- Order contracts by names in the global list ([94fd53b](https://github.com/Probesys/bileto/commit/94fd53b))
- Always display the default organization on the user page ([565e10c](https://github.com/Probesys/bileto/commit/565e10c))
- Make sure that users can always create tickets in their default organization ([aa3d2e1](https://github.com/Probesys/bileto/commit/aa3d2e1))
- Move the observers after the assignee in the tickets ([2788bcd](https://github.com/Probesys/bileto/commit/2788bcd))
- Always show the number of tickets above the list ([b953bf9](https://github.com/Probesys/bileto/commit/b953bf9))
- Change the placeholder of the organization field in the user form ([e425e2e](https://github.com/Probesys/bileto/commit/e425e2e))

### Bug fixes

- Accept `@me` notation in the "quick search" form ([df4be41](https://github.com/Probesys/bileto/commit/df4be41))
- Display all the errors in the "quick search" form ([80b03e5](https://github.com/Probesys/bileto/commit/80b03e5))
- Accept extra fields in the "advanced search" form ([ae7ede4](https://github.com/Probesys/bileto/commit/ae7ede4))
- Use "demande" instead of "requête" in the French translation ([90160a2](https://github.com/Probesys/bileto/commit/90160a2))
- Fix the LDAP synchronization when creating new users ([03027f7](https://github.com/Probesys/bileto/commit/03027f7))

### Technical

- Ignore auto-response emails when collecting emails ([221a7ff](https://github.com/Probesys/bileto/commit/221a7ff))
- Associate the time spent with their corresponding messages ([c041744](https://github.com/Probesys/bileto/commit/c041744))
- Allow to import the ticket's team ([4d0a5c7](https://github.com/Probesys/bileto/commit/4d0a5c7))
- Make Sentry optional in production ([5f6fb1b](https://github.com/Probesys/bileto/commit/5f6fb1b))
- Lock mailbox and mailbox emails during processing ([391f85c](https://github.com/Probesys/bileto/commit/391f85c))
- Log only warning if a user cannot be created with LDAP ([30f3bee](https://github.com/Probesys/bileto/commit/30f3bee))
- Provide a file favicon.ico ([e1a74d8](https://github.com/Probesys/bileto/commit/e1a74d8))
- Update the dependencies ([cfa938a](https://github.com/Probesys/bileto/commit/cfa938a), [a0c98eb](https://github.com/Probesys/bileto/commit/a0c98eb), [35a4995](https://github.com/Probesys/bileto/commit/35a4995), [e7d14d9](https://github.com/Probesys/bileto/commit/e7d14d9), [c9c352e](https://github.com/Probesys/bileto/commit/c9c352e))

### Documentation

- Explain to the administrators how to import data ([50489de](https://github.com/Probesys/bileto/commit/50489de))
- Add documentation about backup and restoration in production ([880c217](https://github.com/Probesys/bileto/commit/880c217))
- Update the available features in the readme and the roadmap ([af6e39b](https://github.com/Probesys/bileto/commit/af6e39b))

### Developers

- Improve the development tooling
    - Replace Vite by esbuild ([5843249](https://github.com/Probesys/bileto/commit/5843249))
    - Allow to pass a `VERSION` param to `make db-migrate` ([adb3dfb](https://github.com/Probesys/bileto/commit/adb3dfb))
    - Allow to run a specific linter with `make lint` ([6144b5f](https://github.com/Probesys/bileto/commit/6144b5f))
    - Allow to choose the installer with `make install` ([13a0343](https://github.com/Probesys/bileto/commit/13a0343))
    - Allow to change the Nginx port in development ([90b4bfd](https://github.com/Probesys/bileto/commit/90b4bfd))
    - Provide a command to run mysql command in the Docker container ([6feb532](https://github.com/Probesys/bileto/commit/6feb532))
    - Declare `COVERAGE`, `FILE` and `FILTER` next to the `make test` command ([b5e2660](https://github.com/Probesys/bileto/commit/b5e2660), [fd590ac](https://github.com/Probesys/bileto/commit/fd590ac))
    - Declare the Docker Composer project name in the docker-compose.yml file ([afe751b](https://github.com/Probesys/bileto/commit/afe751b))
    - Ignore the `uploads/` folder on `make tree` ([1e666db](https://github.com/Probesys/bileto/commit/1e666db))
- Finish to refactor the forms
    - Improve the Bileto theme of forms ([eef60a2](https://github.com/Probesys/bileto/commit/eef60a2))
    - Refactor the search forms ([b3baa55](https://github.com/Probesys/bileto/commit/b3baa55))
    - Refactor the answer form ([ed43f0e](https://github.com/Probesys/bileto/commit/ed43f0e))
    - Refactor the mailbox form ([97db752](https://github.com/Probesys/bileto/commit/97db752))
    - Refactor the ticket forms ([f0f30e8](https://github.com/Probesys/bileto/commit/f0f30e8))
    - Refactor preferences and profile forms ([f70c838](https://github.com/Probesys/bileto/commit/f70c838))
    - Refactor the authorization forms ([6c15390](https://github.com/Probesys/bileto/commit/6c15390))
- Set the default ticket's observers in TicketActivity ([0e6fe33](https://github.com/Probesys/bileto/commit/0e6fe33))
- Enable LDAP server by default in the development environment ([1a9c940](https://github.com/Probesys/bileto/commit/1a9c940))
- Fix the database configuration on the CI ([ed8db12](https://github.com/Probesys/bileto/commit/ed8db12))
- Update the instructions for pull requests ([18e8112](https://github.com/Probesys/bileto/commit/18e8112))
- Install and configure phpstan-symfony ([bad64fe](https://github.com/Probesys/bileto/commit/bad64fe))
- Refactor the `MessageEvent::CREATED` event ([084369f](https://github.com/Probesys/bileto/commit/084369f))
- Handle empty data in the Encryptor ([6210e2e](https://github.com/Probesys/bileto/commit/6210e2e))

## 2024-10-11 - 0.11.0-beta

### Security

- Hide the details of errors when reseting a password ([c66cf04](https://github.com/Probesys/bileto/commit/c66cf04))

### New

- Automate the assignment of teams to tickets ([d070858](https://github.com/Probesys/bileto/commit/d070858))
- Allow to transfer tickets ([52ac714](https://github.com/Probesys/bileto/commit/52ac714))
- Autoclose resolved tickets after 7 days ([a32f870](https://github.com/Probesys/bileto/commit/a32f870))
- Forbid actions in closed tickets ([506d21a](https://github.com/Probesys/bileto/commit/506d21a))
- Send a receipt to the requester when ticket is opened ([b1ef3fb](https://github.com/Probesys/bileto/commit/b1ef3fb))

### Improvements

- Determine the default users' organization from authorizations ([7304749](https://github.com/Probesys/bileto/commit/7304749))
- Account time spent on new contract during a transfer ([f3334b2](https://github.com/Probesys/bileto/commit/f3334b2))
- Detect emails answering to a GLPI server ([5de12d9](https://github.com/Probesys/bileto/commit/5de12d9))
- Allow to thread notification emails in email clients ([c375ada](https://github.com/Probesys/bileto/commit/c375ada))
- Add padding between timeline dates and their following elements ([4f9b95c](https://github.com/Probesys/bileto/commit/4f9b95c))
- Improve the design of the tickets' messages ([1bdc2f8](https://github.com/Probesys/bileto/commit/1bdc2f8))
- Improve the ticket's events messages ([e28066b](https://github.com/Probesys/bileto/commit/e28066b))
- Improve the performance to fetch the ticket page ([31cb55d](https://github.com/Probesys/bileto/commit/31cb55d))

### Bug fixes

- Fix the contracts' pagination ([4e24da8](https://github.com/Probesys/bileto/commit/4e24da8))
- Don't remove observers when self-assigning a ticket ([fcb0b57](https://github.com/Probesys/bileto/commit/fcb0b57))
- Unset correctly previous default role when changing it ([4145da6](https://github.com/Probesys/bileto/commit/4145da6))
- Don't show the "unassigned buttons" if the ticket is closed ([5ebfae8](https://github.com/Probesys/bileto/commit/5ebfae8))
- Add the missing labels to buttons in organization template ([97f6986](https://github.com/Probesys/bileto/commit/97f6986))
- Fix the timeline bar height ([61a14d0](https://github.com/Probesys/bileto/commit/61a14d0))
- Display "optional" label in the label of the organizations' domains ([d5f843f](https://github.com/Probesys/bileto/commit/d5f843f))
- Make sure the messages don't expand out of the screen ([0ed6ce4](https://github.com/Probesys/bileto/commit/0ed6ce4))
- Make sure the super role contains the `admin:*` permission ([e7bf8ac](https://github.com/Probesys/bileto/commit/e7bf8ac))

### Technical

- Update the dependencies ([39d0cbb](https://github.com/Probesys/bileto/commit/39d0cbb))
- Allow to send errors to a Sentry server ([7216468](https://github.com/Probesys/bileto/commit/7216468), [21c35a3](https://github.com/Probesys/bileto/commit/21c35a3))
- Improve the data importation
    - Improve the performance of DataImporter ([82b5bf4](https://github.com/Probesys/bileto/commit/82b5bf4))
    - Improve the importation of documents ([18f26e7](https://github.com/Probesys/bileto/commit/18f26e7))
    - Allow to import the default role ([ef1e2b5](https://github.com/Probesys/bileto/commit/ef1e2b5))
    - Allow to import Messages emailId ([7232231](https://github.com/Probesys/bileto/commit/7232231))
    - Allow to import Tickets updatedAt ([5215858](https://github.com/Probesys/bileto/commit/5215858))
    - Validate only new entities during importation ([c8e3cc6](https://github.com/Probesys/bileto/commit/c8e3cc6))

### Developers

- Provide a command to pull the Docker images ([14a87a4](https://github.com/Probesys/bileto/commit/14a87a4))
- Allow to setup/reset the database without the seeds ([3903511](https://github.com/Probesys/bileto/commit/3903511))
- Refactor the Symfony forms ([1745d70](https://github.com/Probesys/bileto/commit/1745d70))
- Load the application version in the configuration ([57fec7a](https://github.com/Probesys/bileto/commit/57fec7a))
- Increase PHP `memory_limit` in Docker ([60dbdad](https://github.com/Probesys/bileto/commit/60dbdad))
- Refactor sending the "message email" to ticket's actors ([0c72ea3](https://github.com/Probesys/bileto/commit/0c72ea3))
- Refactor getting (confidential) messages ([665785d](https://github.com/Probesys/bileto/commit/665785d))
- Refactor the RecordableEntitiesSubscriber ([6c0cf97](https://github.com/Probesys/bileto/commit/6c0cf97))
- Refactor records of ManyToMany relations changes ([a20a96e](https://github.com/Probesys/bileto/commit/a20a96e))
- Allow to filter organizations by granted permission in OrganizationType ([21c9f07](https://github.com/Probesys/bileto/commit/21c9f07))
- Improve markup of timeline dates ([0f1504c](https://github.com/Probesys/bileto/commit/0f1504c))
- Extract a common.information key ([76d45e2](https://github.com/Probesys/bileto/commit/76d45e2))
- Allow to check permissions on tickets with the AppVoter ([2a003b0](https://github.com/Probesys/bileto/commit/2a003b0))

## 2024-08-26 - 0.10.0-beta

### Migration notes

You can now change the default locale globally by setting the `APP_DEFAULT_LOCALE` in your env file.

### New

- Provide labels ([9dc904c](https://github.com/Probesys/bileto/commit/9dc904c), [8ff1927](https://github.com/Probesys/bileto/commit/8ff1927), [02d62ba](https://github.com/Probesys/bileto/commit/02d62ba), [5c99beb](https://github.com/Probesys/bileto/commit/5c99beb), [00cfe75](https://github.com/Probesys/bileto/commit/00cfe75), [25b01fe](https://github.com/Probesys/bileto/commit/25b01fe))
- Add observers to a tickets ([22a4dd3](https://github.com/Probesys/bileto/commit/22a4dd3), [027faa7](https://github.com/Probesys/bileto/commit/027faa7), [57118af](https://github.com/Probesys/bileto/commit/57118af))
- Allow to reset passwords ([6e4e8bb](https://github.com/Probesys/bileto/commit/6e4e8bb))

### Improvements

- Allow to search for tickets with and without contracts ([28a76cf](https://github.com/Probesys/bileto/commit/28a76cf))
- Separate permissions to see real vs. accounted times spent ([30387e6](https://github.com/Probesys/bileto/commit/30387e6))
- Allow to choose locale when creating/editing a user ([1e5fcb5](https://github.com/Probesys/bileto/commit/1e5fcb5))
- Allow to import teams ([f722522](https://github.com/Probesys/bileto/commit/f722522))
- Allow to import the organizations' domains ([12ea090](https://github.com/Probesys/bileto/commit/12ea090))
- Improve the look of form fieldsets ([1806b8b](https://github.com/Probesys/bileto/commit/1806b8b))

### Bug fixes

- Use the correct white logo ([0e36766](https://github.com/Probesys/bileto/commit/0e36766))
- Fix the header on mobile when not connected ([e35fa48](https://github.com/Probesys/bileto/commit/e35fa48))
- Fix search with "NOT assignee" for unassigned tickets ([5b78fd8](https://github.com/Probesys/bileto/commit/5b78fd8))
- Fix search with "NOT involves" not returning correct results ([8f973a1](https://github.com/Probesys/bileto/commit/8f973a1))
- Allow to list tickets not attached to a contract ([3748d33](https://github.com/Probesys/bileto/commit/3748d33))
- Redirect to the user page after updating ([30df1c0](https://github.com/Probesys/bileto/commit/30df1c0))
- Fix spacing in the list of mailboxes ([78b326d](https://github.com/Probesys/bileto/commit/78b326d))

### Documentation

- Add an item to PR template about data import ([0896d38](https://github.com/Probesys/bileto/commit/0896d38))
- Update the screenshot in the README ([b4213ee](https://github.com/Probesys/bileto/commit/b4213ee))

### Technical

- Allow to pass locale when creating user with CLI ([187add3](https://github.com/Probesys/bileto/commit/187add3))
- Allow to change the default locale globally ([0916174](https://github.com/Probesys/bileto/commit/0916174))
- Add a scheduled message to clean data everyday ([4fc1109](https://github.com/Probesys/bileto/commit/4fc1109))
- Update dependencies ([5aabff3](https://github.com/Probesys/bileto/commit/5aabff3), [3687da6](https://github.com/Probesys/bileto/commit/3687da6))

### Developers

- Fix some PHPUnit Testdox warnings ([59c8441](https://github.com/Probesys/bileto/commit/59c8441))
- Remove the symfony/phpunit-bridge dependency ([c1ccb71](https://github.com/Probesys/bileto/commit/c1ccb71))
- Refactor the Ticket title form ([c8abf72](https://github.com/Probesys/bileto/commit/c8abf72))
- Refactor edit ticket actors with Symfony Form ([8b35064](https://github.com/Probesys/bileto/commit/8b35064))
- Move `Form\Type` classes to `Form\` ([5470325](https://github.com/Probesys/bileto/commit/5470325))
- Set radio/checkbox tick position with margin ([cb14588](https://github.com/Probesys/bileto/commit/cb14588))
- Fix test in TicketSearcherTest failing randomly ([8d7fa9c](https://github.com/Probesys/bileto/commit/8d7fa9c))

## 2024-07-05 - 0.9.0-beta

This is our first beta version! 🥳
Bileto is still not ready for production, but it's closer.
I hope you like the new design :)

### Security

- Don't use the `Reply-To` header to get the requester ([06af973](https://github.com/Probesys/bileto/commit/06af973))

### New

- Allow to associate organizations to email domains ([7e14501](https://github.com/Probesys/bileto/commit/7e14501))
- Allow to define a default user role ([86a2a40](https://github.com/Probesys/bileto/commit/86a2a40))
- Add a page to list all the ongoing contracts ([7f8f683](https://github.com/Probesys/bileto/commit/7f8f683))
- Allow to assign tickets and unaccounted times on contract (re)newal ([e7cc7a4](https://github.com/Probesys/bileto/commit/e7cc7a4))
- Add the list of users in organizations ([4fe3fc7](https://github.com/Probesys/bileto/commit/4fe3fc7))
- Add a page to display information about users ([949ba1c](https://github.com/Probesys/bileto/commit/949ba1c))

### Improvements

- Integrate the new design ([742bb28](https://github.com/Probesys/bileto/commit/742bb28), [46f9ee8](https://github.com/Probesys/bileto/commit/46f9ee8), [0c08313](https://github.com/Probesys/bileto/commit/0c08313), [0bb0b24](https://github.com/Probesys/bileto/commit/0bb0b24), [0babc9b](https://github.com/Probesys/bileto/commit/0babc9b), [77f5dbd](https://github.com/Probesys/bileto/commit/77f5dbd), [2e0a06f](https://github.com/Probesys/bileto/commit/2e0a06f), [fba1c2c](https://github.com/Probesys/bileto/commit/fba1c2c), [9a22326](https://github.com/Probesys/bileto/commit/9a22326), [3f1fb31](https://github.com/Probesys/bileto/commit/3f1fb31), [e54bc34](https://github.com/Probesys/bileto/commit/e54bc34), [a2d14b2](https://github.com/Probesys/bileto/commit/a2d14b2), [07570a1](https://github.com/Probesys/bileto/commit/07570a1), [585f3ce](https://github.com/Probesys/bileto/commit/585f3ce), [176836d](https://github.com/Probesys/bileto/commit/176836d), [53b2272](https://github.com/Probesys/bileto/commit/53b2272))
- Improve performance when fetching from the database ([9f5ce7b](https://github.com/Probesys/bileto/commit/9f5ce7b), [0c18fd5](https://github.com/Probesys/bileto/commit/0c18fd5))
- Enable the TinyMCE autosave plugin ([50adef8](https://github.com/Probesys/bileto/commit/50adef8))
- Redirect to user page after creation ([09d3bf1](https://github.com/Probesys/bileto/commit/09d3bf1))
- Reword some advanced search syntax explanations ([040e49c](https://github.com/Probesys/bileto/commit/040e49c))

### Bug fixes

- Fix deletion of organizations ([63b3563](https://github.com/Probesys/bileto/commit/63b3563))
- Fix the days diff on the ticket page ([96ba9cf](https://github.com/Probesys/bileto/commit/96ba9cf))
- Add a space before unit in the "hours consumed" label ([39ff01e](https://github.com/Probesys/bileto/commit/39ff01e))
- Activate TinyMCE with "gpl" license ([763d15f](https://github.com/Probesys/bileto/commit/763d15f))
- Fix the button--icon height on Webkit ([d8a3cfa](https://github.com/Probesys/bileto/commit/d8a3cfa))

### Technical

- Update the Composer dependencies ([fc90087](https://github.com/Probesys/bileto/commit/fc90087), [120d018](https://github.com/Probesys/bileto/commit/120d018), [a572491](https://github.com/Probesys/bileto/commit/a572491), [770eb06](https://github.com/Probesys/bileto/commit/770eb06), [fa0fb13](https://github.com/Probesys/bileto/commit/fa0fb13), [0ce833c](https://github.com/Probesys/bileto/commit/0ce833c), [29f0702](https://github.com/Probesys/bileto/commit/29f0702), [6fca2d6](https://github.com/Probesys/bileto/commit/6fca2d6), [6f4468f](https://github.com/Probesys/bileto/commit/6f4468f), [e6be6e0](https://github.com/Probesys/bileto/commit/e6be6e0))
- Remove the old PostgreSQL sequences ([33acdbb](https://github.com/Probesys/bileto/commit/33acdbb))

### Developers

- Remind developers to check changes in Firefox and Chrome ([06e9fc0](https://github.com/Probesys/bileto/commit/06e9fc0))
- Refactor the role form with Symfony Form ([4ef39dc](https://github.com/Probesys/bileto/commit/4ef39dc))
- Make Doctrine entities mapping explicit ([56f709c](https://github.com/Probesys/bileto/commit/56f709c))
- Make deprecation warnings more verbose in PHPUnit ([6d02919](https://github.com/Probesys/bileto/commit/6d02919))
- Remove uid from the entities factories ([4a35802](https://github.com/Probesys/bileto/commit/4a35802))
- Lint migrations with PHPCS ([642a93b](https://github.com/Probesys/bileto/commit/642a93b))
- Fix deprecations for Foundry 2 ([6aba189](https://github.com/Probesys/bileto/commit/6aba189))
- Provide phpstan-doctrine package ([6d36ef7](https://github.com/Probesys/bileto/commit/6d36ef7))
- Refactor creation of users with a `UserCreator` service ([37efa0e](https://github.com/Probesys/bileto/commit/37efa0e))
- Improve the development seeds ([b840e96](https://github.com/Probesys/bileto/commit/b840e96))
- Provide an `Email` class to extract domains ([a2318f6](https://github.com/Probesys/bileto/commit/a2318f6))
- Provide a `Url` class to sanitize domains ([a5d7dcd](https://github.com/Probesys/bileto/commit/a5d7dcd))
- Provide a DQL `JSON_CONTAINS` function ([e8988c1](https://github.com/Probesys/bileto/commit/e8988c1))
- Provide the `input-texts` component ([7dfedcc](https://github.com/Probesys/bileto/commit/7dfedcc))
- Extract a `button--discreet-alt` button ([b8ffd2b](https://github.com/Probesys/bileto/commit/b8ffd2b))
- Fix Doctrine deprecation warning about ordering ([2310c67](https://github.com/Probesys/bileto/commit/2310c67))
- Give all agent permissions to technicians in dev environment ([2e50007](https://github.com/Probesys/bileto/commit/2e50007))
- Merge CSS tickets files in `custom/tickets.css` ([9a29d19](https://github.com/Probesys/bileto/commit/9a29d19))
- Rename `.row` classes in `.cols` ([8e14a1b](https://github.com/Probesys/bileto/commit/8e14a1b))
- Set id on "UI select" of multiselect actors ([d1cc409](https://github.com/Probesys/bileto/commit/d1cc409))
- Provide the accordion component ([6e4dd2f](https://github.com/Probesys/bileto/commit/6e4dd2f))
- Introduce the CSS class `widget--small` ([9077d7e](https://github.com/Probesys/bileto/commit/9077d7e))

## 2024-05-07 - 0.8.0-alpha

### Migration notes

Sub-organizations are no longer supported (see [the issue #516](https://github.com/Probesys/bileto/issues/516)).
Your existing sub-organizations will be converted to first-level organizations during this update.
**Important: make sure that all your organizations have different names before running the migrations.**

### New

- Provide teams and agents ([d895133](https://github.com/Probesys/bileto/commit/d895133), [9107ac2](https://github.com/Probesys/bileto/commit/9107ac2), [783e7c0](https://github.com/Probesys/bileto/commit/783e7c0), [5c07222](https://github.com/Probesys/bileto/commit/5c07222), [969b1d7](https://github.com/Probesys/bileto/commit/969b1d7), [33219f4](https://github.com/Probesys/bileto/commit/33219f4), [0976e68](https://github.com/Probesys/bileto/commit/0976e68))
- Allow to import data from a ZIP archive ([f9992ef](https://github.com/Probesys/bileto/commit/f9992ef))

### Improvements

- Allow to combine multiple authorizations on the same organization ([cbd6ca0](https://github.com/Probesys/bileto/commit/cbd6ca0))
- Add navigation to the organizations to the main menu ([6677352](https://github.com/Probesys/bileto/commit/6677352))
- Display spent times hours/minutes in a long format ([b61636e](https://github.com/Probesys/bileto/commit/b61636e))
- Display the contract date alert as a number of days ([bb055b4](https://github.com/Probesys/bileto/commit/bb055b4))
- Display contract hours consumed as a percentage ([73de316](https://github.com/Probesys/bileto/commit/73de316))
- Add a unique constraint on organization names ([eb0a6dc](https://github.com/Probesys/bileto/commit/eb0a6dc))
- Rename "operational users" to "agents" ([f98d663](https://github.com/Probesys/bileto/commit/f98d663))
- Display tickets views if user is an agent ([1c83b30](https://github.com/Probesys/bileto/commit/1c83b30))

### Bug fixes

- Force requester and assignee to be part of the lists ([15faa7e](https://github.com/Probesys/bileto/commit/15faa7e))
- Fix searching tickets by contract if using a subquery ([b30603e](https://github.com/Probesys/bileto/commit/b30603e))
- Assert TimeSpent times are required and > 0 ([0bb8a0f](https://github.com/Probesys/bileto/commit/0bb8a0f))

### Technical

- Remove the concept of sub-organizations ([86dd9ac](https://github.com/Probesys/bileto/commit/86dd9ac))
- Fix installation of dependencies in the Docker image ([777ee4c](https://github.com/Probesys/bileto/commit/777ee4c))
- Update the dependencies ([af602f5](https://github.com/Probesys/bileto/commit/af602f5), [b1b90bf](https://github.com/Probesys/bileto/commit/b1b90bf))

### Developers

- Upgrade to Turbo 8 ([c72c313](https://github.com/Probesys/bileto/commit/c72c313))
- Upgrade to PHPUnit 11 ([989b7f9](https://github.com/Probesys/bileto/commit/989b7f9))
- Upgrade to Rector 1.0 ([bb0dd46](https://github.com/Probesys/bileto/commit/bb0dd46))
- Upgrade to eslint 9 ([4caf4ab](https://github.com/Probesys/bileto/commit/4caf4ab))
- Cache authorizations in the AppVoter ([beccdd0](https://github.com/Probesys/bileto/commit/beccdd0))
- Provide the class FSHelper ([d412967](https://github.com/Probesys/bileto/commit/d412967))
- Refactor repositories save, saveBatch and remove ([e807452](https://github.com/Probesys/bileto/commit/e807452))
- Set updatedBy only if there is an active user ([96ca111](https://github.com/Probesys/bileto/commit/96ca111))
- Allow contract.createdBy and contract.updatedBy to be null ([cf8b116](https://github.com/Probesys/bileto/commit/cf8b116), [fb06f3e](https://github.com/Probesys/bileto/commit/fb06f3e))
- Extract common translations keys ([59281b9](https://github.com/Probesys/bileto/commit/59281b9))
- Load only "orga" authorizations when checking for "any" ([14e96b1](https://github.com/Probesys/bileto/commit/14e96b1))
- Add a findOneOrBuildBy method ([812ceb1](https://github.com/Probesys/bileto/commit/812ceb1))
- Set default empty password on User initialization ([9f0be2c](https://github.com/Probesys/bileto/commit/9f0be2c))
- Set `FindOrCreateTrait` with a generic class ([2a65a93](https://github.com/Probesys/bileto/commit/2a65a93))
- Add the `orga:manage` permission to the default Technician role ([757e96b](https://github.com/Probesys/bileto/commit/757e96b))
- Provide a `AuthorizationHelper::grantTeam` method ([d465d4b](https://github.com/Probesys/bileto/commit/d465d4b))
- Provide an `ArrayHelper` class ([3e1fffb](https://github.com/Probesys/bileto/commit/3e1fffb))
- Provide a `is_agent` Twig function ([f8f477d](https://github.com/Probesys/bileto/commit/f8f477d))
- Move getAuthorizations from AppVoter to AuthorizationRepository ([fec907a](https://github.com/Probesys/bileto/commit/fec907a))
- Move TicketRepository methods related to TicketSearcher ([93a2bb5](https://github.com/Probesys/bileto/commit/93a2bb5))
- Increase PHP memory in the dev environment ([594b5fc](https://github.com/Probesys/bileto/commit/594b5fc))
- Enable DAMA DoctrineTestBundle in tests ([15eca8a](https://github.com/Probesys/bileto/commit/15eca8a))
- Make sure database is initialized on the CI ([f8036d9](https://github.com/Probesys/bileto/commit/f8036d9))
- Output PHPUnit results with TestDox format ([2890d74](https://github.com/Probesys/bileto/commit/2890d74))
- Refactor the clearing of entity manager in tests ([568c3b7](https://github.com/Probesys/bileto/commit/568c3b7))
- Remove the warning about docker-compose.yml version ([c68c0cf](https://github.com/Probesys/bileto/commit/c68c0cf))

## 2024-02-02 - 0.7.0-alpha

### Migration notes

Bileto now requires PHP 8.2+.

You need to set the new environment variable `APP_BASE_URL` in your `.env.local` file (see [env.sample](/env.sample)).
This variable is used to generate absolute URLs in non-HTTP contexts (i.e. from the command line).

The PHP `imap` module is now required.
Bileto uses [PHP-IMAP](https://github.com/Webklex/php-imap) which should make the module optional.
Unfortunately, the library doesn't decode email subjects and attachments correctly by itself.
It works a lot better with the module installed.

PostgreSQL >= 12 is now required. If you’re still using PostgreSQL 11, you must upgrade to a newer version.

Its no longer possible to create sub-organizations.
In next releases, your existing sub-organizations will be transformed into first-level organizations (see [the issue #516](https://github.com/Probesys/bileto/issues/516)).

The structure of the roles changed.
“User” roles (as opposed to admin and operational roles) are now more restricted by default.
You should review these roles and possibly change them to “operational” roles.

### New

- Allow to approve or refuse a solution ([288a17a](https://github.com/Probesys/bileto/commit/288a17a))
- Allow to edit the contracts ([0b72c7c](https://github.com/Probesys/bileto/commit/0b72c7c))

### Improvements

- Disallow the creation of sub-organizations ([ee2cc44](https://github.com/Probesys/bileto/commit/ee2cc44))
- Paginate the tickets lists ([d263301](https://github.com/Probesys/bileto/commit/d263301))
- Improve the lifecycle of the tickets ([85fad0f](https://github.com/Probesys/bileto/commit/85fad0f))
- Create "incident" tickets by default ([4de596a](https://github.com/Probesys/bileto/commit/4de596a))
- Allow emails not to be deleted after collecting them ([4acac03](https://github.com/Probesys/bileto/commit/4acac03))
- Make roles easier to understand ([6d5e2b6](https://github.com/Probesys/bileto/commit/6d5e2b6))
- Reword "admin" to "administrator" ([272eec1](https://github.com/Probesys/bileto/commit/272eec1))
- Warn if user has no permission in their default organization ([911afc4](https://github.com/Probesys/bileto/commit/911afc4))
- Select the default organization when setting an authorization after creating a user ([8cf832e](https://github.com/Probesys/bileto/commit/8cf832e))
- Change the label of the organization of authorization ([ed42e09](https://github.com/Probesys/bileto/commit/ed42e09))
- Change time spent wording from billed/charged to accounted time ([ea707ae](https://github.com/Probesys/bileto/commit/ea707ae))
- Redirect to the contract after its creation ([73700f6](https://github.com/Probesys/bileto/commit/73700f6))
- Initialize default contract alerts on creation ([0fe0a91](https://github.com/Probesys/bileto/commit/0fe0a91))
- Change the traduction of ticket's "Contract" to "Ongoing contract" ([77ab61f](https://github.com/Probesys/bileto/commit/77ab61f))
- Improve the look and behaviour of disabled checkboxes ([8862664](https://github.com/Probesys/bileto/commit/8862664))
- Improve the look of the progress bars ([1ffbe9f](https://github.com/Probesys/bileto/commit/1ffbe9f))
- Improve the CSRF error message ([1595742](https://github.com/Probesys/bileto/commit/1595742))

### Bug fixes

- Display correctly the inline attachments in messages contents ([417a76d](https://github.com/Probesys/bileto/commit/417a76d))
- Attach messageDocuments to notification emails ([164e45a](https://github.com/Probesys/bileto/commit/164e45a))
- Ignore HTML errors when creating tickets from emails ([f0dc217](https://github.com/Probesys/bileto/commit/f0dc217))
- Remove incorrect UTF-8 chars from attachments names ([8d59c95](https://github.com/Probesys/bileto/commit/8d59c95))
- Handle emails with empty body ([31fdc13](https://github.com/Probesys/bileto/commit/31fdc13))
- Fix encoding of the email body ([22ef430](https://github.com/Probesys/bileto/commit/22ef430))
- Track changes to the tickets' ongoing contracts ([c4da224](https://github.com/Probesys/bileto/commit/c4da224))
- Trim name and notes when creating a contract ([46723b4](https://github.com/Probesys/bileto/commit/46723b4))
- Disallow to set contracts maxHours below their consumedHours ([32a5bbe](https://github.com/Probesys/bileto/commit/32a5bbe))
- Use `strcmp` in LocaleSorter if comparison failed with the Collator ([db600db](https://github.com/Probesys/bileto/commit/db600db))
- Wrap pre elements in messages contents ([6b9cdc4](https://github.com/Probesys/bileto/commit/6b9cdc4))
- Remove the orphan parenthesis from the "incident updated on" label ([959ed9d](https://github.com/Probesys/bileto/commit/959ed9d))
- Add a missing HTML closing tag in the header ([3bfbb04](https://github.com/Probesys/bileto/commit/3bfbb04))

### Technical

- Require PostgreSQL >= 12 ([b9917f0](https://github.com/Probesys/bileto/commit/b9917f0))
- Require PHP >= 8.2 ([1dc250f](https://github.com/Probesys/bileto/commit/1dc250f))
- Require the PHP imap module ([0f1699f](https://github.com/Probesys/bileto/commit/0f1699f))
- Upgrade to Symfony 6.4 ([afe5eda](https://github.com/Probesys/bileto/commit/afe5eda))
- Update the dependencies ([ebf01e7](https://github.com/Probesys/bileto/commit/ebf01e7), [af19f56](https://github.com/Probesys/bileto/commit/af19f56), [4dd097d](https://github.com/Probesys/bileto/commit/4dd097d), [df2e5f5](https://github.com/Probesys/bileto/commit/df2e5f5), [62bb824](https://github.com/Probesys/bileto/commit/62bb824))
- Configure the `default_uri` in routing ([47a8fb9](https://github.com/Probesys/bileto/commit/47a8fb9))

### Documentation

- Improve the documentation about contributing to code ([17f5be0](https://github.com/Probesys/bileto/commit/17f5be0))
- Update the roadmap with links to GitHub ([8379dfe](https://github.com/Probesys/bileto/commit/8379dfe))
- Fix typos in the dependencies documentation ([5dcdc05](https://github.com/Probesys/bileto/commit/5dcdc05))

### Developers

- Provide a pagination component ([7d424db](https://github.com/Probesys/bileto/commit/7d424db))
- Allow to check permissions of any user ([bfc7a5d](https://github.com/Probesys/bileto/commit/bfc7a5d))
- Install the Symfony Form component ([e03b841](https://github.com/Probesys/bileto/commit/e03b841))
- Refactor the contract form with the Form component ([184f273](https://github.com/Probesys/bileto/commit/184f273))
- Refactor the monitoring of the activity of the entities ([d86759e](https://github.com/Probesys/bileto/commit/d86759e))
- Add getEntityType to RecordableEntityInterface ([86692d3](https://github.com/Probesys/bileto/commit/86692d3))
- Set the activeUser in CreateTicketsFromMailboxEmailsHandler ([f2db100](https://github.com/Probesys/bileto/commit/f2db100))
- Make deprecations notices less verbose in tests ([4c9ea03](https://github.com/Probesys/bileto/commit/4c9ea03))
- Configure Rector as a new linter ([2c86af0](https://github.com/Probesys/bileto/commit/2c86af0))
- Provide a make db-rollback command ([ee4d7c2](https://github.com/Probesys/bileto/commit/ee4d7c2))
- Clean all the Docker stuff on make docker-clean ([aea07f5](https://github.com/Probesys/bileto/commit/aea07f5))
- Move some docker files under docker/development ([838e769](https://github.com/Probesys/bileto/commit/838e769))
- Fix node bundler for uid != 1000 ([50f3c95](https://github.com/Probesys/bileto/commit/50f3c95))
- Restore validators files when extracting translations ([985ed96](https://github.com/Probesys/bileto/commit/985ed96))

## 2023-11-23 - 0.6.0-alpha

### New

- Allow to create and list contracts ([c66bd7e](https://github.com/Probesys/bileto/commit/c66bd7e), [1895e83](https://github.com/Probesys/bileto/commit/1895e83), [e546c01](https://github.com/Probesys/bileto/commit/e546c01), [e40e54a](https://github.com/Probesys/bileto/commit/e40e54a))
- Allow to assign contracts to tickets ([fc3e93e](https://github.com/Probesys/bileto/commit/fc3e93e), [e2af93d](https://github.com/Probesys/bileto/commit/e2af93d))
- Allow to list the tickets of a contract ([61dda9a](https://github.com/Probesys/bileto/commit/61dda9a))
- Allow to set up alerts on the contracts ([f385dd6](https://github.com/Probesys/bileto/commit/f385dd6))
- Allow to record time spent ([35ab22c](https://github.com/Probesys/bileto/commit/35ab22c))

### Improvements

- Simplify the answer form ([1e93d8e](https://github.com/Probesys/bileto/commit/1e93d8e))
- Put documents visually in the TinyMCE editor ([2bb4837](https://github.com/Probesys/bileto/commit/2bb4837))
- Improve accessibility of popups ([c723bb3](https://github.com/Probesys/bileto/commit/c723bb3))
- Rebalance the global font sizes and borders width ([17e03df](https://github.com/Probesys/bileto/commit/17e03df))
- Round off the buttons ([d140f2b](https://github.com/Probesys/bileto/commit/d140f2b))
- Lighten the look of the "quick search" filters ([5b7b737](https://github.com/Probesys/bileto/commit/5b7b737))
- Improve breadcrumb to navigate in tickets ([d8479a1](https://github.com/Probesys/bileto/commit/d8479a1))
- Improve the look of discreet buttons with caret ([2a71ef1](https://github.com/Probesys/bileto/commit/2a71ef1))
- Remove the underline of anchors rendered as buttons ([06bd6ac](https://github.com/Probesys/bileto/commit/06bd6ac))

### Bug fixes

- Handle email attachments correctly ([c558ef3](https://github.com/Probesys/bileto/commit/c558ef3))
- Adapt the editor background to color scheme ([035a9b2](https://github.com/Probesys/bileto/commit/035a9b2))
- Don't try to recreate roles in production ([a3a6cf2](https://github.com/Probesys/bileto/commit/a3a6cf2))
- Fix the strip list style for even items ([bc52f30](https://github.com/Probesys/bileto/commit/bc52f30))
- Fix the year format rendered by the Twig `dateTrans` filter ([55ef233](https://github.com/Probesys/bileto/commit/55ef233))
- Don't track empty entity changes ([256b79a](https://github.com/Probesys/bileto/commit/256b79a))

### Technical

- Provide a Docker image for production ([dd71926](https://github.com/Probesys/bileto/commit/dd71926), [42260f5](https://github.com/Probesys/bileto/commit/42260f5), [392db06](https://github.com/Probesys/bileto/commit/392db06))
- Update the dependencies ([f31533b](https://github.com/Probesys/bileto/commit/f31533b), [ae0762f](https://github.com/Probesys/bileto/commit/ae0762f))

### Documentation

- Explain how to contribute to the documentation ([7ea774e](https://github.com/Probesys/bileto/commit/7ea774e))
- Adapt the documentation for the "alpha" phase ([56d9058](https://github.com/Probesys/bileto/commit/56d9058))
- Merge the deploy and update files for administrators ([3664744](https://github.com/Probesys/bileto/commit/3664744))
- Add a process before releasing a version ([c4382d4](https://github.com/Probesys/bileto/commit/c4382d4))
- Update the roadmap ([e568670](https://github.com/Probesys/bileto/commit/e568670))
- Update the screenshot ([1abb8a2](https://github.com/Probesys/bileto/commit/1abb8a2))

### Developers

- Upgrade to NodeJS 20 ([fda833c](https://github.com/Probesys/bileto/commit/fda833c))
- Upgrade to Vite 5 ([8e08be9](https://github.com/Probesys/bileto/commit/8e08be9))
- Restore security files on make translations ([9b1afa7](https://github.com/Probesys/bileto/commit/9b1afa7))
- Provide a JS Switch controller ([3bea2cd](https://github.com/Probesys/bileto/commit/3bea2cd))
- Allow to limit the size of inputs ([da36f4d](https://github.com/Probesys/bileto/commit/da36f4d))
- Provide a HoursFormatter Twig extension ([c35caec](https://github.com/Probesys/bileto/commit/c35caec), [f16f071](https://github.com/Probesys/bileto/commit/f16f071))
- Add buttons groups ([3772fa8](https://github.com/Probesys/bileto/commit/3772fa8))
- Refactor the transfer of events of the editor ([a308460](https://github.com/Probesys/bileto/commit/a308460))
- Remove the Changes section from the PR template ([303262d](https://github.com/Probesys/bileto/commit/303262d))
- Add a check for color scheme to the PR checklist ([e7c0868](https://github.com/Probesys/bileto/commit/e7c0868))

## 2023-09-01 - 0.5.0-alpha

### New

- Add support for LDAP authentication ([7aca9e5](https://github.com/Probesys/bileto/commit/7aca9e5), [2acfbb7](https://github.com/Probesys/bileto/commit/2acfbb7), [75e4ce7](https://github.com/Probesys/bileto/commit/75e4ce7))
- Allow to upload images and documents ([14f17fe](https://github.com/Probesys/bileto/commit/14f17fe), [9c8e22f](https://github.com/Probesys/bileto/commit/9c8e22f), [fdc305e](https://github.com/Probesys/bileto/commit/fdc305e), [c7ad8aa](https://github.com/Probesys/bileto/commit/c7ad8aa), [be20bb0](https://github.com/Probesys/bileto/commit/be20bb0))

### Improvements

- Improve the readability of the ticket page ([1c75799](https://github.com/Probesys/bileto/commit/1c75799), [8a3e75c](https://github.com/Probesys/bileto/commit/8a3e75c))
- Customize the errors pages ([276501e](https://github.com/Probesys/bileto/commit/276501e))
- Redirect automatically if user can create ticket in its default organization ([c590e40](https://github.com/Probesys/bileto/commit/c590e40))
- Redirect to the "new authorization" page after creating a user ([35b1826](https://github.com/Probesys/bileto/commit/35b1826))

### Bug fixes

- Allow empty name to be entered in the profile ([7b549e7](https://github.com/Probesys/bileto/commit/7b549e7))

### Techical

- Update the Composer dependencies ([328f655](https://github.com/Probesys/bileto/commit/328f655))
- Update the NPM dependencies ([29073ba](https://github.com/Probesys/bileto/commit/29073ba))

### Documentation

- Update the roadmap and the readme ([bec35c9](https://github.com/Probesys/bileto/commit/bec35c9))
- Improve the doc to check requirements in production ([8f5ce4a](https://github.com/Probesys/bileto/commit/8f5ce4a))
- Reorganize the developers documentation ([4fdd67f](https://github.com/Probesys/bileto/commit/4fdd67f))
- Improve the doc to generate the migrations for MariaDB ([353fe63](https://github.com/Probesys/bileto/commit/353fe63))
- Document the `dev:` prefix in the release section ([0bb57c4](https://github.com/Probesys/bileto/commit/0bb57c4))

### Developers

- Rename the make i18n-extract command ([3073863](https://github.com/Probesys/bileto/commit/3073863))
- Configure the CSS autoprefixer NPM package ([9502721](https://github.com/Probesys/bileto/commit/9502721))
- Add isCreatedBy and isUpdatedBy to the MetaEntityInterface ([e9801be](https://github.com/Probesys/bileto/commit/e9801be))
- Extract a ConstraintErrorsFormatter from BaseController ([cc94181](https://github.com/Probesys/bileto/commit/cc94181))
- Use Docker Compose v2 ([8d65095](https://github.com/Probesys/bileto/commit/8d65095))
- Improve starting a MariaDB database in development ([bf01124](https://github.com/Probesys/bileto/commit/bf01124))
- Provide an "info" alert ([f90870c](https://github.com/Probesys/bileto/commit/f90870c))
- Style the disabled inputs and textareas correctly ([0a9ed91](https://github.com/Probesys/bileto/commit/0a9ed91))
- Improve signature of CommandTestsHelper::executeCommand ([81816de](https://github.com/Probesys/bileto/commit/81816de))
- Remove rollbacks test ([aa6b0d5](https://github.com/Probesys/bileto/commit/aa6b0d5))
- Rename JS forms controllers TicketEditor and NewAuthorizationForm ([35ea05e](https://github.com/Probesys/bileto/commit/35ea05e))
- Reorganize the env files ([b76098d](https://github.com/Probesys/bileto/commit/b76098d))
- Remove the Stylelint rule about comments ([abfac89](https://github.com/Probesys/bileto/commit/abfac89))

## 2023-07-07 - 0.4.0-dev

### Migration notes

Bileto now requires that you configure a mail server to be used.
In consequence, two new `MAILER_DSN` and `MAILER_FROM` environment variables must be set in production.
See [the Symfony documentation](https://symfony.com/doc/7.0/mailer.html) to get help.
It is already configured in development (see [the GreenMail documentation](/docs/developers/greenmail.md)).

The PHP `sodium` and `xsl` extensions are now required.
You must make sure that they are installed on your server.

You must setup a Messenger worker.
Read the [administrator guide to learn how](/docs/administrators/deploy.md) (nearly the end of the document).

### Security

- Fix an <abbr>XSS</abbr> when deleting an organization ([718f757](https://github.com/Probesys/bileto/commit/718f757))

### New

- Send a notification when posting a new message ([eebc2f6](https://github.com/Probesys/bileto/commit/eebc2f6))
- Allow to create and answer to tickets by emails
    - Allow to manage IMAP mailboxes ([9afd7ab](https://github.com/Probesys/bileto/commit/9afd7ab), [a590954](https://github.com/Probesys/bileto/commit/a590954), [29d6446](https://github.com/Probesys/bileto/commit/29d6446))
    - Allow to create tickets by emails ([d6c1293](https://github.com/Probesys/bileto/commit/d6c1293), [09ddf26](https://github.com/Probesys/bileto/commit/09ddf26))
    - Allow to answer to a ticket by email ([b3305c1](https://github.com/Probesys/bileto/commit/b3305c1))
    - List the emails in error on the mailboxes page ([a8fdfaa](https://github.com/Probesys/bileto/commit/a8fdfaa), [d8a3c6e](https://github.com/Probesys/bileto/commit/d8a3c6e))
- Allow to assign a user to an organization ([f436203](https://github.com/Probesys/bileto/commit/f436203))
- Allow to edit users ([854cce6](https://github.com/Probesys/bileto/commit/854cce6))
- Allow to delete roles ([d547f7a](https://github.com/Probesys/bileto/commit/d547f7a))

### Improvements

- Display only tech users in the lists of assignees ([5360bf8](https://github.com/Probesys/bileto/commit/5360bf8))
- Show a notification when profile/preferences are saved ([f989d0b](https://github.com/Probesys/bileto/commit/f989d0b))
- Improve the look of the notifications ([f340549](https://github.com/Probesys/bileto/commit/f340549))
- Show an icon on the messages sent by email ([f3ed9e8](https://github.com/Probesys/bileto/commit/f3ed9e8))

### Documentation

- Update the roadmap ([b2a7705](https://github.com/Probesys/bileto/commit/b2a7705))
- Extract documentation indexes in dedicated files ([b6ae39e](https://github.com/Probesys/bileto/commit/b6ae39e))

### Technical

- Upgrade to Symfony 6.3 ([733d10d](https://github.com/Probesys/bileto/commit/733d10d))
- Setup Symfony Messenger ([6e80eb9](https://github.com/Probesys/bileto/commit/6e80eb9), [d9afabc](https://github.com/Probesys/bileto/commit/d9afabc))
- Separate orga roles in user and tech roles ([16701e6](https://github.com/Probesys/bileto/commit/16701e6))
- Configure GreenMail ([adc8d35](https://github.com/Probesys/bileto/commit/adc8d35), [1be563f](https://github.com/Probesys/bileto/commit/1be563f))
- Fix datetimes of Messages created in seeds ([9fe51fa](https://github.com/Probesys/bileto/commit/9fe51fa))
- Fix the make db-reset command ([0f776ae](https://github.com/Probesys/bileto/commit/0f776ae))
- Configure the CI for the `feat/*` branches ([a038af1](https://github.com/Probesys/bileto/commit/a038af1))
- Move the getters and setters of Uid to MetaEntityTrait ([da6123a](https://github.com/Probesys/bileto/commit/da6123a))
- Block html and body tags with HtmlSanitizer ([380eea1](https://github.com/Probesys/bileto/commit/380eea1))
- Increase the `max_input_length` of HtmlSanitizer ([c685b16](https://github.com/Probesys/bileto/commit/c685b16))
- Update the dependencies ([3be49e0](https://github.com/Probesys/bileto/commit/3be49e0), [29186b0](https://github.com/Probesys/bileto/commit/29186b0), [723451d](https://github.com/Probesys/bileto/commit/723451d), [0a85228](https://github.com/Probesys/bileto/commit/0a85228))

## 2023-05-12 - 0.3.0-dev

### New

- Provide a search engine and a search syntax ([d83d996](https://github.com/Probesys/bileto/commit/d83d996), [abf6054](https://github.com/Probesys/bileto/commit/abf6054))
- Allow to rename the organizations ([8afbde7](https://github.com/Probesys/bileto/commit/8afbde7))
- Allow to delete the organizations ([0d85a97](https://github.com/Probesys/bileto/commit/0d85a97))
- Allow to sort the lists of tickets ([5a4df55](https://github.com/Probesys/bileto/commit/5a4df55))
- Track and display the last activity of tickets ([326fe08](https://github.com/Probesys/bileto/commit/326fe08))
- Add a “remember me” checkbox to the login form ([2acd1de](https://github.com/Probesys/bileto/commit/2acd1de))

### Improvements

- Redesign the lists of organizations ([da08569](https://github.com/Probesys/bileto/commit/da08569))
- Allow to set a password when creating users ([6e902ee](https://github.com/Probesys/bileto/commit/6e902ee))
- Allow to (un)check all the roles at once ([ae96f71](https://github.com/Probesys/bileto/commit/ae96f71))
- Add a margin below the modal titles ([ebbc1ac](https://github.com/Probesys/bileto/commit/ebbc1ac))

### Bug fixes

- Make the user name input really optional ([db3ae5a](https://github.com/Probesys/bileto/commit/db3ae5a))
- Use the correct color scheme after login or logout ([9b95c80](https://github.com/Probesys/bileto/commit/9b95c80))
- Allow public access to the Web Manifest ([3984d0d](https://github.com/Probesys/bileto/commit/3984d0d))

### Documentation

- Improve the production documentation about file permissions ([61a2e91](https://github.com/Probesys/bileto/commit/61a2e91))
- Fix the documentation to reset the database in production ([ec44171](https://github.com/Probesys/bileto/commit/ec44171))
- Fix the documentation to retrieve the latest Git tag ([a5bdf39](https://github.com/Probesys/bileto/commit/a5bdf39))
- Document "Documentation" and "Technical" sections of the changelog ([ed4a960](https://github.com/Probesys/bileto/commit/ed4a960))

### Technical

- Add the support for PHP 8.2 ([36c38e0](https://github.com/Probesys/bileto/commit/36c38e0))
- Add the support for PostgreSQL 11+ and MariaDB 10.4+ ([af667af](https://github.com/Probesys/bileto/commit/af667af))
- Fix the seeds with MariaDB ([1946bb9](https://github.com/Probesys/bileto/commit/1946bb9))
- Provide a Stimulus controller to control the checkboxes ([14828fb](https://github.com/Probesys/bileto/commit/14828fb))
- Provide a `.row--wrap` class ([b370ad0](https://github.com/Probesys/bileto/commit/b370ad0))
- Provide a CSS `.indent` class ([4aa8a10](https://github.com/Probesys/bileto/commit/4aa8a10))
- Remove the `UniqueEntity` constraint from UID fields ([b2d8be7](https://github.com/Probesys/bileto/commit/b2d8be7))
- Use the User UID instead of ID in actors forms ([95a1bfa](https://github.com/Probesys/bileto/commit/95a1bfa))
- Disable XDebug when running PHPStan ([8024cb8](https://github.com/Probesys/bileto/commit/8024cb8))
- Update the dependencies ([927e4df](https://github.com/Probesys/bileto/commit/927e4df), [15ff366](https://github.com/Probesys/bileto/commit/15ff366), [b2680c3](https://github.com/Probesys/bileto/commit/b2680c3), [04563c5](https://github.com/Probesys/bileto/commit/04563c5), [862a354](https://github.com/Probesys/bileto/commit/862a354))

## 2023-03-27 - 0.2.0-dev

### New

- Allow to create roles ([dd6e62f](https://github.com/Probesys/bileto/commit/dd6e62f))
- Allow to create sub-organizations ([8aa07f1](https://github.com/Probesys/bileto/commit/8aa07f1))
- Allow to create users ([ccfd37f](https://github.com/Probesys/bileto/commit/ccfd37f), [e1d9f3b](https://github.com/Probesys/bileto/commit/e1d9f3b), [ecd0832](https://github.com/Probesys/bileto/commit/ecd0832), [79cfab2](https://github.com/Probesys/bileto/commit/79cfab2), [f8a355a](https://github.com/Probesys/bileto/commit/f8a355a))
- Check permissions ([faea252](https://github.com/Probesys/bileto/commit/faea252))
- Display Ticket activity in the timeline ([2115eff](https://github.com/Probesys/bileto/commit/2115eff), [c439709](https://github.com/Probesys/bileto/commit/c439709))
- Add a page to list all the tickets ([a2851cb](https://github.com/Probesys/bileto/commit/a2851cb))
- Add a home page to list tickets owned ([3d161aa](https://github.com/Probesys/bileto/commit/3d161aa), [7e7a7a9](https://github.com/Probesys/bileto/commit/7e7a7a9))
- Allow to edit the status of the tickets ([99fd4bf](https://github.com/Probesys/bileto/commit/99fd4bf))
- Allow to edit user profile ([6bbbbec](https://github.com/Probesys/bileto/commit/6bbbbec), [d35f10e](https://github.com/Probesys/bileto/commit/d35f10e))
- Provide an about page ([3d7a495](https://github.com/Probesys/bileto/commit/3d7a495), [7218152](https://github.com/Probesys/bileto/commit/7218152))
- Improve integration on mobile and desktop apps ([bdab19c](https://github.com/Probesys/bileto/commit/bdab19c))

### Improvements

- Add the new logo ([5dbebde](https://github.com/Probesys/bileto/commit/5dbebde))
- Change the primary color scale ([b810532](https://github.com/Probesys/bileto/commit/b810532))
- Setup Atkinson Hyperlegible font ([36b7917](https://github.com/Probesys/bileto/commit/36b7917))
- Redesign the layout navigation ([fbbf602](https://github.com/Probesys/bileto/commit/fbbf602))
- Redesign the tickets navigation ([6d7325f](https://github.com/Probesys/bileto/commit/6d7325f))
- Redesign the lists of tickets ([2402ffc](https://github.com/Probesys/bileto/commit/2402ffc))
- Allow to show / hide passwords ([530c3f0](https://github.com/Probesys/bileto/commit/530c3f0))
- Allow to select assignee when clicking on "unassigned" ([f8806f8](https://github.com/Probesys/bileto/commit/f8806f8))
- List tickets requested by user in "Your tickets" ([984244d](https://github.com/Probesys/bileto/commit/984244d))
- Change the status field by a "is resolved" checkbox when opening a ticket ([ab2183f](https://github.com/Probesys/bileto/commit/ab2183f))
- Allow to choose the priority when opening a ticket ([153a767](https://github.com/Probesys/bileto/commit/153a767))
- Allow to choose the type when opening a ticket ([8bc053f](https://github.com/Probesys/bileto/commit/8bc053f))
- Display connected user in the users list ([2133951](https://github.com/Probesys/bileto/commit/2133951))
- Display the number of tickets above the list ([c433d18](https://github.com/Probesys/bileto/commit/c433d18))
- Display the number of messages in tickets ([cb055b8](https://github.com/Probesys/bileto/commit/cb055b8))
- Improve rendering of dates in the ticket timeline ([52af077](https://github.com/Probesys/bileto/commit/52af077))
- Detect preferred language from browser ([8ed7599](https://github.com/Probesys/bileto/commit/8ed7599))
- Redirect to /organizations after the creation of an organization ([3acfb63](https://github.com/Probesys/bileto/commit/3acfb63))
- Notify when "update ticket type" fails ([f01410d](https://github.com/Probesys/bileto/commit/f01410d))
- Add a "skip to main content" accessibility anchor ([63c6d9d](https://github.com/Probesys/bileto/commit/63c6d9d))
- Allow to scroll to the top of the page ([c16932b](https://github.com/Probesys/bileto/commit/c16932b))
- Allow to scroll to the bottom of tickets ([4485332](https://github.com/Probesys/bileto/commit/4485332))
- Hide avatars in tickets on small screen ([ba22dd4](https://github.com/Probesys/bileto/commit/ba22dd4))
- Remove year from dates if it's the same as current year ([84c98b5](https://github.com/Probesys/bileto/commit/84c98b5))
- Remove the TinyMCE emoji plugin ([b49cd73](https://github.com/Probesys/bileto/commit/b49cd73))
- Improve sizes on mobile ([411b4e4](https://github.com/Probesys/bileto/commit/411b4e4))
- Always display the tickets organizations ([2a589e9](https://github.com/Probesys/bileto/commit/2a589e9))
- Increase contrast of ticket info titles ([8ace6a3](https://github.com/Probesys/bileto/commit/8ace6a3))
- Set pointer cursor on `<summary>` elements ([ab46915](https://github.com/Probesys/bileto/commit/ab46915))
- Improve the look of popups ([4a1bf43](https://github.com/Probesys/bileto/commit/4a1bf43))
- Improve the look of the layout banners ([300c41d](https://github.com/Probesys/bileto/commit/300c41d))
- Make box-shadow under cards more visible ([2e645f7](https://github.com/Probesys/bileto/commit/2e645f7))
- Change profile icon to id-card ([0b8c417](https://github.com/Probesys/bileto/commit/0b8c417))
- Decrease the font-size of form captions ([3b9d325](https://github.com/Probesys/bileto/commit/3b9d325))

### Documentation

- Add a roadmap ([57661e2](https://github.com/Probesys/bileto/commit/57661e2))
- Add documentation to update the production ([32d2ca6](https://github.com/Probesys/bileto/commit/32d2ca6))
- Update the documentation to update dev environment ([ed1f675](https://github.com/Probesys/bileto/commit/ed1f675))
- Update the documentation to deploy Bileto in prod ([9b0e52e](https://github.com/Probesys/bileto/commit/9b0e52e))
- Add documentation about translations ([95375c9](https://github.com/Probesys/bileto/commit/95375c9))
- Add documentation about managing the dependencies ([3af30d9](https://github.com/Probesys/bileto/commit/3af30d9))
- Complete the documentation to release new versions ([e1443d8](https://github.com/Probesys/bileto/commit/e1443d8))
- Improve the content of the README ([0a6df89](https://github.com/Probesys/bileto/commit/0a6df89))
- Improve the PR template with comments ([0ce7ccf](https://github.com/Probesys/bileto/commit/0ce7ccf))

### Technical

- Provide a notification system ([7e6caa3](https://github.com/Probesys/bileto/commit/7e6caa3))
- Provide a mechanism to seed the database ([6f55272](https://github.com/Probesys/bileto/commit/6f55272))
- Lint more things (containers, twig and translations) ([f19a3b0](https://github.com/Probesys/bileto/commit/f19a3b0))
- Use ICU format for translations ([0adf1e3](https://github.com/Probesys/bileto/commit/0adf1e3), [b110223](https://github.com/Probesys/bileto/commit/b110223), [881e8aa](https://github.com/Probesys/bileto/commit/881e8aa))
- Refactor initialization of meta fields ([3063858](https://github.com/Probesys/bileto/commit/3063858))
- Extract a LocaleSorter from OrganizationSorter ([8ad6c22](https://github.com/Probesys/bileto/commit/8ad6c22))
- Move factories under tests/ folder ([3849b6e](https://github.com/Probesys/bileto/commit/3849b6e))
- Fix GitHub Actions deprecations ([5a0cc15](https://github.com/Probesys/bileto/commit/5a0cc15))
- Update dependencies ([003bab6](https://github.com/Probesys/bileto/commit/003bab6), [c163589](https://github.com/Probesys/bileto/commit/c163589), [c17e97d](https://github.com/Probesys/bileto/commit/c17e97d), [15dd9a5](https://github.com/Probesys/bileto/commit/15dd9a5), [a84479c](https://github.com/Probesys/bileto/commit/a84479c), [a087c03](https://github.com/Probesys/bileto/commit/a087c03), [0efd5a5](https://github.com/Probesys/bileto/commit/0efd5a5), [c138def](https://github.com/Probesys/bileto/commit/c138def), [5160ac8](https://github.com/Probesys/bileto/commit/5160ac8), [78c7a46](https://github.com/Probesys/bileto/commit/78c7a46), [db0c913](https://github.com/Probesys/bileto/commit/db0c913), [662c172](https://github.com/Probesys/bileto/commit/662c172))

### Misc

- Update copyright notices ([d6a44e2](https://github.com/Probesys/bileto/commit/d6a44e2), [189a1ff](https://github.com/Probesys/bileto/commit/189a1ff))

## 2022-12-08 - 0.1.0-dev

### New

- Allow users to login ([8924d27](https://github.com/Probesys/bileto/commit/8924d27))
- Allow to create and list the organizations ([d141c61](https://github.com/Probesys/bileto/commit/d141c61))
- Open tickets via the interface ([915520f](https://github.com/Probesys/bileto/commit/915520f))
- Filter owned and unassigned tickets ([aab4b73](https://github.com/Probesys/bileto/commit/aab4b73))
- Allow to answer to a ticket ([1bc64ee](https://github.com/Probesys/bileto/commit/1bc64ee))
- Change the status when answering to a ticket ([feafea8](https://github.com/Probesys/bileto/commit/feafea8))
- Post confidential messages ([403538b](https://github.com/Probesys/bileto/commit/403538b))
- Post a solution ([679f547](https://github.com/Probesys/bileto/commit/679f547))
- Change requester and assignee of a ticket ([d27c9d4](https://github.com/Probesys/bileto/commit/d27c9d4))
- Change the priority of the tickets ([f1cc2ec](https://github.com/Probesys/bileto/commit/f1cc2ec))
- Turn ticket into incident or request ([f4d53a0](https://github.com/Probesys/bileto/commit/f4d53a0))
- Rename the tickets ([4b00279](https://github.com/Probesys/bileto/commit/4b00279))
- Disallow changing status if ticket status is finished ([385096e](https://github.com/Probesys/bileto/commit/385096e))
- Allow to self-assign a ticket ([fe28553](https://github.com/Probesys/bileto/commit/fe28553))
- Allow a user to choose its language ([c37460b](https://github.com/Probesys/bileto/commit/c37460b))
- Add a dark mode ([0e4fb2f](https://github.com/Probesys/bileto/commit/0e4fb2f))

### Misc

- Provide documentation to deploy in production ([d398aea](https://github.com/Probesys/bileto/commit/d398aea))
- Add information on how to contribute ([9b62c60](https://github.com/Probesys/bileto/commit/9b62c60))
- Add a PR template ([a0ce04f](https://github.com/Probesys/bileto/commit/a0ce04f))
- Add the AGPL license ([a2430a8](https://github.com/Probesys/bileto/commit/a2430a8))
- (cli) Allow to create users ([04ff6ab](https://github.com/Probesys/bileto/commit/04ff6ab))

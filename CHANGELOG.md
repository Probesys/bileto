# Changelog of Bileto

## unreleased

### Migration notes

Bileto now requires PHP 8.2+.

You need to set the new environment variable `APP_BASE_URL` in your `.env.local` file (see [env.sample](/env.sample)).
This variable is used to generate absolute URLs in non-HTTP contexts (i.e. from the command line).

The PHP `imap` module is now required.
Bileto uses [PHP-IMAP](https://github.com/Webklex/php-imap) which should make the module optional.
Unfortunately, the library doesn't decode email subjects and attachments correctly by itself.
It works a lot better with the module installed.

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

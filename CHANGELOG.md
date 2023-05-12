# Changelog of Bileto

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

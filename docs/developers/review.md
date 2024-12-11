# Reviewing the pull requests

Each change to the repository must be reviewed through a code review.
A template with a checklist is provided and filled by a reviewer for each pull request.

**This document is intended for the reviewers.**
If you don’t know how to do a specific task of the checklist, please take a look at the following explanations.

Some items of the checklist may feel out of context: please check them anyway.

## “Code is manually tested”

This is to remind you to test the changes yourself.
The idea is to follow the instructions given in the “How to test manually” section, and to verify that it works as expected.
Sometimes, you’ll realize at this stage that something important has been forgotten.

## “Permissions / authorizations are verified”

Bileto is based on a custom system of roles.
This system is explained in the document [“Roles & permissions”](/docs/developers/roles.md).
In particular, controllers' actions must be protected by the appropriate authorization checks.

## “New data can be imported correctly”

When new entities or fields are created, we need to consider making this data importable by [the `DataImporter` service](/src/Service/DataImporter/DataImporter.php).
However, not all data needs to be imported.
If fields are changed or removed, the service needs to be adjusted as well.

## “Interface works on both mobiles and big screens”

Bileto must work on desktop and mobiles.
If the interface is changed, you should check that it still works on mobiles.
For that, you can use the “mobile mode” of your browser (e.g. <kbd>CTRL + ALT + M</kbd> in Firefox).

## “Interface works in both light and dark modes”

Bileto provides light and dark modes to suit user preferences.
If colors are properly used as explained in [the `colors.light.css` file](/assets/stylesheets/variables/colors.light.css), everything should already be fine.
However, you should still manually check that it works in both modes.
You can change the mode in the “Preferences” page of Bileto.

## “Interface works on both Firefox and Chrome”

Bileto must work well on all major browsers.
We strongly support Firefox and it is our standard web browser as we develop with it.
However, Chrome is the number one brower in terms of market share.
Please always try your changes with at least Firefox and Chrome (or Chromium, or at least a Webkit browser).

## “Accessibility has been tested”

An inaccessible application is a broken one and an accessibility issue must be considered as a bug.
However, testing the accessibility can be pretty hard when you’re not an expert.
To get started, you can use the [WAVE browser extension](https://wave.webaim.org/extension/).
This extension highlight some usual issues.
Please try to fix the ones related to the changes of the pull/merge request.

Additional testing can include testing with a screen reader, or directly with people who need accessibility features.
However, these solutions are more difficult to get right.

## “Translations are synchronized”

Bileto is provided in English and in French.
The French translation must be synchronized with the English one.

You can run the command `make translations` to verify that everything is translated.
Please make sure to sort the translations in alphabetical order in the translations files.

[Learn more about the translations.](/docs/developers/translations.md)

## “Tests are up to date”

Everything in Bileto is not tested, but we try to write the most pertinent tests.
In this context, we mainly write [“Application Tests”](https://symfony.com/doc/current/testing.html#application-tests) to automate the tests in Bileto.
If a change impacts a controller, it’s likely that some tests must be written about the change.
The tests are located under [the `tests/` folder](/tests) and are written with [PHPUnit](https://docs.phpunit.de).

[Learn how to execute the tests.](/docs/developers/tests.md)

## “Copyright notices are up to date”

At the top of each file, we include a small comment to indicate the copyright of the file and the license.
Developer must add a line if they made a significant change to the file:

```
Copyright <year> <name>
```

## “Documentation is up to date”

Keeping the documentation up to date is a difficult task.
We try to make it easy by making sure that we’ve documented everything relevant in each pull request.
The documentation includes everything under [`docs/`](/docs), the [“readme”](/README.md), and the migration notes in the [changelog](/CHANGELOG.md).

The migration notes are important information that needs to be carried out when upgrading to the next version of Bileto.
It’s not often that we have to document this, though.

## “Merge request has been reviewed and approved”

It is to remind you to review the changes in the code and to approve the pull request.

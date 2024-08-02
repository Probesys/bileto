# How to contribute to Bileto

## Submit an idea

If you want to share an idea that you have, you can [open a ticket](https://github.com/Probesys/bileto/issues) in our bugtracker.
Please always check that a similar ticket is not already opened to avoid duplicates.
We might ask you more questions to understand what you expect from your ideas.
The more context you give, the more likely we will include your suggestion in our plans.

Please note that it can take time before we add your suggestion in our roadmap.
We’ll let you know what we plan to do with your suggestion as soon as we know it ourselves.

## Report a bug

Bugs are everywhere, even in the most tested applications.
We would be grateful if you [open a ticket](https://github.com/Probesys/bileto/issues) if you encounter a bug.
Please always check that a similar ticket is not already opened to avoid duplicates.

**Special note about security issues:** please let we know by a more private channel to avoid disclosing information that would harm our users.
You can use [our contact form on our website](https://www.probesys.com/contact) for instance.

## Contribute to the code

You want to contribute to the code of Bileto? That's great!
Here are some information that may help you.

### Identify a task

If you want to contribute to the code, you should start by looking at [the tickets in GitHub](https://github.com/Probesys/bileto/issues).
Some tickets are labelled [“easy task”](https://github.com/Probesys/bileto/issues?q=is%3Aissue+is%3Aopen+label%3A%22easy+task%22): those are particularly suitable for newcomers.

Note that we value more the contributions from people who are actually interested in Bileto.
The “easy tasks” are for people who want to actively contribute to the project.
If you’re only interested in getting a "contribution badge" on your GitHub profile, please pass.

Once you’ve identified a task, don’t hesitate to leave a message in the ticket to let us know that you’re interested in contributing.
We’ll assign it to you.

### Fork the project

In order to be able to open a pull request, you must fork the project first:

1. open an account on [GitHub](https://github.com);
2. fork the repository on your profile (i.e. the “fork” button on the top right of the repository page);
3. clone the repository locally (e.g. `git clone https://github.com/<your-username>/bileto.git`);
4. start a new branch (e.g. `git switch -c patch`);

### Get help

You’ll need some help to get started.
First of all, you should read [the Developers' Guide](/docs/developers/README.md) (especially to learn how to setup your development environment).

Not everything is properly documented, so don't hesitate to ask us your questions in the ticket you’ve been assigned.

### Open a Pull Request (<abbr>PR</abbr>)

Once you’re done with your changes:

1. push your branch on GitHub (e.g. `git push -u origin HEAD`);
2. open [a pull request](https://github.com/Probesys/bileto/compare) (click on the “compare across forks” link, then select your branch with the “compare” button).

When you open your pull request, you’ll be asked some information:

- a related ticket opened in the bugtracker (the one you’ve been assigned);
- how we can test the changes manually (instructions step by step in order to verify that your changes work);
- and a checklist to make sure that you (and we) don’t forget anything important.

Some items of the checklist may feel out of context for your pull request: please check them anyway and add “N/A” at the end of the line.

If you don’t know how to do a specific task of the checklist, please take a look at the following explanations.
If something is still not clear, let we know so we can assist you.
There’s no reasons to feel ashamed of not knowing something, and you’ll hopefully learn something on your way.

#### “Code is manually tested”

This is to remind you to test your changes yourself.
The idea is to follow the instructions that you gave in the “How to test manually” section, and to verify that it works as expected.
Sometimes, you’ll realize at this stage that you‘ve forgotten something important.

#### “Permissions / authorizations are verified”

Bileto is based on a custom system of roles.
This system is explained in the document [“Roles & permissions”](/docs/developers/roles.md).
This item reminds you to put the required actions behind an authorization.

#### “Data can be imported correctly”

When new entities or fields are created, we need to consider making this data importable by [the `DataImporter` service](/src/Service/DataImporter/DataImporter.php).
However, not all data needs to be imported.
If fields are changed or removed, the service needs to be adjusted as well.

#### “Interface works on both mobile and big screen”

We want Bileto to work on desktop and mobiles.
If you’ve changed the interface, you should check that it still works on mobile.
For that, you can use the “mobile mode” of your browser (e.g. <kbd>CTRL + ALT + M</kbd> in Firefox).

#### “Interface works in both light and dark modes”

Bileto provides light and dark modes to suit user preferences.
If you have used the colors as explained in [the `colors.light.css` file](/assets/stylesheets/variables/colors.light.css), everything should already be fine.
However, you should still manually check that it works in both modes.
You can change the mode in the “Preferences” page of Bileto.

#### “Interface works on both Firefox and Chrome”

We aim to make Bileto work well on all major browsers.
We strongly support Firefox and it
This is our standard web browser and we develop with it.
However, Chrome is the number one brower in terms of market share.
Please always try your changes with at least Firefox and Chrome (or Chromium, or at least a Webkit browser).

#### “Accessibility has been tested”

We want Bileto to be as accessible as possible.
An accessibility issue in Bileto is considered as a bug.
However, testing the accessibility can be pretty hard when you’re not an expert.
To get started, we recommend you to use the [WAVE browser extension](https://wave.webaim.org/extension/).
This extension highlight some usual issues.
Please try to fix the ones related to your changes.

Additional testing can include testing with a screen reader, or directly with people who need accessibility features.
However, these solutions are more difficult to get right.

#### “Tests are up-to-date”

Everything in Bileto is not tested, but we try to write the most pertinent tests.
In this context, we mainly write [“Application Tests”](https://symfony.com/doc/current/testing.html#application-tests) to automate the tests in Bileto.
If you’ve made a change at a controller level, it’s likely that we’ll ask you to write a test about your change.
The tests are located under [the `tests/` folder](/tests) and are written with [PHPUnit](https://docs.phpunit.de).
[Learn how to execute the tests.](/docs/developers/tests.md)

#### “Locales are synchronized”

Bileto is provided in English and in French.
You can run the command `make translations` to verify that everything is translated.
Please make sure to sort the translations in alphabetical order in the translations files.
[Learn more about the translations.](/docs/developers/translations.md)

#### “Copyright notices are up to date”

At the top of each file, we include a small comment to indicate the copyright of the file and the license.
If you’ve made a significant change to the file, please add a line:

```
Copyright <year> <your name>
```

#### “Documentation is up to date”

Keeping the documentation up to date is a difficult task.
We try to make it easy by making sure that we’ve documented everything relevant in each pull request.
The documentation includes everything under [`docs/`](/docs), the [“readme”](/README.md), and the migration notes in the [changelog](/CHANGELOG.md).
The migration notes are important information that needs to be carried out when upgrading to the next version of Bileto.
It’s not often that we have to document this, though.

## Contribute to the documentation

To contribute to the documentation (e.g. fix a typo, add a section), you need to follow the same process as for contributing to the code.

The documentation is located in the `docs/` folder and is split into two parts:

- [The Administrators' Guide](/docs/administrators/README.md) is for technical people who want to install Bileto on their server;
- and [The Developers' Guide](/docs/developers/README.md) is for developers who want to contribute to the Bileto code.

The latter is itself divided into five sections:

- the global section is for all the developers;
- the backend and frontend sections contain topics specific to the backend or the frontend work;
- the maintainers section is intended to help us perform the maintenance tasks;
- and the architecture section is intended to explain some technical decisions.

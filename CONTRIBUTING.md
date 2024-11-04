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
- how we can test the changes manually (instructions step by step in order to verify that your changes work).

The checklist is intended to the reviewers: you don't have to fill it.
It allows us to make sure that you (and we) don’t forget anything important.

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

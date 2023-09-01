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

## Open a Pull Request (<abbr>PR</abbr>)

You want to contribute to the code of Bileto? That's great!
Here are some information that may help you:

1. open an account on [GitHub](https://github.com);
2. fork the repository on your profile (i.e. the “fork” button on the top right of the repository page);
3. clone the repository locally (e.g. `git clone https://github.com/your-username/bileto.git`), or make your changes directly via the GitHub editor;
4. push your changes in a new branch on GitHub;
5. open [a pull request](https://github.com/Probesys/bileto/compare) (click on the “compare across forks” link, then select your branch with the “compare” button).

When you open your pull request, you’ll be asked some information:

- a related ticket opened in the bugtracker (if any);
- what you are changing technically speaking;
- how we can test the changes manually;
- and a checklist to make sure that you (and we) don’t forget anything important.

Some items of the checklist may feel out of context for your pull request: please check them anyway and add “N/A” at the end of the line.

If you don’t know how to do a specific task of the checklist, please let we know so we can assist you.
There’s no reasons to feel ashamed of not knowing something, and you’ll hopefully learn something on your way.

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

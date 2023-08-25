# Translating Bileto

Bileto uses the default system of translations of Symfony.
Documentation:

- [Translations in Symfony](https://symfony.com/doc/current/translation.html)
- [Using the ICU MessageFormat](https://symfony.com/doc/current/reference/formats/message_format.html)

## Format

We use the [ICU format](https://unicode-org.github.io/icu/userguide/format_parse/messages/) to format the translations.

## Files location

The files are located under [the `translations` folder](/translations).

## Domains

Two domains are actively used:

- messages: the default domain, most of the translations belong to this domain
- errors: the domain used for the errors to display to the user (i.e. entity validations and controllers errors)

The `validators` and `security` domains are those created by the Symfony bundles.
You should not worry about them.

## Format of translations keys

The translations keys are using keywords (e.g. `users.index.title`).

The first keyword is generally refering to the controller or entity to which the translation belongs (e.g. `users.*`).
It can also refers to:

- another controller/entity (e.g. when linking to another part of the application);
- the layout (i.e. `layout.*`)
- the forms (i.e. `forms.*`) for translations used in several forms (e.g. `forms.save_changes`)

When a translation is specific to a template/controller, the second keyword is generally the name of the controller action (e.g. `users.index.*`).
It also can refer to an entity field (e.g. `users.email`).

**Please keep the translations keys in alphabetical order.**

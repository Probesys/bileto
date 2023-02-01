# Using the translations

Bileto uses the default system of translations of Symfony.
Documentation:

- [Translations in Symfony](https://symfony.com/doc/current/translation.html)
- [Using the ICU MessageFormat](https://symfony.com/doc/current/reference/formats/message_format.html)

## Format

We use the [ICU format](https://unicode-org.github.io/icu/userguide/format_parse/messages/) to format the translations.

## Files location

The files are located under [the `translations` folder](/translations).

## Domains

Three domains are actually used:

- messages: the default domain, most of the translations belong to this domain
- validators: the domain used by the entities validators (and sometimes in controllers to return error messages)
- security: the domain used by the security bundle of Symfony (we almost never use it)

## Format of messages keys

The translations keys of the message domain are using keywords (e.g. `users.index.title`).

The first keyword is generally refering to the controller or entity to which the translation belongs (e.g. `users.*`).
It can also refers to:

- another controller/entity (e.g. when linking to another part of the application);
- the layout (i.e. `layout.*`)
- the forms (i.e. `forms.*`) for translations used in several forms (e.g. `forms.save_changes`)

When a translation is specific to a template/controller, the second keyword is generally the name of the controller action (e.g. `users.index.*`).
It also can refer to an entity field (e.g. `users.email`).

**Please keep the translations keys in alphabetical order.**

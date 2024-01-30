# Roles & permissions

In Bileto, the permissions are handled by the [Role](/src/Entity/Role.php) entity.

Roles are associated to the users via the [Authorization](/src/Entity/Authorization.php) entity.
An authorization is associated to a role and a user.
It also can be associated to an organization.

In Bileto, roles can be created by the administrators in order to customize the access given to the users.

## The permissions

The permissions allow the actions of a user in Bileto.
For instance, there is a specific permission to create tickets, and another one to update the title of a ticket.
Permissions are directly attached to the roles.
When creating a role, an administrator can select the permissions that will be attached to the role.

## The types of roles

There are three (or four) types of roles:

- Administrator (and Super): these roles give access to the administration of Bileto. There is one and only one “Super” role which cannot be removed.
- Operational: these roles give access to the back office and allow users with operational roles to be assigned to tickets.
- User: these roles are intended to the end-users so they have access to the assistance tools.

The type defines the kind of permissions that can be attached to a role.
For instance, an Operational role can “Answer confidentially to tickets”, while User roles cannot.

## The authorizations

By default, a user can do nothing in Bileto: it needs to be attached to a role first.
A user is attached to a role thanks to an Authorization.
An Authorization is the object that attaches a user to a role and an optional organization.
The organization defines the scope of the authorization.

## The scope of authorizations

An authorization can be limited to a certain organization, or it can be applied globally.
It's called the scope of the authorization.
The scope is only relevant to the Operational and User roles.
Indeed, the Administrator roles give permissions outside of the organizations.

## Authorizations conflicts

A user cannot have two authorizations concerning the same scope.
Bileto refuses to give two authorizations concerning the same organization to a user.

However, two scopes can be in conflict (e.g. an authorization with a global scope, and another one associated to an organization).
When the scope of two authorizations conflict with each other, it's the most specific one which applies (organization > global).

Also, only one administrator role can be given to a user.

## The “Super” role

A special Super role is automatically created in the database and cannot be deleted, nor modified.
It has the unique `admin:*` permission, which means it gives access to everything in the administration.
It is especially useful to recover from a mistake.
For instance, in the case you've removed the permissions to manage roles from all the other admin roles.

## Technical aspects

### Granting roles to users

You can grant a role to a user with the [`AuthorizationRepository`](/src/Repository/AuthorizationRepository.php):

```php
$user = /* get some user */;
$role = /* get some role */;

$authorizationRepository->grant($user, $role);

// or, to grant a role scoped to a certain organization
$organization = /* get some organization */;
$authorizationRepository->grant($user, $role, $organization);
```

### Checking permissions with Symfony

**Note of caution:** the Symfony roles are not related to the Bileto roles and **we don't use them.**
Only the method `getRoles()` of the `User` entity returns a "Symfony role" because this method is required by the authentication system.

#### The `Voter`

Documentation: [symfony.com](https://symfony.com/doc/current/security/voters.html)

Bileto has a unique Voter to check the permission of a user: [`AppVoter`](/src/Security/AppVoter.php).
It loads the applicable user authorization and related role, then it checks that the role includes the current checked permission.

#### How to check the permissions

Documentation: [symfony.com](https://symfony.com/doc/current/security.html#access-control-authorization)

In controllers:

```php
$this->denyAccessUnlessGranted('orga:see', $organization);

// or

if ($this->security->isGranted('orga:see', $organization)) {
    // do something
}
```

In templates:

```twig
{% if is_granted('orga:manage', organization) %}
    <a href="...">Delete</a>
{% endif %}
```

You can check that a permission is given by a global authorization:

```php
$this->denyAccessUnlessGranted('orga:see', 'global');
```

Or that a permission is given at least by one authorization:

```php
$this->denyAccessUnlessGranted('orga:see', 'any');
```

It is also possible to create an error of access manually with:

```php
if (/* some condition */) {
    throw $this->createAccessDeniedException();
}
```

#### Find organizations authorized for a user

Users are authorized to access an organization if they are associated with an “operational” or a “user” role.
Granting access to an organization includes all its sub-organizations.
To load all the organizations for which the user is authorized:

```php
namespace App\Repository\OrganizationRepository;

function someController(OrganizationRepository $orgaRepository)
{
    $user = $this->getUser();
    $organizations = $orgaRepository->findAuthorizedOrganizations($user);
}
```

### Adding new permissions

To add new permissions to Bileto, you must add it to the `PERMISSIONS` constant of [the `Role` entity](/src/Entity/Role.php).
A permission is given for a specific type of role.

The admin permissions are completely separated from the other.
The permissions of the operational and user roles can overlap though.
In this case, you must add the permission in both groups.

Please see below to learn the syntax of the permissions.

#### Permission syntax

The permissions are represented as strings compounds of several terms separated by colons (`:`).

The first term is always one of `admin` or `orga`.
This is to easily check that a permission can be scoped (`orga`), or only applies to the administration (`admin`).

The second term must be a verb of action among the following:

- `see` to "show" a resource (e.g. an organization, a ticket)
- `list` to "list" the resources
- `create` to "create" resources
- `update` to "update" resources
- `delete` to "delete" resources
- `manage` to gives rights on all the previous operations

The only exception is for the super permission `admin:*` where the verb is replaced by a `*`.
This is to take care of the case where new actions appear in the future which would not be included in the `manage` action.

Several other terms can follow then:

- a resource, usually in plural form, e.g. `orga:create:tickets` (to create _tickets_)
- an attribute of a resource, e.g. `orga:update:tickets:title` (to update the _title_ of tickets)
- a free term to specify a variation, e.g. `orga:create:tickets:messages:confidential` (to create _confidential_ messages in the tickets)

#### Translating permissions

When you create a new permission, you must give it a translated label which appears in the "new/edit role" forms.
As the translation keys are built dynamically, the Symfony `translation:extract` cannot extract them.
This is why you need to put the translation key corresponding to the permission in a special file: [`Misc/AdditionalTranslations.php`](/src/Misc/AdditionalTranslations.php).

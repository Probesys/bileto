# Working with the roles & permissions

In Bileto, the permissions are handled by the [Role](/src/Entity/Role.php) entity.
Roles are associated to the users via the [Authorization](/src/Entity/Authorization.php) entity.

An authorization is associated to a role and a user.
It also can be associated to an organization.

## Administrative roles

The admin roles give access to the global "Settings" section and the related actions.

Authorizations associated to an admin role are never associated to an organization: they are always applied globally.

Permissions examples:

- access to the global settings (always applied): `admin:see`
    - manage the roles: `admin:manage:roles`
    - manage the users: `admin:manage:users`
    - manage the organizations: `admin:manage:organizations`

Only one admin role can be given to a user.

Moreover, a special "super-admin" role is automatically created in the database and cannot be deleted, nor modified.
It has the unique `admin:*` permission, which means it gives access to everything in the administration.
It is especially useful to recover from a mistake.
For instance, in the case you've removed the permissions to manage roles from all the other admin roles.

## Organization roles

The orga roles are applied to the actions available inside an organization (e.g. create a ticket, change the status of a ticket).

Authorizations associated to the orga roles are associated to zero or one organization.
If an authorization is not associated to an organization, it is applied to all the organizations.
The authorization is applied in cascade to the sub-organizations.
It is the most specific authorization which applies (sub-organization > organization > global).

Permissions examples:

- see the organization: `orga:see`
    - manage the organization: `orga:manage`
- list the users: `orga:list:users`
    - manage the users: `orga:manage:users`
- list the contracts: `orga:list:contracts`
    - manage the contracts: `orga:manage:contracts`
- create a ticket: `orga:create:tickets`
- list all the tickets: `orga:list:tickets:all`
- answer to tickets: `orga:create:tickets:messages`
    - answer with confidential message: `orga:create:tickets:messages:confidential`
    - answer with a solution: `orga:create:tickets:messages:solution`
- update the ticket status: `orga:update:tickets:status`
- update the ticket type: `orga:update:tickets:type`
- update the ticket title: `orga:update:tickets:title`
- update the ticket actors: `orga:update:tickets:actors`
- update the ticket priority: `orga:update:tickets:priority`
- see the ticket contract: `orga:see:tickets:contract`
    - update the ticket contract: `orga:update:tickets:contract`

## Granting roles to users

You can grant a role to a user with the [`AuthorizationRepository`](/src/Repository/AuthorizationRepository.php):

```php
$user = /* get some user */;
$role = /* get some role */;

$authorizationRepository->grant($user, $role);

// or, to grant a role limited to a certain organization
$organization = /* get some organization */;
$authorizationRepository->grant($user, $role, $organization);
```

## Checking permissions with Symfony

**Note of caution:** the Symfony roles are not related to the Bileto roles and **we don't use them.**
Only the method `getRoles()` of the `User` entity returns a "Symfony role" because this method is required by the authentication system.

### The `Voter`

Documentation: [symfony.com](https://symfony.com/doc/current/security/voters.html)

Bileto has a unique Voter to check the permission of a user: [`AppVoter`](/src/Security/Voter.php) (not implemented yet).
It loads the applicable user authorization and related role, then it checks that the role includes the current checked permission.

### How to check the permissions

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

You should not need this, but it is possible to create an error of access manually with:

```php
if (/* some condition */) {
    throw $this->createAccessDeniedException();
}
```

## Permission syntax

The permissions are represented as strings compounds of several terms separated by colons (`:`).

The first term is always one of `admin` or `orga`.
This is to easily check that a permission belongs to the current role (e.g. `orga:update` doesn't belong to `admin` roles).

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

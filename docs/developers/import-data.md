# Importing data

Bileto allows to import data from a ZIP archive.
This allows in particular to import data from GLPI.

## The DataImporter service

The class responsible for the import is the [`DataImporter` service](/src/Service/DataImporter/DataImporter.php).
The file is already extensively commented, so we don't say more here about it.

## Specifications

### The command

For now, we only provide a command to execute in a console. This allows to simplify the implementation as we don't need to handle potential timeouts.
The command is named `app:data:import` and takes a path to a ZIP file:

```console
$ php bin/console app:data:import /path/to/archive.zip
```

The command extracts the archive and check every file in it. If at least one file is invalid, it fails.

### Checking the data

Before importing the data into the database, the command performs some checks to verify the integrity and validity of the files.

Constraints can be associated to fields as expressed in the next section. Most of them are already declared in the code of the Symfony entities, with assertions (c.f. `Assert\*`). Unfortunately, some assertions cannot be performed before the data is stored into the database. For instance:

- organizations' max length can be checked easily
    ```php
    $organization = new Organization();
    $organization->setName($name);
    $errors = $validator->validate($organization);
    ```
- but uniqueness of users' emails cannot be checked unless the data is imported into the database.

These checks are handled in different ways:

- uniqueness of ids: as these ids are not imported into the database, they are checked from outside of the Symfony entities.
- references to other elements: same reasoning, the ids must refer to ids from the file, but not to the ids from the database.
- uniqueness of properties (e.g. organizations' names): the command fails if the data is duplicated within the file, but will reuse the data already present in the database. For instance, if a user with the email `example@example.com` is twice in the file, the command will fail. However, if the email is in the database, the command will load it and ignore the data from the file.
- the few last checks are handled with custom logic.

### The data

The data is stored in a ZIP archive. It contains several files:

- `organizations.json` an array of organizations defined as:
  - id: string (unique)
  - name: string (unique, not empty, max 255 chars)
  - domains: array of strings (each must be unique and valid domains)
- `roles.json` an array of roles defined as:
  - id: string (unique)
  - name: string (unique, not empty, max 50 chars)
  - description: string (not empty, max 255 chars)
  - type: string (must be `super`, `admin`, `agent`, or `user`, `super` must be unique)
  - isDefault: boolean, optional (only one `user` role can be the default role)
  - permissions: array of strings, optional (see `Role::PERMISSIONS` for the list of valid strings)
- `users.json` an array of users defined as:
  - id: string (unique)
  - email: string (unique, not empty, valid email)
  - locale: string, optional (must be `en_GB`, or `fr_FR`)
  - name: string, optional (not empty, max 100 chars)
  - ldapIdentifier: string, optional
  - organizationId: string or null, optional (reference to an organization)
  - authorizations: array of, optional:
    - roleId: string (reference to a role)
    - organizationId: string or null (reference to an organization)
- `teams.json` an array of teams defined as:
  - id: string (unique)
  - name: string (unique, not empty, max 50 chars)
  - isResponsible: boolean, optional
  - teamAuthorizations: array of, optional:
    - roleId: string (reference to a role)
    - organizationId: string or null (reference to an organization)
- `contracts.json` an array of contracts defined as:
  - id: string (unique)
  - name: string (max 255 chars, not empty)
  - startAt: datetime
  - endAt: datetime (greater than startAt)
  - maxHours: integer (number of minutes, greater than 0)
  - notes: string, optional
  - organizationId: string (reference to an organization)
  - timeAccountingUnit: integer, optional (number of minutes, greater than or equal 0)
  - hoursAlert: integer, optional (percent, greater than or equal 0)
  - dateAlert: integer, optional (number of days, greater than or equal 0)
- `labels.json` an array of labels defined as:
  - id: string (unique)
  - name: string (unique, not empty, max 50 chars)
  - description: string, optional (max 250 chars)
  - color: string, optional (must be `grey`, `primary`, `blue`, `green`, `orange`, or `red`)

It also contains a `tickets/` folder where each file corresponds to a ticket. For clarity reasons, the files can be put in sub-folders. Sub-folders have no meaning to the command, but can help to group tickets by organizations for instance. The name of the files doesn't matter, but they have to contain JSON objects:

- id: string (unique)
- createdAt: datetime
- updatedAt: datetime, optional (defaults to createdAt)
- createdById: string (reference to a user)
- type: string, optional (must be `request`, or `incident`)
- status: string, optional (must be `new`, `in_progress`, `planned`, `pending`, `resolved`, or `closed`)
- title: string (max 255 chars, not empty)
- urgency: string, optional (must be `low`, `medium`, or `high`)
- impact: string, optional (must be `low`, `medium`, or `high`)
- priority: string, optional (must be `low`, `medium`, or `high`)
- requesterId: string (reference to a user)
- observerIds: array of strings, optional (references to users)
- teamId: string or null, optional (reference to a team)
- assigneeId: string or null, optional (reference to a user)
- organizationId: string (reference to an organization)
- solutionId: string or null, optional (reference to a message, included in ticket.messages)
- contractIds: array of strings, optional (references to contracts)
- labelIds: array of strings, optional (references to labels)
- timeSpents: array of, optional:
  - createdAt: datetime
  - createdById: string (reference to a user)
  - time: integer (number of minutes, greater than 0)
  - realTime: integer (number of minutes, greater than 0)
  - contractId: string or null, optional (reference to a contract, included in ticket.contracts)
- messages: array of, optional:
  - id: string (unique)
  - createdAt: datetime
  - createdById: string (reference to a user)
  - postedAt: datetime, optional (default to createdAt)
  - isConfidential: boolean, optional
  - via: string, optional (must be `webapp`, or `email`)
  - content: string (not empty, HTML, will be sanitized)
  - notificationsReferences: array of strings
  - messageDocuments: array of, optional:
    - name: string (not empty)
    - filepath: string (not empty, exists under the `documents/` folder)

The ids are not imported, but are used to link elements between each other during the importation process.

Datetimes must be expressed with [RFC 3339](https://www.rfc-editor.org/rfc/rfc3339), e.g. `2024-02-13T10:00:00+02:00` (see also PHP [`DateTimeInterface::RFC3339`](https://www.php.net/manual/fr/class.datetimeinterface.php)).

> [!CAUTION]
> It is important that the datetimes are expressed with the date offset corresponding to the timezone of the server.
> Otherwise, existing data could be duplicated.

A last folder named `documents/` contains the list of documents to import.

A file (or folder) can be missing. In this case, it is considered that there is no corresponding data to import. Be careful though as the references cannot be broken (e.g. if a ticket refers to a user id, the user must exist in the file `users.json`, even though the email already exists in the database).

### Handling existing data

When importing the data, some elements may already exist in the database (e.g. users have been imported from LDAP, or the command failed the first time after importing part of the data).

We can easily detect existing data for organizations, roles, users, teams and labels. Indeed, these entities require the uniqueness of a field (name or email). Thus, if we detect that a corresponding entity already exists (using the unique field), we can load the entity from the database to reuse it.

Contracts and tickets are harder to handle as there is no unique field that could help us to detect existing data. Custom logic can be used though:

- contracts: same name, startAt, endAt and organization
- tickets: same name, createdAt and organization

As the uniqueness of these entities is based on dates, it's important that they are expressed with an offset corresponding to the server's timezone.

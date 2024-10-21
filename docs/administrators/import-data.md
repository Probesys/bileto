# Importing data

Bileto allows you to import data from a ZIP archive.

To do so, run:

```console
www-data$ php bin/console app:data:import /path/to/archive.zip
```

The format of the archive is explained [in a document dedicated to developers.](/docs/developers/import-data.md)
It allows you to import:

- organizations;
- roles;
- users;
- teams;
- contracts;
- labels;
- and tickets with their messages, documents and spent time.

Before importing the data into the database, the command performs some checks to verify the integrity and validity of the files to be imported.
If at least one file is invalid, it fails.

Also, the command detects if the data has already been imported to avoid to duplicate it.
However, it doesn't update data already imported; it just ignores it.

## Trusting documents mimetypes

Users cannot upload some file types.
However, if you import existing data and that you're sure the documents are safe, you can import documents that would not be accepted by Bileto otherwise.
You must pass the `--trust-mimetypes` option to do so.

```console
www-data$ php bin/console app:data:import --trust-mimetypes /path/to/archive.zip
```

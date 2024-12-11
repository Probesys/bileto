# Backup and restore Bileto

There are few things that you should backup when you run Bileto in production.

The database is the main component to backup.

With PostgreSQL:

```console
$ # To create a backup:
$ pg_dump bileto_production > backup-bileto.sql
$ # And to restore:
$ psql bileto_production < backup-bileto.sql
```

With MariaDB:

```console
$ # To create a backup:
$ mariadb-dump bileto_production > backup-bileto.sql
$ # And to restore:
$ mariadb bileto_production < backup-bileto.sql
```

You should also backup two more folders and a file:

- `var/data/` stores an encryption key used to encrypt secrets;
- `var/uploads/` (or any other folder if you changed `APP_UPLOADS_DIRECTORY`) stores the documents uploaded by the users;
- `.env.local` stores the configuration of Bileto.

When you restore Bileto, make sure to set the correct owner of these files:

```console
$ sudo chown -R www-data:www-data .
```

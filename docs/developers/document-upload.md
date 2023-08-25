# Document upload

Bileto allows users to upload documents.
At the moment, documents can only be uploaded with messages.
The decision was made to name all the objects (i.e. controller, service and entity) to reflect this fact.

## The `MessageDocuments` controller

The controller dedicated to the upload of documents is [`MessageDocumentsController`](/src/Controller/MessageDocumentsController.php).

It provides two endpoints:

- `create` allows to upload the document to the server;
- `show` allows to download the document from the server.

The user can upload a document if he's allowed to create messages in any organization.

The document can be downloaded if:

- the user has uploaded the file himself;
- or if he has access to the message related to the document.

## The `MessageDocumentStorage` service

The [`MessageDocumentStorage` service](/src/Service/MessageDocumentStorage.php) establishes the relationship between the file system (where the files are stored) and the database (to associate the file with a message).

It allows to:

- store a file in its correct location and its associated [`MessageDocument`](/src/Entity/MessageDocument.php) to be returned;
- get the size and read the file related to a given `MessageDocument`.

## Documents on the filesystem

Finally, the documents are stored somewhere on the file system.
The location is determined by the `APP_UPLOADS_DIRECTORY` environment variable (see [`env.sample`](/env.sample)).

Then, the sha256 hash of the file is used to get the file name (the extension is the one corresponding to the mime type of the file).
The file is placed in two levels of subdirectories.
The subdirectories are named after the first four characters of the file hash.

For example, considering the JPEG file hash `bd62115afd16249cff5bd9418b3c4fab3a9a254ebcf0e695cb3c14b92d7827f1`, the corresponding file will be saved as `$APP_UPLOADS_DIRECTORY/bd/62/bd62115afd16249cff5bd9418b3c4fab3a9a254ebcf0e695cb3c14b92d7827f1.jpg`.

Using the hash as the file name avoids duplicating the same file more than once.
Storing the files in subdirectories allows a large number of files to be stored while maintaining good performance.

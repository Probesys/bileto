# How to release a version

## Start the release process

There's a `make` command to release a new version of Bileto:

```console
$ git checkout -b release/0.1.0-dev
$ make release VERSION=0.1.0-dev
```

It will write the version number in the file [`VERSION.txt`](/VERSION.txt) and build the assets for production.

## Generate the changelog

Next, the command will open a text editor so you can edit the changelog.

If updating Bileto to the new version requires specific actions, make sure they are listed in a “Migration notes” section.

Next, generate the list of messages to include in the changelog with:

```console
$ git log --first-parent --pretty=format:'%s (%h)' --abbrev-commit $(git describe --abbrev=0 --tags)..
```

You might want to create a git alias in your `.gitconfig`.

Organize the commits in the following sections:

- Security (`sec:` prefix)
- New (`new:` prefix)
- Improvements (`imp:` prefix)
- Bug fixes (`fix:` prefix)
- Misc (other prefixes)

Feel free to rename the commit messages if you think they aren't clear enough (at least to remove the prefixes).
You can also remove messages if they don't bring value to the changelog (that's the purpose of the `misc:` prefix for instance).

Finally, add links to GitHub commits on the hashes (e.g. `[83fdf85](https://github.com/Probesys/bileto/commit/83fdf85)`).

## Complete the release

Once the changelog is complete, close your editor and the command will commit your changes.

Push your branch to GitHub:

```console
$ git push -u origin HEAD
```

And open a pull request.

Once merged, don't forget to push the version tag:

```console
$ git push --tags
```

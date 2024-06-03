# Working with the icons

We use [Font Awesome 6](https://fontawesome.com/v6/download) (Free for Web version).
Icons are copied from the `svgs/` archive folder to `assets/icons/`.
Comments from the file are removed to reduce the size of the final file (the FontAwesome license is still visible in [the LICENSE.txt file](/assets/icons/LICENSE.txt)).

## Use icons

Icons can be displayed in templates via the Twig function `icon()` (see [`IconExtension`](/src/Twig/IconExtension.php)):

```twig
{{ icon('status') }}
```

The parameter is the name of the icon, which corresponds to the name of the icon file without the extension.

You can pass additional classes with the second parameter:

```twig
{{ icon('angle-down', 'icon--rotate90') }}
```

## Build icons

They are then built in a single file (`public/icons.svg`) with the following command:

```console
$ make icons
```

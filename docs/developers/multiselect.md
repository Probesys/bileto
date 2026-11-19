# Working with the multiselect widget

We often need to select multiple options from a `<select>` field.
However, the default browser widget for `<select multiple>` is not intuitive.
So we have created a custom widget that is easier to use.

This widget can be used by setting the `block_prefix` attribute of a Symfony Form `CollectionType` / `EntityType`.
You also have to set a `data-placeholder` attribute on the widget.
This placeholder is only used in the interface as a disabled option.
For instance, to display an "observers" field:

```php
$form->add('observers', AppType\ActorType::class, [
    'multiple' => true,
    'by_reference' => false,
    'required' => false,
    'label' => new TranslatableMessage('tickets.observers'),
    'attr' => [
        'data-placeholder' => $this->translator->trans('forms.multiselect.select_actor'),
    ],
    'block_prefix' => 'multiselect',
]);
```

## Searchable select fields

Set `'autocomplete' => true` on an existing choice field to use Symfony UX Autocomplete.
The ticket contract form uses this without Ajax: authorized ongoing contracts are loaded with the form and searched locally.
It makes the empty "No contract" choice selectable and disables the clear button,
so removing a contract uses the same keyboard controls as selecting one.
Styling lives in `assets/stylesheets/components/autocomplete.css` and is shared by all autocomplete fields.

An existing ongoing contract takes precedence.
Otherwise, the form suggests an active contract from the most recently updated other ticket
with the same organization, requester and assignee.
The suggestion is only saved when the form is submitted.

Install Composer dependencies before npm: the frontend package comes from `vendor/`.
The `.npmrc` setting installs local packages as packed dependencies, without their development dependencies.

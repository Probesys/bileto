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

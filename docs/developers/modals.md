# Working with the modals

## Fetching the content of the modal

The most common way of opening a modal is by fetching its content from a route.

You need a button with the `data-controller="modal-opener"` and all the related attributes, e.g.:

```twig
<button
    data-controller="modal-opener"
    data-action="modal-opener#fetch"
    data-modal-opener-selector-value="{{ path('a route') }}"
>
    Edit
</button>
```

The `data-modal-opener-selector-value` destination view must extend the `modal.html.twig` template, e.g.:

```twig
{% extends 'modal.html.twig' %}

{% block title %}Some modal title{% endblock %}

{% block body %}
    <p>The content of the modal</p>
{% endblock %}
```

## Copying the content from a HTML template

You can also open a modal by copying the content from a `<template>` HTML node.

As for fetching, you need a button with the `data-controller="modal-opener"` (note that `data-action` is different then):

```twig
<button
    data-controller="modal-opener"
    data-action="modal-opener#copy"
    data-modal-opener-selector-value="#my-modal"
>
    Edit
</button>
```

The `<template>` node must be present in the current HTML document and must follow this structure:

```twig
<template id="my-modal">
    <div>
        <h1 id="modal-title" class="modal__title">Some modal title</h1>

        <p>The content of the modal</p>
    </div>
</div>
```

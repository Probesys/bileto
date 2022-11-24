# Working with the modals

## How to open a modal

You need a button with the `data-controller="modal-opener"` and all the related attributes, e.g.:

```twig
<button
    data-controller="modal-opener"
    data-action="modal-opener#fetch"
    data-modal-opener-href-value="{{ path('a route') }}"
    aria-haspopup="dialog"
    aria-controls="modal"
>
    Edit
</button>
```

The `data-modal-opener-href-value` destination view must extend the `modal.html.twig` template, e.g.:

```twig
{% extends 'modal.html.twig' %}

{% block title %}Some modal title{% endblock %}

{% block body %}
    <p>The content of the modal</p>
{% endblock %}
```

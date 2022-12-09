# Working with popups

The HTML of popups is based on the [`<details>`](https://developer.mozilla.org/fr/docs/Web/HTML/Element/details) element, with a pinch of CSS and a Stimulus controller:

```twig
<details
    class="popup"
    data-controller="popup"
    data-action="toggle->popup#update click@window->popup#closeOnClickOutside"
>
    <summary class="popup__opener">
        <span class="button">
            {{ 'Actions' | trans }}
            {{ icon('caret-down') }}
        </span>
    </summary>

    <nav class="popup__container popup__container--left">
    </nav>
</details>
```

Note the button in the `<summary>` element should not be a real `<button>` (only a class).
It would take the focus and catch click events otherwise.

The nav element can have different kind of children.

A basic link:

```twig
<a class="popup__item" href="{{ path('preferences') }}" role="menuitem">
    {{ 'Preferences' | trans }}
</a>
```

A button to open a modal:

```twig
<button
    class="popup__item"
    type="button"
    data-controller="modal-opener"
    data-action="modal-opener#fetch"
    data-modal-opener-href-value="{{ path('edit ticket title', { uid: ticket.uid }) }}"
>
    {{ 'Rename the ticket' | trans }}
</button>
```

A form to perform an action:

```twig
<form action="{{ path('logout') }}" method="post">
    <input type="hidden" name="_csrf_token" value="{{ csrf_token('logout') }}">
    <button class="popup__item" type="submit">
        {{ 'Logout' | trans }}
    </button>
</form>
```

# Working with popups

## Basic usage

The HTML of popups is based on the [`<details>`](https://developer.mozilla.org/fr/docs/Web/HTML/Element/details) element, with a pinch of CSS and a Stimulus controller:

```twig
<details
    class="popup"
    data-controller="popup"
    data-action="toggle->popup#update click@window->popup#closeOnClickOutside"
>
    <summary class="popup__opener">
        <span class="button button--carret">
            {{ 'Actions' | trans }}
        </span>
    </summary>

    <nav class="popup__container popup__container--middle">
    </nav>
</details>
```

Note the button in the `<summary>` element should not be a real `<button>` (only a class).
It would take the focus and catch click events otherwise.

Don’t forget to use the `.button--carret` class to put a carret on the right of the button.
This shows to the user that the button will open a popup.

## Menu elements

The menu can have different kind of children.

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

Radio buttons to select an option:

```twig
<div>
    <input
        id="status-pending"
        type="radio"
        name="status"
        value="pending"
    />

    <label class="popup__item" for="status-pending">
        {{ 'tickets.status.pending' | trans }}
    </label>
</div>
```

## Menu position

You can align the menu with the button either on the right:

```html
<nav class="popup__container popup__container--right">
</nav>
```

On the left:

```html
<nav class="popup__container popup__container--left">
</nav>
```

On the top:

```html
<nav class="popup__container popup__container--top">
</nav>
```

Or centered:

```html
<nav class="popup__container popup__container--center">
</nav>
```

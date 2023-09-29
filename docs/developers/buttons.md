# Working with the buttons

Buttons elements (`<button>`) are styled by default.

To apply a button style to a different element, you can add the `.button` class to this element.
Be careful about accessibility though!
[Learn more about the ARIA button role.](https://developer.mozilla.org/en-US/docs/Web/Accessibility/ARIA/Roles/button_role)

## Primary button

You can style a button as primary:

```html
<button class="button--primary" type="submit">
    Save changes
</button>
```

As far as possible, you should avoid adding multiple primary buttons on a single page.

## Icon button

Sometimes, a button contains a single icon without text (you must add a `.sr-only` text for screen readers though).
You can adapt this button to be completely rounded with the correct class:

```twig
<button class="button--icon">
    {{ icon('gear') }}

    <span class="sr-only">
        Configure the priority
    </span>
</button>
```

## Discreet button

Some buttons have small importance.
Such button can be made smaller to attract less attention:

```html
<button class="button--discreet">
    Go to bottom
</button>
```

## Anchor button

It is possible to style buttons as links.
**This is generally not recommended as such buttons offer bad affordance.**
Sometimes, it can be useful to keep the interface elegant though.

```html
<button class="button--anchor">
    Show the advanced syntax
</button>
```

## Button with caret

You can add a caret to buttons opening a popup menu.
Read about it in “[Working with popups](/docs/developers/popups.md)”.

## Button caption

You can add a small caption to buttons in order to provide additional context to the button:

```html
<button>
    <span class="button__caption">
        Status
    </span>

    Opened
</button>
```

## Buttons group

You can group buttons that complement each other.
It is useful to add secondary actions to a button in a popover for instance.
Note that you must use an additional `.button-group__item` class on the buttons that are part of the group.

```twig
<div class="button-group">
    <button class="button-group__item" type="submit">
        Answer
    </button>

    <details class="popup">
        <summary class="popup__opener">
            <span class="button button-group__item">
                {{ icon('caret-down') }}

                <span class="sr-only">
                    Select a different method to answer.
                </span>
            </span>
        </summary>

        <nav class="popup__container">
            <!-- ... -->
        </nav>
    </details>
</div>
```

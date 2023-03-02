# Working with rows and grids

It may be challenging to organize the layout of an application.
Bileto provides two ways to organize the elements: rows and grids.
Rows are what you'll need in most cases to align elements (they use [flexbox](https://developer.mozilla.org/docs/Web/CSS/CSS_Flexible_Box_Layout/Basic_Concepts_of_Flexbox)).
Grids allow specific things such as organizing elements in 2 dimensions (they use [grid layout](https://developer.mozilla.org/docs/Web/CSS/CSS_Grid_Layout/Basic_Concepts_of_Grid_Layout)).

## Rows

If you need to put elements on a single row, you can use the `.row` class:

```html
<div class="row">
    <div>Item 1</div>
    <div>Item 2</div>
</div>
```

On mobile, rows items stack themselves.
You can force them to stay always in line with `.row--always`:

```html
<div class="row row--always">
    <div>Item 1</div>
    <div>Item 2</div>
</div>
```

Sometimes, a row item is higher than its neighbours and they align badly.
If you need to center the items, use `.row--center`:

```html
<div class="row row--center">
    <div>
        A<br>
        Higher<br>
        Item
    </div>
    <div>Item 2</div>
</div>
```

You can center all the items in their container with `.row--middle`:

```html
<div class="row row--middle">
    <div>Item 1</div>
    <div>Item 2</div>
</div>
```

To add space between row items, just use one of the `.flow*` classes:

```html
<div class="row flow">
    <div>Item 1</div>
    <div>Item 2</div>
</div>
```

You can tell a row item to take all the remaining space with `.row__item--extend`:

```html
<div class="row">
    <div class="row__item--extend">Item 1 extends to take the remaining space</div>
    <div>Item 2</div>
</div>
```

On the other way, you may want an item to not shrink so it keeps enough space for its content.
You can use `.row__item--noshrink` for that:

```html
<div class="row">
    <div class="row__item--noshrink">Item 1 will not shrink</div>
    <div>Item 2</div>
</div>
```

## Grids

If you need to organize the elements of your layout in two dimensions, you’ll need `.grid`:

```html
<div class="grid">
    <div>Item 1</div>
    <div>Item 2</div>
    <div>Item 3</div>
    <div>Item 4</div>
    <div>Item 5</div>
    <div>Item 6</div>
</div>
```

In this example, items will align themselves in a row until there's not enough space.
Then, they'll be placed on another row.

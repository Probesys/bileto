# Working with columns and grids

It may be challenging to organize the layout of an application.
Bileto provides two ways to organize the elements: cols and grids.
Cols are what you'll need in most cases to align elements (they use [flexbox](https://developer.mozilla.org/docs/Web/CSS/CSS_Flexible_Box_Layout/Basic_Concepts_of_Flexbox)).
Grids allow specific things such as organizing elements in 2 dimensions (they use [grid layout](https://developer.mozilla.org/docs/Web/CSS/CSS_Grid_Layout/Basic_Concepts_of_Grid_Layout)).

## Cols

If you need to put elements on a single line, you can use the `.cols` class:

```html
<div class="cols">
    <div>Item 1</div>
    <div>Item 2</div>
</div>
```

On mobile, columns stack themselves.
You can force them to stay always in line with `.cols--always`:

```html
<div class="cols cols--always">
    <div>Item 1</div>
    <div>Item 2</div>
</div>
```

Sometimes, a column is higher than its neighbours and they align badly.
If you need to center the columns, use `.cols--center`:

```html
<div class="cols cols--center">
    <div>
        A<br>
        Higher<br>
        Item
    </div>
    <div>Item 2</div>
</div>
```

Or if you need to align on their baseline, use `.cols--baseline`:

```html
<div class="cols cols--baseline">
    <div>
        A<br>
        Higher<br>
        Item
    </div>
    <div>Item 2</div>
</div>
```

To add space between columns, just use the `.flow*` classes:

```html
<div class="cols flow">
    <div>Item 1</div>
    <div>Item 2</div>
</div>
```

You can tell a column to take all the remaining space with `.col--extend`:

```html
<div class="cols">
    <div class="col--extend">Item 1 extends to take the remaining space</div>
    <div>Item 2</div>
</div>
```

On the other way, you may want a column to not shrink so it keeps enough space for its content.
You can use `.col--noshrink` for that:

```html
<div class="cols">
    <div class="col--noshrink">Item 1 will not shrink</div>
    <div>Item 2</div>
</div>
```

For more control on the size of the items, you can use the `.col--size*` classes.
They allow to create a layout of 12 columns.
For instance:

```html
<div class="cols">
    <div class="col--size3">Item 1</div>
    <div class="col--size6">Item 2</div>
    <div class="col--size3">Item 3</div>
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

In this example, items will align themselves on a line until there's not enough space.
Then, they'll be placed on another line.

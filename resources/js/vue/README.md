# Vue tables

`Table` and `InertiaTableWrapper` export the same Vue component.

For tables embedded in profiles, dashboards, or related-record sections, hide the toolbar with `showToolbar`:

```vue
<InertiaTableWrapper :resource="table" :show-toolbar="false" />
```

This hides search, filters, saved views, exports, column settings, and view tabs. Rows, sorting, row actions, pagination, and real bulk actions remain available. Column hiding and pinning are disabled so columns cannot be hidden without a way to restore them. Export-only selection checkboxes are also hidden.

The default is `true`, preserving the complete toolbar on listing pages. Set it to `true` on an embedded table whenever these controls are useful.

Row actions are permission-aware: one visible action renders as a button; two or more render in a dropdown. Hidden or unauthorized actions do not affect that choice.

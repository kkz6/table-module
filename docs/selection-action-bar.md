# Selection action bar

Both table renderers use `SelectionActionBar` when one or more rows are selected.
It replaces the bulk Actions menu without adding table-specific frontend rules.
Exports remain in the header menu when nothing is selected.

## Inputs and eligibility

- `actions`, `exports`, and `rows`: existing serialized table definitions and results.
- `selectedItems`, `total`: selection keys and the number of matching results.
- `busy`, `onClear`: processing state and selection reset.
- Existing action, export, icon and result callbacks are forwarded to `TableActions`.

One explicit selection exposes row actions (including Edit links and downloads).
Multiple selections expose bulk actions. Original action indexes are preserved,
and `_actions` hidden/disabled states exclude ineligible records. Mixed selections
display an eligible count on affected buttons and submit only those keys.
Unauthorized actions are omitted. Server-side authorization and disabled checks
remain authoritative.

Selecting all uses the existing `*` contract: actions apply to eligible items
across all matching pages, respecting search and filters. Off-page eligibility
cannot be inferred from the visible page, so bulk actions remain available when
there are unexamined rows. Changing table state clears the previous selection.

## Appearance and interaction

The bar floats at the bottom of the viewport. Shared popover, border and text
tokens support light/dark themes. Shared outline buttons carry icon + text labels;
destructive actions use the quiet destructive variant and existing confirmation
dialogs. Buttons wrap and the bar scrolls within a bounded height on small screens.
The table reserves bottom space so the bar does not cover its final rows.
On first selection, it slides up 1.5rem and fades in over 200ms with ease-out.
Changing the selection count does not replay the entrance. Reduced-motion users
see the bar immediately without animation.

The region is labelled “Selected item actions”; selection/processing changes are
announced through a status element. Native buttons support Tab and Enter/Space,
and dialogs retain their existing focus trapping and Escape behavior. Selecting a
row does not move focus. Clear selection has an explicit accessible label.
While processing, actions and Clear are disabled, with a spinner and busy state.

No actions execute merely from selecting rows. Destructive confirmations must not
be bypassed. Do not hard-code content types, statuses or action labels in the bar.

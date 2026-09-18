/** Filters such as Trash are meaningful without a comparison value. */
export function filterQuery(filter: { clause?: string; value?: unknown }): { clause?: string; value?: unknown } | null {
    const withoutValue = ['is_true', 'is_false', 'is_set', 'is_not_set', 'with_trashed', 'only_trashed', 'without_trashed'];

    if (!withoutValue.includes(filter.clause ?? '') && (filter.value == null || filter.value === '')) {
        return null;
    }

    return { clause: filter.clause, value: filter.value };
}

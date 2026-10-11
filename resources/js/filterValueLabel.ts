/** Readable chip values retain the meaning of non-equality operators. */
export function filterValueLabel(filter: { type: string }, state: { clause?: string; value?: unknown }, value: string): string {
    const clause = state.clause ?? 'equals';
    const special: Record<string, string> = {
        is_set: 'Set',
        is_not_set: 'Not set',
        is_null: 'Empty',
        is_not_null: 'Not empty',
        is_true: 'Yes',
        is_false: 'No',
        with_trashed: 'Include archived',
        only_trashed: 'Archived only',
        without_trashed: 'Active only',
    };
    if (special[clause]) return special[clause];
    if (filter.type === 'boolean') return state.value === false || state.value === 0 || state.value === '0' ? 'No' : 'Yes';
    const prefixes: Record<string, string> = {
        not_equals: 'Not',
        not_in: 'Exclude',
        contains: 'Contains',
        not_contains: 'Does not contain',
        starts_with: 'Starts with',
        not_starts_with: 'Does not start with',
        ends_with: 'Ends with',
        not_ends_with: 'Does not end with',
        greater_than: 'Above',
        greater_than_or_equal: 'At least',
        less_than: 'Below',
        less_than_or_equal: 'At most',
        between: 'Between',
        not_between: 'Outside',
        before: 'Before',
        after: 'After',
        equal_or_before: 'On or before',
        equal_or_after: 'On or after',
        '!=': 'Not',
        '>': 'Above',
        '>=': 'At least',
        '<': 'Below',
        '<=': 'At most',
    };
    return prefixes[clause] ? `${prefixes[clause]} ${value}` : value || 'Choose value';
}

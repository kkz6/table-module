import type { TableState, TableTab } from './types';

export function activeTableTab(tabs: TableTab[], state: TableState): string {
    return (
        tabs.find((tab) =>
            Object.entries(tab.filters).every(([attribute, preset]) => {
                const current = state.filters[attribute];
                if (preset === null) return !current?.enabled;
                const expected =
                    typeof preset === 'object' && preset !== null && 'value' in preset
                        ? (preset as { value: unknown; clause?: string })
                        : { value: preset, clause: 'equals' };
                return (
                    !!current?.enabled &&
                    current.clause === (expected.clause ?? 'equals') &&
                    (Array.isArray(current.value) || Array.isArray(expected.value)
                        ? JSON.stringify(current.value) === JSON.stringify(expected.value)
                        : String(current.value) === String(expected.value))
                );
            }),
        )?.value ?? ''
    );
}

/** Only filters represented by the selected tab can be omitted from the URL/chips. */
export function tabFilterAttributes(tabs: TableTab[], state: TableState): string[] {
    const active = tabs.find((tab) => tab.value === activeTableTab(tabs, state));
    return active ? Object.keys(active.filters) : [];
}

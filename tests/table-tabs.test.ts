import assert from 'node:assert/strict';
import test from 'node:test';
import { activeTableTab, tabFilterAttributes } from '../resources/js/tableTabs.ts';
import type { TableState, TableTab } from '../resources/js/types';
const tabs = [
    { value: 'all', filters: { status: null } },
    { value: 'draft', filters: { status: 'draft' } },
] as TableTab[];
const state = (filter: object) => ({ filters: { status: filter, currency: { enabled: true, clause: 'equals', value: 'USD' } } }) as TableState;
test('the selected tab replaces only its own filter in chips and URL', () => {
    const selected = state({ enabled: true, clause: 'equals', value: 'draft' });
    assert.equal(activeTableTab(tabs, selected), 'draft');
    assert.deepEqual(tabFilterAttributes(tabs, selected), ['status']);
});
test('legacy custom operators stay visible instead of being hidden by tabs', () => {
    const custom = state({ enabled: true, clause: 'not_equals', value: 'draft' });
    assert.equal(activeTableTab(tabs, custom), '');
    assert.deepEqual(tabFilterAttributes(tabs, custom), []);
});
test('all disables the preset and tables without tabs manage no filters', () => {
    const all = state({ enabled: false });
    assert.equal(activeTableTab(tabs, all), 'all');
    assert.deepEqual(tabFilterAttributes([], all), []);
});

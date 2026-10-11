import assert from 'node:assert/strict';
import test from 'node:test';
import { filterQuery } from '../resources/js/filterQuery.ts';
test('unfinished selections are not serialized as active server filters', () => {
    assert.equal(filterQuery({ clause: 'in', value: [] }), null);
    assert.equal(filterQuery({ clause: 'between', value: [0, null] }), null);
    assert.deepEqual(filterQuery({ clause: 'between', value: [0, 10] }), { clause: 'between', value: [0, 10] });
    assert.deepEqual(filterQuery({ clause: 'only_trashed', value: null }), { clause: 'only_trashed', value: null });
});

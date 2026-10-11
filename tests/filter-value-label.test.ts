import assert from 'node:assert/strict';
import test from 'node:test';
import { filterValueLabel } from '../resources/js/filterValueLabel.ts';
test('simple filters show plain values while other operators keep their meaning',()=>{
 assert.equal(filterValueLabel({type:'set'},{clause:'equals',value:'active'},'Active'),'Active');
 assert.equal(filterValueLabel({type:'set'},{clause:'not_equals',value:'active'},'Active'),'Not Active');
 assert.equal(filterValueLabel({type:'numeric'},{clause:'greater_than_or_equal',value:0},'0'),'At least 0');
 assert.equal(filterValueLabel({type:'date'},{clause:'before',value:'2026-10-11'},'2026-10-11'),'Before 2026-10-11');
});
test('boolean and archive filters use readable states',()=>{
 assert.equal(filterValueLabel({type:'boolean'},{clause:'equals',value:false},'false'),'No');
 assert.equal(filterValueLabel({type:'boolean'},{clause:'equals',value:0},'0'),'No');
 assert.equal(filterValueLabel({type:'boolean'},{clause:'is_true'},''),'Yes');
 assert.equal(filterValueLabel({type:'trashed'},{clause:'only_trashed'},''),'Archived only');
});

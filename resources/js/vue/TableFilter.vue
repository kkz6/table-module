<script setup lang="ts">
import {
    Button,
    DateTimePicker,
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuTrigger,
    Input,
    NumberInput,
    Select,
} from '@workspace/ui';
import { ChevronDown } from 'lucide-vue-next';
import { computed } from 'vue';
import type { FilterDefinition, FilterState } from './types';
const props = defineProps<{ filter: FilterDefinition; state: FilterState }>();
const emit = defineEmits<{ change: [clause: string, value: unknown]; remove: [] }>();
const clause = computed(() => props.state.clause || props.filter.clauses?.[0] || 'equals');
const noValue = (value: string) => ['is_true', 'is_false', 'is_set', 'is_not_set', 'with_trashed', 'only_trashed', 'without_trashed'].includes(value);
const isRange = (value: string) => ['between', 'not_between'].includes(value);
const isSet = (value: string) => ['in', 'not_in'].includes(value) || !!props.filter.multiple;
const values = computed(() =>
    Array.isArray(props.state.value) ? props.state.value : props.state.value == null || props.state.value === '' ? [] : [props.state.value],
);
const inputType = computed(() => (props.filter.type === 'numeric' ? 'number' : 'text'));
function changeClause(value: string): void {
    const next = noValue(value) ? null : isRange(value) ? ['', ''] : isSet(value) ? values.value : (values.value[0] ?? '');
    emit('change', value, next);
}
function toggle(value: string | number): void {
    const current = values.value.map(String);
    const next = current.includes(String(value)) ? current.filter((item) => item !== String(value)) : [...current, String(value)];
    if (next.length) emit('change', clause.value, next);
    else emit('remove');
}
function range(index: number, value: string | number | null): void {
    const next: unknown[] = [values.value[0] ?? '', values.value[1] ?? ''];
    next[index] = value;
    emit('change', clause.value, next);
}
</script>
<template>
    <Select
        v-if="(filter.clauses?.length || 0) > 1"
        class="w-40"
        :aria-label="`${filter.label} operator`"
        :model-value="clause"
        :options="(filter.clauses || []).map((value) => ({ value, label: value.replaceAll('_', ' ') }))"
        @update:model-value="(value) => changeClause(String(value))"
    />
    <template v-if="!noValue(clause)">
        <DropdownMenu v-if="filter.options?.length && isSet(clause)">
            <DropdownMenuTrigger as-child>
                <Button
                    variant="outline"
                    :aria-label="`Filter ${filter.label}`"
                >
                    {{ values.length ? `${values.length} selected` : 'Select values' }}
                    <ChevronDown class="size-4" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent class="max-h-72 overflow-auto">
                <DropdownMenuCheckboxItem
                    v-for="option in filter.options"
                    :key="option.value"
                    :model-value="values.map(String).includes(String(option.value))"
                    @update:model-value="toggle(option.value)"
                    @select.prevent
                >
                    {{ option.label }}
                </DropdownMenuCheckboxItem>
            </DropdownMenuContent>
        </DropdownMenu>
        <Select
            v-else-if="filter.options?.length"
            class="w-40"
            :aria-label="`Filter ${filter.label}`"
            :model-value="String(state.value ?? '')"
            :options="filter.options.map((option) => ({ value: String(option.value), label: option.label }))"
            @update:model-value="(value) => emit('change', clause, value)"
        />
        <template v-else-if="filter.type === 'date'">
            <template v-if="isRange(clause)">
                <DateTimePicker
                    v-for="index in [0, 1]"
                    :key="index"
                    class="w-40"
                    date-only
                    clearable
                    :label="`${filter.label} ${index ? 'end' : 'start'}`"
                    :model-value="String(values[index] ?? '')"
                    @update:model-value="(value) => range(index, value)"
                />
            </template>
            <DateTimePicker
                v-else
                class="w-40"
                date-only
                clearable
                :label="`Filter ${filter.label}`"
                :model-value="String(state.value ?? '')"
                @update:model-value="(value) => emit('change', clause, value)"
            />
        </template>
        <template v-else-if="isRange(clause)">
            <component
                :is="filter.type === 'numeric' ? NumberInput : Input"
                v-for="index in [0, 1]"
                :key="index"
                :allow-negative="filter.type === 'numeric'"
                class="w-40"
                :type="inputType"
                :aria-label="`${filter.label} ${index ? 'end' : 'start'}`"
                :model-value="String(values[index] ?? '')"
                @change="(event: Event) => range(index, (event.target as HTMLInputElement).value)"
            />
        </template>
        <component
            :is="filter.type === 'numeric' ? NumberInput : Input"
            v-else
            :allow-negative="filter.type === 'numeric'"
            class="w-40"
            :type="inputType"
            :aria-label="`Filter ${filter.label}`"
            :model-value="String(state.value ?? '')"
            @change="(event: Event) => emit('change', clause, (event.target as HTMLInputElement).value)"
        />
    </template>
</template>

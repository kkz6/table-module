<script setup lang="ts">
import FormDialog from '@workspace/FormDialog.vue';
import { Button, Checkbox, Input, Label, Select } from '@workspace/ui';
import { ArrowDown, ArrowUp, Loader2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import type { ExportPayload, TableExport } from './types';
const props = defineProps<{ resource?: TableExport; visible: Record<string, boolean>; busy?: boolean; error?: string }>();
const emit = defineEmits<{ close: []; submit: [payload: ExportPayload] }>();
const order = ref<string[]>([]),
    columnMap = ref<NonNullable<ExportPayload['columnMap']>>({}),
    formats = ref<string[]>([]),
    options = ref<Record<string, unknown>>({});
const columns = computed(() => props.resource?.columns ?? []);
watch(
    () => props.resource,
    (value) => {
        order.value = (value?.columns ?? []).map((column) => column.name);
        columnMap.value = Object.fromEntries(
            (value?.columns ?? []).map((column) => [
                column.name,
                {
                    isEnabled: column.enabledByDefault && (!value?.enableVisibleTableColumnsByDefault || props.visible[column.name] !== false),
                    label: column.label,
                },
            ]),
        );
        formats.value = [...(value?.formats ?? ['csv'])];
        options.value = Object.fromEntries(
            (value?.optionsForm ?? []).map((field) => [field.name, field.default ?? (field.type === 'checkbox' ? false : '')]),
        );
    },
    { immediate: true },
);
const valid = computed(
    () =>
        formats.value.length > 0 &&
        (props.resource?.hasColumnMapping === false || !columns.value.length || Object.values(columnMap.value).some((column) => column.isEnabled)),
);
function move(index: number, delta: number): void {
    const next = [...order.value];
    [next[index], next[index + delta]] = [next[index + delta], next[index]];
    order.value = next;
}
function format(value: string): void {
    formats.value = formats.value.includes(value) ? formats.value.filter((item) => item !== value) : [...formats.value, value];
}
function submit(): void {
    if (!valid.value || props.busy) return;
    emit('submit', {
        columnMap: Object.fromEntries(order.value.map((name) => [name, columnMap.value[name]])),
        formats: formats.value,
        options: options.value,
    });
}
</script>
<template>
    <FormDialog
        :open="!!resource"
        :title="resource?.label || 'Export records'"
        description="Choose the columns, order, file formats, and export options."
        :busy="busy"
        @close="emit('close')"
        @submit="submit"
    >
        <p
            v-if="error"
            role="alert"
            class="text-destructive text-sm"
        >
            {{ error }}
        </p>
        <div
            v-if="busy"
            class="flex items-center gap-3 py-5"
            role="status"
        >
            <Loader2 class="size-5 animate-spin" />
            Preparing your export…
        </div>
        <template v-else>
            <div
                v-if="columns.length && resource?.hasColumnMapping !== false"
                class="space-y-3"
            >
                <div class="flex items-center justify-between">
                    <Label>Columns</Label>
                    <div class="flex gap-1">
                        <Button
                            variant="ghost"
                            size="sm"
                            @click="Object.values(columnMap).forEach((column) => (column.isEnabled = true))"
                        >
                            Select all
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            @click="Object.values(columnMap).forEach((column) => (column.isEnabled = false))"
                        >
                            Clear
                        </Button>
                    </div>
                </div>
                <div class="max-h-64 overflow-y-auto rounded-md border">
                    <div
                        v-for="(name, index) in order"
                        :key="name"
                        class="flex items-center gap-2 border-b p-2 last:border-0"
                    >
                        <Checkbox
                            v-model="columnMap[name].isEnabled"
                            :aria-label="`Include ${columns.find((column) => column.name === name)?.label}`"
                        />
                        <Input
                            v-model="columnMap[name].label"
                            :aria-label="`Export label for ${columns.find((column) => column.name === name)?.label}`"
                            :disabled="!columnMap[name].isEnabled"
                        />
                        <Button
                            variant="ghost"
                            size="icon"
                            :disabled="index === 0"
                            :aria-label="`Move ${name} up`"
                            @click="move(index, -1)"
                        >
                            <ArrowUp class="size-3" />
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon"
                            :disabled="index === order.length - 1"
                            :aria-label="`Move ${name} down`"
                            @click="move(index, 1)"
                        >
                            <ArrowDown class="size-3" />
                        </Button>
                    </div>
                </div>
            </div>
            <fieldset class="space-y-2">
                <legend class="mb-2 text-sm font-medium">File formats</legend>
                <Label
                    v-for="value in resource?.formats ?? ['csv']"
                    :key="value"
                    class="flex items-center gap-2"
                >
                    <Checkbox
                        :model-value="formats.includes(value)"
                        @update:model-value="format(value)"
                    />
                    {{ value.toUpperCase() }}
                </Label>
                <p
                    v-if="formats.length > 1"
                    class="text-muted-foreground text-xs"
                >
                    Multiple formats download together in a ZIP file.
                </p>
            </fieldset>
            <div
                v-for="field in resource?.optionsForm ?? []"
                :key="field.name"
                class="space-y-2"
            >
                <Label :for="`export-option-${field.name}`">{{ field.label }}</Label>
                <Checkbox
                    v-if="field.type === 'checkbox'"
                    :id="`export-option-${field.name}`"
                    :model-value="!!options[field.name]"
                    @update:model-value="(value) => (options[field.name] = !!value)"
                />
                <Select
                    v-else-if="field.type === 'select'"
                    :id="`export-option-${field.name}`"
                    :model-value="String(options[field.name] ?? '')"
                    :options="Object.entries(field.options ?? {}).map(([value, label]) => ({ value, label }))"
                    @update:model-value="(value) => (options[field.name] = value)"
                />
                <Input
                    v-else
                    :id="`export-option-${field.name}`"
                    :model-value="String(options[field.name] ?? '')"
                    @update:model-value="(value) => (options[field.name] = value)"
                />
            </div>
        </template>
        <template #footer>
            <Button
                type="submit"
                :disabled="busy || !valid"
            >
                {{ busy ? 'Exporting…' : 'Download export' }}
            </Button>
        </template>
    </FormDialog>
</template>

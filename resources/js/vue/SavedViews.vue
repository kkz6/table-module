<script setup lang="ts">
import FormDialog from '@workspace/FormDialog.vue';
import { Button, Input, Label, Popover, Select } from '@workspace/ui';
import axios from 'axios';
import { Bookmark, BookmarkPlus, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import type { Key, SavedViews, TableState } from './types';
const props = defineProps<{ resource: SavedViews; name: string; busy?: boolean }>();
const emit = defineEmits<{ apply: [state: TableState] }>();
const views = ref(props.resource),
    selected = ref('__current'),
    open = ref(false),
    deleting = ref(false),
    saving = ref(false),
    title = ref(''),
    error = ref(''),
    menuOpen = ref(false);
watch(
    () => props.resource,
    (value) => (views.value = value),
);
const current = computed(() => views.value.data.find((view) => String(view.id) === selected.value));
function apply(id: string): void {
    selected.value = id;
    const view = current.value;
    if (view) {
        menuOpen.value = false;
        emit('apply', view.state);
    }
}
async function save(): Promise<void> {
    if (!title.value.trim()) return;
    saving.value = true;
    error.value = '';
    try {
        const { data } = await axios.post(views.value.storeUrl, { name: props.name, title: title.value.trim(), query: views.value.query });
        views.value = data;
        selected.value = String(data.data.find((view: { id: Key; title: string }) => view.title === title.value.trim())?.id ?? '__current');
        open.value = false;
        title.value = '';
    } catch (cause) {
        error.value = axios.isAxiosError(cause)
            ? (cause.response?.data?.errors?.title?.[0] ?? cause.response?.data?.message ?? 'Could not save the view.')
            : 'Could not save the view.';
    } finally {
        saving.value = false;
    }
}
async function remove(): Promise<void> {
    if (!current.value) return;
    saving.value = true;
    error.value = '';
    try {
        const { data } = await axios.delete(current.value.deleteUrl);
        views.value = data;
        selected.value = '__current';
        deleting.value = false;
    } catch {
        error.value = 'Could not delete the view.';
    } finally {
        saving.value = false;
    }
}
</script>
<template>
    <Popover
        v-model:open="menuOpen"
        align="end"
    >
        <template #trigger>
            <Button
                variant="outline"
                size="icon"
                aria-label="Saved table views"
                title="Saved views"
                :disabled="busy"
            >
                <Bookmark class="size-4" />
            </Button>
        </template>
        <div class="space-y-3">
            <p class="text-sm font-medium">Saved views</p>
            <Select
                class="w-full"
                aria-label="Saved table view"
                :model-value="selected"
                :disabled="busy || saving"
                :options="[
                    { value: '__current', label: 'Current view' },
                    ...views.data.map((view) => ({ value: String(view.id), label: view.title })),
                ]"
                @update:model-value="(value) => apply(String(value))"
            />
            <div class="flex gap-2">
                <Button
                    variant="outline"
                    class="flex-1"
                    :disabled="busy || saving"
                    aria-label="Save table view"
                    @click="
                        menuOpen = false;
                        open = true;
                        error = '';
                    "
                >
                    <BookmarkPlus class="size-4" />
                    Save view
                </Button>
                <Button
                    v-if="current"
                    variant="ghost"
                    size="icon"
                    aria-label="Delete saved view"
                    @click="
                        menuOpen = false;
                        deleting = true;
                        error = '';
                    "
                >
                    <Trash2 class="size-4" />
                </Button>
            </div>
        </div>
    </Popover>
    <FormDialog
        :open="open"
        title="Save table view"
        description="Save your current search, filters, sorting, and columns."
        :busy="saving"
        @close="open = false"
        @submit="save"
    >
        <Label for="table-view-title">View name</Label>
        <Input
            id="table-view-title"
            v-model="title"
            required
            maxlength="255"
            autofocus
        />
        <p
            v-if="error"
            role="alert"
            class="text-destructive text-sm"
        >
            {{ error }}
        </p>
        <template #footer>
            <Button
                type="submit"
                :disabled="saving || !title.trim()"
            >
                Save view
            </Button>
        </template>
    </FormDialog>
    <FormDialog
        :open="deleting"
        title="Delete saved view?"
        :description="`Delete ${current?.title ?? 'this view'}? Your records will be kept.`"
        :busy="saving"
        @close="deleting = false"
        @submit="remove"
    >
        <p
            v-if="error"
            role="alert"
            class="text-destructive text-sm"
        >
            {{ error }}
        </p>
        <template #footer>
            <Button
                type="submit"
                variant="destructive"
                :disabled="saving"
            >
                Delete view
            </Button>
        </template>
    </FormDialog>
</template>

<script setup lang="ts">
import {
    Archive,
    ArrowDown,
    ArrowRight,
    ArrowUp,
    Check,
    CheckCircle,
    ChevronDown,
    Copy,
    Download,
    Eye,
    FileText,
    Loader2,
    Mail,
    MoreHorizontal,
    Pencil,
    Plus,
    RefreshCw,
    Send,
    Settings,
    Trash2,
    X,
    XCircle,
} from 'lucide-vue-next';
import { computed, type Component } from 'vue';
const props = defineProps<{ name?: string | null; resolver?: (name: string) => Component | undefined }>();
const icons: Record<string, Component> = {
    Archive,
    ArrowDown,
    ArrowRight,
    ArrowUp,
    Check,
    CheckCircle,
    ChevronDown,
    Copy,
    Download,
    Eye,
    FileText,
    Loader2,
    Mail,
    MoreHorizontal,
    Pencil,
    Plus,
    RefreshCw,
    Send,
    Settings,
    Trash2,
    X,
    XCircle,
};
const component = computed(() => props.resolver?.(props.name ?? '') ?? icons[props.name ?? ''] ?? MoreHorizontal);
</script>
<template>
    <component
        :is="component"
        aria-hidden="true"
    />
</template>

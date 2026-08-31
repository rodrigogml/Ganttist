<script setup lang="ts">
import { computed } from 'vue'
import { ComboboxAnchor, ComboboxContent, ComboboxEmpty, ComboboxInput, ComboboxItem, ComboboxItemIndicator, ComboboxPortal, ComboboxRoot, ComboboxTrigger, ComboboxViewport } from 'reka-ui'

export interface AppComboboxOption { id: string; label: string; description?: string | null; disabled?: boolean; depth?: number }
const props = withDefaults(defineProps<{ modelValue: string | null | undefined; options: readonly AppComboboxOption[]; placeholder?: string; emptyLabel?: string; allowEmpty?: boolean; emptyMessage?: string }>(), { placeholder: 'Buscar…', emptyLabel: 'Nenhuma seleção', allowEmpty: false, emptyMessage: 'Nenhuma opção encontrada.' })
const emit = defineEmits<{ 'update:modelValue': [value: string | null] }>()
const value = computed<string | null>({ get: () => props.modelValue ?? null, set: (next) => emit('update:modelValue', next ?? null) })
const displayValue = (id: string | null | undefined) => id === null || id === undefined ? (props.allowEmpty ? props.emptyLabel : '') : props.options.find((option) => option.id === id)?.label ?? ''
</script>

<template>
  <ComboboxRoot v-model="value" :open-on-click="true" :open-on-focus="true">
    <ComboboxAnchor class="app-combobox-anchor"><ComboboxInput class="app-combobox-input" :display-value="displayValue" :placeholder="placeholder" /><ComboboxTrigger class="app-combobox-trigger" :aria-label="`Abrir ${placeholder.toLocaleLowerCase('pt-BR')}`" title="Abrir opções"><svg viewBox="0 0 20 20" aria-hidden="true"><path d="m5 7.5 5 5 5-5" /></svg></ComboboxTrigger></ComboboxAnchor>
    <ComboboxPortal><ComboboxContent class="app-combobox-content" position="popper" side="bottom" :side-offset="4" :collision-padding="12"><ComboboxViewport class="app-combobox-viewport">
      <ComboboxItem v-if="allowEmpty" :value="null" class="app-combobox-option app-combobox-empty-choice"><span>{{ emptyLabel }}</span><ComboboxItemIndicator class="app-combobox-check">✓</ComboboxItemIndicator></ComboboxItem>
      <ComboboxItem v-for="option in options" :key="option.id" :value="option.id" :disabled="option.disabled" :text-value="option.label" class="app-combobox-option" :style="{ '--app-combobox-depth': option.depth ?? 0 }"><span class="app-combobox-option-copy"><b>{{ option.label }}</b><small v-if="option.description">{{ option.description }}</small></span><ComboboxItemIndicator class="app-combobox-check">✓</ComboboxItemIndicator></ComboboxItem>
      <ComboboxEmpty class="app-combobox-no-results">{{ emptyMessage }}</ComboboxEmpty>
    </ComboboxViewport></ComboboxContent></ComboboxPortal>
  </ComboboxRoot>
</template>

<style>
.app-combobox-anchor{position:relative;display:flex;align-items:center;width:100%}.app-combobox-input{width:100%;padding-right:39px}.app-combobox-trigger{position:absolute;right:4px;display:grid;width:31px;height:31px;place-items:center;border:0;border-radius:6px;background:transparent;color:#747b8b;cursor:pointer}.app-combobox-trigger:hover{background:#f0eff8;color:#5d50c6}.app-combobox-trigger:focus-visible,.app-combobox-input:focus-visible{outline:2px solid #6b5ddd;outline-offset:1px}.app-combobox-trigger svg{width:17px;height:17px;fill:none;stroke:currentColor;stroke-linecap:round;stroke-linejoin:round;stroke-width:1.8}.app-combobox-content{z-index:200;width:var(--reka-combobox-trigger-width);min-width:var(--reka-combobox-trigger-width);max-height:min(260px,var(--reka-combobox-content-available-height));overflow:hidden;border:1px solid #dfe2e9;border-radius:9px;background:#fff;box-shadow:0 14px 30px #10152626;transform-origin:var(--reka-combobox-content-transform-origin)}.app-combobox-viewport{max-height:inherit;overflow:auto;padding:4px}.app-combobox-option{display:flex;min-height:36px;align-items:center;justify-content:space-between;gap:8px;padding:7px 9px;padding-left:calc(9px + var(--app-combobox-depth, 0) * 16px);border-radius:6px;color:#30394c;font:inherit;font-size:12px;cursor:pointer;outline:none}.app-combobox-option[data-highlighted],.app-combobox-option[data-state='checked']{background:#efedff;color:#5e50cf}.app-combobox-option[data-disabled]{opacity:.45;cursor:not-allowed}.app-combobox-option-copy{min-width:0}.app-combobox-option-copy b,.app-combobox-option-copy small{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.app-combobox-option-copy b{font-weight:600}.app-combobox-option-copy small{margin-top:2px;color:#7b8291;font-size:10px}.app-combobox-check{font-size:13px;font-weight:800}.app-combobox-empty-choice{border-bottom:1px solid #eceef3;font-weight:600}.app-combobox-no-results{padding:13px 8px;color:#7d8599;font-size:11px;text-align:center}
</style>

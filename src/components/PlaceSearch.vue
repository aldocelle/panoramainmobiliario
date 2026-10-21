<script setup>
// Buscador con sugerencias de lugares (patrón combobox ARIA: flechas, Enter, Escape).
import {ref,computed,watch} from 'vue'
import Icon from './Icon.vue'
import {suggestPlaces} from '../lib/places'
const props=defineProps({modelValue:String,places:Array,placeholder:String,inputId:String})
const emit=defineEmits(['update:modelValue','pick'])
const open=ref(false),active=ref(-1)
const suggestions=computed(()=>suggestPlaces(props.places,props.modelValue))
const expanded=computed(()=>open.value&&suggestions.value.length>0)
const listId=props.inputId+'-list',optionId=i=>`${listId}-${i}`
watch(suggestions,()=>{active.value=-1})
function input(e){emit('update:modelValue',e.target.value);open.value=true}
function pick(place){emit('pick',place);open.value=false;active.value=-1}
function keydown(e){const n=suggestions.value.length;if(e.key==='ArrowDown'&&n){e.preventDefault();open.value=true;active.value=(active.value+1)%n}else if(e.key==='ArrowUp'&&n){e.preventDefault();open.value=true;active.value=(active.value-1+n)%n}else if(e.key==='Enter'&&expanded.value&&active.value>=0){e.preventDefault();pick(suggestions.value[active.value])}else if(e.key==='Escape'&&expanded.value){e.preventDefault();e.stopPropagation();open.value=false}}
</script>
<template><div class="place-search"><input :id="inputId" :value="modelValue" type="search" role="combobox" aria-autocomplete="list" :aria-expanded="expanded" :aria-controls="listId" :aria-activedescendant="expanded&&active>=0?optionId(active):undefined" autocomplete="off" enterkeyhint="search" :placeholder="placeholder" @input="input" @focus="open=true" @blur="open=false" @keydown="keydown"><ul v-show="expanded" :id="listId" role="listbox" class="place-list" aria-label="Lugares sugeridos"><li v-for="(p,i) in suggestions" :id="optionId(i)" :key="p.kind+p.label+p.detail" role="option" :aria-selected="i===active" :class="{active:i===active}" @mousedown.prevent="pick(p)"><Icon :name="p.kind==='region'?'map':'pin'"/><span class="place-text"><strong>{{p.label}}</strong><span v-if="p.detail">, {{p.detail}}</span></span><small>{{p.count?p.count+(p.count===1?' aviso':' avisos'):p.kind==='region'?'Región':'Sin avisos aún'}}</small></li></ul></div></template>

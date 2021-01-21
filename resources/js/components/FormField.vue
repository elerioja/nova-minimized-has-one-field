<template>
  <label :for="labelFor" class="inline-block text-80 pt-2 leading-tight">
    <slot />
  </label>
</template>

<script>

import { FormField } from 'laravel-nova';
export default {
  
  mixins: [FormField],
  props: ['field'],
  methods:{
    fill(formData) {
    formData.append(this.field.attribute, this.value || '')
          }
        }
}
</script>

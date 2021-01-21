<template>

  <panel-item :field="field">
    <template slot="value">
      <div class="flex space-between">
      <router-link
        v-if="field.viewable && field.value"
        :to="{
          name: 'detail',
          params: {
            resourceName: field.resourceName,
            resourceId: field.hasOneId,
            relatedResourceName:field.hasOneRelationship
          },
        }"
        class="no-underline font-bold dim text-primary"
      >
        {{ field.value }} 
      </router-link>
      <p v-else-if="field.value">{{ field.value }}</p>
      <!-- <p v-else>&mdash; {{label}}</p> --> 
      <router-link
      v-else
      dusk="create-button"
      class="btn btn-sm btn-outline inline-flex items-center focus:outline-none focus:shadow-outline active:outline-none active:shadow-outline"
      :to="{
        name: 'create',
        params: {
                resourceName: field.resourceName,
                resourceId: field.hasOneId,
              },
        query: {
          viaResource: field.resourceName,
          viaResourceId: resource.id.value,
          viaRelationship: field.hasOneRelationship,
        },
      }"
    >
      Create {{ singularName }} 
    </router-link>

    
       <div>
           <!-- View Resource Link -->
        <span v-if="field.viewable && field.value" class="inline-flex">
          <router-link
            :data-testid="`${testId}-view-button`"
            :dusk="`${field.hasOneId}-view-button`"
            class="cursor-pointer text-70 hover:text-primary mr-3 inline-flex items-center"
            v-tooltip.click="__('View')"
            :to="{
              name: 'detail',
              params: {
                resourceName: field.resourceName,
                resourceId: field.hasOneId,
              },
            }"
          >
            <icon type="view" width="22" height="18" view-box="0 0 22 16" />
          </router-link>
        </span>

          <!-- Edit Resource Link -->
        <span v-if="field.viewable && field.value" class="inline-flex">
          <router-link
            class="inline-flex cursor-pointer text-70 hover:text-primary mr-3"
            :dusk="`${field.hasOneId}-edit-button`"
            :to="{
              name: 'edit',
              params: {
                resourceName: field.resourceName,
                resourceId: field.hasOneId,
              },
              query: {
                viaResource: field.resourceName,
                viaResourceId: resource.id.value,
                viaRelationship: field.hasOneRelationship,
              },
            }"
            v-tooltip.click="__('Edit')"
          >
            <icon type="edit" />
          </router-link>
        </span>

        <!-- Delete Resource Link -->
        <button
          :data-testid="`${testId}-delete-button`"
          :dusk="`${field.hasOneId}-delete-button`"
          class="inline-flex appearance-none cursor-pointer text-70 hover:text-primary mr-3"
          v-tooltip.click="__('Delete')"
          v-if="field.viewable && field.value"
          @click.prevent="openDeleteModal"
        >
          <icon />
        </button>

        <portal to="modals" transition="fade-transition" v-if="deleteModalOpen">
          <delete-resource-modal
            v-if="deleteModalOpen"
            @confirm="confirmDelete"
            @close="closeDeleteModal"
            :mode="'delete'"
          >
            <div slot-scope="{ uppercaseMode, mode }" class="p-8">
              <heading :level="2" class="mb-6">{{ __(uppercaseMode + ' Resource') }}</heading>
              <p class="text-80 leading-normal">
                {{ __('Are you sure you want to ' + mode + ' this resource?') }}
              </p>
            </div>
          </delete-resource-modal>
        </portal>
      </div>
      </div>
    </template>
  </panel-item>
</template>

<script>
import {Minimum, Deletable} from 'laravel-nova'
export default {
  props: ['resource', 'resourceName', 'resourceId', 'field', 'testId',
    'queryString'],

    data: () => ({
    deleteModalOpen: false,
  }),
  mounted(){
    console.log('res',this.resource)
    console.log('field', this.field)
  },
  mixins: [Deletable],
  methods: {
    /**
     * Select the resource in the parent component
     */
  
    openDeleteModal() {
      this.deleteModalOpen = true
    },

    confirmDelete() {
      this.deleteResources([this.field.resource],response => {
        Nova.success(
          this.__('The :resource was deleted!', {
            resource: this.field.singularLabel.toLowerCase(),
          })
        )
          window.location.reload()
      })
    },

    getResources() {
      this.loading = true

      this.$nextTick(() => {
        this.clearResourceSelections()

        return Minimum(
          Nova.request().get('/nova-api/' + this.resourceName, {
            params: this.resourceRequestQueryString,
          }),
          300
        ).then(({ data }) => {
          this.resources = []

          this.resourceResponse = data
          this.resources = data.resources
          this.softDeletes = data.softDeletes
          this.perPage = data.per_page
          this.allMatchingResourceCount = data.total

          this.loading = false
          window.location.reload();
          this.$emit('reload-resources')
          this.$emit('refresh')
         
        })
      })
    },
    clearResourceSelections() {
      this.selectAllMatchingResources = false
      this.selectedResources = []
    },

    closeDeleteModal() {
      this.deleteModalOpen = false
    },
  },
  computed:{
    singularName() {
      if (this.field) {
        return this.field.singularLabel.toLowerCase()
      }
    },
  }
}
function mapResources(resources) {
  return _.map(resources, resource => resource.hasOneId)
}
</script>


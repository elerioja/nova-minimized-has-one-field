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
        },
        query: {
          viaResource: viaResource,
          viaResourceId: viaResourceId,
          viaRelationship: viaRelationship,
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
                viaResource: viaResource,
                viaResourceId: viaResourceId,
                viaRelationship: viaRelationship,
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
          v-tooltip.click="__(viaManyToMany ? 'Detach' : 'Delete')"
          v-if="field.viewable && field.value"
          @click.prevent="openDeleteModal"


        >
          <icon />
        </button>

        <!-- Restore Resource Link -->
        <button
          :dusk="`${field.hasOneId}-restore-button`"
          class="appearance-none cursor-pointer text-70 hover:text-primary mr-3"
          v-if="resource.authorizedToRestore && resource.softDeleted && !viaManyToMany"
          v-tooltip.click="__('Restore')"
          @click.prevent="openRestoreModal"
        >
          <icon type="restore" with="20" height="21" />
        </button>

        <portal to="modals" transition="fade-transition" v-if="deleteModalOpen || restoreModalOpen">
          <delete-resource-modal
            v-if="deleteModalOpen"
            @confirm="confirmDelete"
            @close="closeDeleteModal"
            :mode="viaManyToMany ? 'detach' : 'delete'"
          >
            <div slot-scope="{ uppercaseMode, mode }" class="p-8">
              <heading :level="2" class="mb-6">{{ __(uppercaseMode + ' Resource') }}</heading>
              <p class="text-80 leading-normal">
                {{ __('Are you sure you want to ' + mode + ' this resource?') }}
              </p>
            </div>
          </delete-resource-modal>

          <restore-resource-modal v-if="restoreModalOpen" @confirm="confirmRestore" @close="closeRestoreModal">
            <div class="p-8">
              <heading :level="2" class="mb-6">{{ __('Restore Resource') }}</heading>
              <p class="text-80 leading-normal">
                {{ __('Are you sure you want to restore this resource?') }}
              </p>
            </div>
          </restore-resource-modal>
        </portal>
      </div>
      </div>
    </template>
  </panel-item>
</template>

<script>
import {Minimum} from 'laravel-nova'
export default {
  props: ['resource', 'resourceName', 'resourceId', 'field', 'testId',
    'restoreResource',
    'resourcesSelected',
    'relationshipType',
    'viaRelationship',
    'viaResource',
    'viaResourceId',
    'viaManyToMany',
    'checked',
    'actionsAreAvailable',
    'shouldShowCheckboxes',
    'queryString',
    'reorderDisabled',
    'resourceIsSortable'],
  
     data: () => ({
    deleteModalOpen: false,
    restoreModalOpen: false,
  }),
   mounted() {
  console.log('field',this.field),  console.log('resource', this.resource)
 },

  methods: {
    /**
     * Select the resource in the parent component
     */
  

    openDeleteModal() {
      this.deleteModalOpen = true
     
    },

    confirmDelete() {
      this.deleteResources([this.field])
      this.closeDeleteModal()
    },
     deleteResources(resources, callback = null) {
      if (this.viaManyToMany) {
        return this.detachResources(resources)
      }

      return Nova.request({
        url: '/nova-api/' + this.field.resourceName,
        method: 'delete',
        params: {
          ...this.queryString,
          ...{ resources: mapResources(resources) },
        },
      }).then(
        callback
          ? callback
          : () => {
              this.deleteModalOpen = false
              this.getResources()
               
            }
      )
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
          location.window.reload();
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

    openRestoreModal() {
      this.restoreModalOpen = true
    },

    confirmRestore() {
      this.restoreResources(this.resource)
      this.closeRestoreModal()
    },

    closeRestoreModal() {
      this.restoreModalOpen = false
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
  console.log(resources)
  return _.map(resources, resource => resource.hasOneId)
}
</script>

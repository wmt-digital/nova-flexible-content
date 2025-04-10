<template>
  <component
      :dusk="currentField.attribute"
      :is="currentField.fullWidth ? 'FullWidthField' : 'default-field'"
      :field="currentField"
      :errors="errors"
      :show-help-text="showHelpText"
      full-width-content>
    <template #field>

      <div ref="flexibleFieldContainer">
        <form-nova-flexible-content-group
            v-for="(group, index) in orderedGroups"
            :dusk="currentField.attribute + '-' + index"
            :key="group.key"
            :field="currentField"
            :group="group"
            :index="index"
            :resource-name="resourceName"
            :resource-id="resourceId"
            :errors="errors"
            :mode="mode"
            class="nova-flexible-content-item"
            :class="{'nova-flexible-content-hidden-state': !group.show}"
            @move-up="moveUp(group.key)"
            @move-down="moveDown(group.key)"
            @remove="remove(group.key)"
            @duplicate="duplicateBlock(group.key, index)"
            @change-visibility="changeVisibility(group.key)"
        >
          <template #add-block-popup>
            <component
                :layouts="layouts"
                :is="currentField.menu.component"
                :field="currentField"
                :limit-counter="limitCounter"
                :limit-per-layout-counter="limitPerLayoutCounter"
                :errors="errors"
                :resource-name="resourceName"
                :resource-id="resourceId"
                @addGroup="addGroup($event, undefined, undefined, undefined, index)"
            />
          </template>
        </form-nova-flexible-content-group>
      </div>

      <component
          :layouts="layouts"
          :is="currentField.menu.component"
          :field="currentField"
          :limit-counter="limitCounter"
          :limit-per-layout-counter="limitPerLayoutCounter"
          :errors="errors"
          :resource-name="resourceName"
          :resource-id="resourceId"
          @addGroup="addGroup($event)"
      />

    </template>
  </component>
</template>

<script>

import FullWidthField from './FullWidthField';
import Sortable from 'sortablejs'
import {DependentFormField, HandlesValidationErrors, mapProps} from 'laravel-nova';
import Group from '../group';

export default {
  mixins: [HandlesValidationErrors, DependentFormField],

  props: {
    ...mapProps(['mode']),
  },

  components: {FullWidthField},

  computed: {
    layouts() {
      return this.currentField.layouts || false
    },
    orderedGroups() {
      return this.order.reduce((groups, key) => {
        groups.push(this.groups[key]);
        return groups;
      }, []);
    },

    limitCounter() {
      if (this.currentField.limit === null || typeof (this.currentField.limit) == "undefined") {
        return null;
      }

      return this.currentField.limit - Object.keys(this.groups).length;
    },

    limitPerLayoutCounter() {
      return this.layouts.reduce((layoutCounts, layout) => {
        if (layout.limit === null) {
          layoutCounts[layout.name] = null;

          return layoutCounts;
        }

        let count = Object.values(this.groups).filter(group => group.name === layout.name).length;

        layoutCounts[layout.name] = layout.limit - count;

        return layoutCounts;
      }, {});
    },
  },

  data() {
    return {
      order: [],
      groups: {},
      files: {},
      sortableInstance: null
    };
  },

  beforeUnmount() {
    if (this.sortableInstance) {
      this.sortableInstance.destroy();
    }
  },

  methods: {
    setInitialValue() {
      this.value = this.currentField.value || [];
      this.files = {};

      this.populateGroups();
      this.$nextTick(this.initSortable.bind(this));
    },

    fill(formData) {
      let key, group;

      this.value = [];
      this.files = {};

      for (var i = 0; i < this.order.length; i++) {
        key = this.order[i];
        group = this.groups[key].serialize();

        // Only serialize the group's non-file attributes
        this.value.push({
          layout: group.layout,
          key: group.key,
          show: group.show,
          attributes: group.attributes,
        });

        // Attach the files for formData appending
        this.files = {...this.files, ...group.files};
      }

      this.appendFieldAttribute(formData, this.currentField.attribute);
      formData.append(this.currentField.attribute, this.value.length ? JSON.stringify(this.value) : '');

      // Append file uploads
      for (let file in this.files) {
        formData.append(file, this.files[file]);
      }

      this.$nextTick(this.initSortable.bind(this));
    },

    appendFieldAttribute(formData, attribute) {
      let registered = [];

      if (formData.has('___nova_flexible_content_fields')) {
        registered = JSON.parse(formData.get('___nova_flexible_content_fields'));
      }

      registered.push(attribute);

      formData.set('___nova_flexible_content_fields', JSON.stringify(registered));
    },

    handleChange(value) {
      this.value = value || [];
      this.files = {};

      this.populateGroups();
    },

    /**
     * Set the displayed layouts from the field's current value
     */
    populateGroups() {
      this.order.splice(0, this.order.length);
      this.groups = {};

      for (let i = 0; i < this.value.length; i++) {
        this.addGroup(
            this.getLayout(this.value[i].layout),
            this.value[i].attributes,
            this.value[i].key,
            this.currentField.collapsed,
            undefined,
            this.value[i].show

        );
      }
    },

    getLayout(name) {
      if (!this.layouts) return;
      return this.layouts.find(layout => layout.name === name);
    },

    addGroup(layout, attributes, key, collapsed, previousPosition, show) {
      if (!layout) return;

      collapsed = collapsed || false;

      let fields = attributes || JSON.parse(JSON.stringify(layout.fields)),
          group = new Group(layout.name, layout.title, fields, this.currentField, key, collapsed, show);

      this.groups[group.key] = group;

      if (previousPosition !== undefined && previousPosition < this.order.length + 1) {
        this.order.splice(previousPosition + 1, 0, group.key);
      } else {
        this.order.push(group.key);
      }

      Nova.$emit('nova-flexible-content-group-added');
    },

    moveUp(key) {
      let index = this.order.indexOf(key);

      if (index <= 0) return;

      this.order.splice(index - 1, 0, this.order.splice(index, 1)[0]);
    },

    moveDown(key) {
      let index = this.order.indexOf(key);

      if (index < 0 || index >= this.order.length - 1) return;

      this.order.splice(index + 1, 0, this.order.splice(index, 1)[0]);
    },

    remove(key) {
      let index = this.order.indexOf(key);

      if (index < 0) return;

      this.order.splice(index, 1);
      delete this.groups[key];
    },

    duplicateBlock(key, index) {
      const cleanAttributes = function (attributes) {
        attributes.forEach((element, index, array) => {
          element.attribute = element.attribute.split('__')[1] ?? element.attribute;
          element.validationKey = element.validationKey.split('__')[1] ?? element.validationKey;
          if (element.component === 'nova-flexible-content') {
            element.value.forEach((value, index, array) => {
              array[index].attributes = cleanAttributes(value.attributes);
            });
          }
          array[index] = element;
        });
        return attributes;
      }

      const group = this.groups[key];
      const layout = this.getLayout(group.name);
      const attributes = cleanAttributes(JSON.parse(JSON.stringify(group.fields)));

      this.addGroup(layout, attributes, null, null, index, group.show);
    },

    changeVisibility(key) {
      let group = this.groups[key];

      group.changeVisibility()

      this.groups[key] = group
    },

    initSortable() {
      const containerRef = this.$refs['flexibleFieldContainer']

      if (!containerRef || this.sortableInstance) {
        return;
      }

      this.sortableInstance = Sortable.create(containerRef, {
        ghostClass: 'nova-flexible-content-sortable-ghost',
        dragClass: 'nova-flexible-content-sortable-drag',
        chosenClass: 'nova-flexible-content-sortable-chosen',
        direction: 'vertical',
        handle: '.nova-flexible-content-drag-button',
        scrollSpeed: 5,
        animation: 100,
        onEnd: (event) => {
          const oldIndex = event.oldIndex;
          const newIndex = event.newIndex;

          if (newIndex >= this.order.length) {
            let k = newIndex - this.order.length + 1;
            while (k--) {
              this.order.push(undefined);
            }
          }

          this.order.splice(newIndex, 0, this.order.splice(oldIndex, 1)[0]);
        }
      });
    },
  }
}
</script>

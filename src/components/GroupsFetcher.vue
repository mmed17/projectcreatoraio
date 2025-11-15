<template>
    <NcSelect
        :model-value="selectedOption"
        @update:model-value="emitUpdate"
        :options="options"
        :loading="isLoading"
        :show-label="true"
        :input-label="inputLabel"
        :placeholder="placeholder"
        :multiple="false"
        :searchable="true"
        @search="onSearch"
        clearable />
</template>

<script>
import NcSelect from '@nextcloud/vue/components/NcSelect';
import { GroupsService } from '../Services/groups';
import { CanceledError } from 'axios';

// Simple debounce function
function debounce(fn, delay) {
    let timeoutID;
    return function(...args) {
        clearTimeout(timeoutID);
        timeoutID = setTimeout(() => {
            fn.apply(this, args);
        }, delay);
    };
}

const groupsService = GroupsService.getInstance();

export default {
    name: 'GroupsFetcher',
    components: { NcSelect },
    props: {
        modelValue: { // This will be the groupId (string)
            type: String,
            default: null,
        },
        inputLabel: { type: String, default: 'Organization' },
        placeholder: { type: String, default: 'Search for an organization...' }
    },
    data() {
        return {
            isLoading: false,
            options: [], // The list of search results
            selectedOption: null, // The full { id, label } object
            abortController: null,
        };
    },
    watch: {
        modelValue: {
            immediate: true,
            async handler(newId) {
                if (newId && (!this.selectedOption || this.selectedOption.id !== newId)) {
                    // The parent set an ID, but we don't have the details. Fetch them.
                    this.isLoading = true;
                    const details = await groupsService.getDetails(newId);
                    if (details) {
                        this.selectedOption = details;
                        // Add to options list so it's rendered
                        this.options = [details]; 
                    }
                    this.isLoading = false;
                } else if (!newId) {
                    this.selectedOption = null;
                    this.options = [];
                }
            }
        }
    },
    created() {
        // Create a debounced search function
        this.debouncedSearch = debounce(this.searchGroups, 300);
    },
    methods: {
        onSearch(query) {
            // Cancel any pending search
            if (this.abortController) {
                this.abortController.abort();
            }
            if (!query) {
                // Clear options if search is empty, but keep the selected one
                this.options = this.selectedOption ? [this.selectedOption] : [];
                return;
            }
            this.isLoading = true;
            this.debouncedSearch(query);
        },
        async searchGroups(query) {
            this.abortController = new AbortController();
            try {
                const results = await groupsService.search(query, this.abortController.signal);
                
                // Ensure the currently selected option is always in the list
                if (this.selectedOption && !results.find(r => r.id === this.selectedOption.id)) {
                    this.options = [this.selectedOption, ...results];
                } else {
                    this.options = results;
                }
            } catch (error) {
                if (!(error instanceof CanceledError)) {
                    console.error('Error searching groups:', error);
                }
            } finally {
                this.isLoading = false;
            }
        },
        emitUpdate(selected) {
            this.selectedOption = selected;
            // Emit only the ID string, or null if cleared
            this.$emit('update:modelValue', selected ? selected.id : null);
        }
    }
}
</script>
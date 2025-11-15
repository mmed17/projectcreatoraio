<template>
    <NcSelectUsers :options="users"
        :model-value="modelValue"
        :multiple="multiple"
        :keep-open="true"
        :show-label="true"
        :no-wrap="true"
        :input-label="inputLabel"
        :placeholder="placeholder"
		:loading="isFetching"
        @search="fetchUsers"
        @update:modelValue="selectUsers" />
</template>

<script>
import NcSelectUsers from '@nextcloud/vue/components/NcSelectUsers'
import { UsersService } from '../Services/users';

const usersService = UsersService.getInstance();

export default {
	name: 'UsersFetcher',
	components: {
        NcSelectUsers
    },
    props: {
        inputLabel: {
            type: String,
            default: 'Here should be a label',
        },
        placeholder: {
            type: String,
            default: 'Here should be a placeholder',
        },
        modelValue: {
            type: Array,
            default: () => [],
        },
		multiple: {
			type: Boolean,
			default: () => true,
		},
		organizationId: {
            type: String,
            default: null,
        },
    },
    data() {
        return {
            users: [],
			searchTimeout: null,
			isFetching: false,
			trackedUsers: [],
        }
    },
	watch: {
        organizationId(newId, oldId) {
            if (newId !== oldId) {
                this.clearSelection();
            }
        }
    },
	computed: {
		selectedUsers: {
			get() {
				return this.modelValue
			}
		}
	},
    methods: {
        async fetchUsers(query) {
			if (this.searchTimeout) {
				clearTimeout(this.searchTimeout);
			}

			this.isFetching = true;
			this.searchTimeout = setTimeout(async () => {
				this.users = await usersService.search(query, this.organizationId);
				this.isFetching = false;
			}, 300);
		},
		selectUsers(users) {
			const usersId = users.reduce((acc, curr) => {
				if(curr.id) {
					this.trackedUsers.push(curr);
					acc.push(curr.id);
				} else {
					acc.push(curr);
				}

				return acc;
			}, []);

			this.$emit('update:modelValue', usersId);
		},
		clearSelection() {
            this.users = []; // Clear dropdown options
            this.trackedUsers = [];
            this.$emit('update:modelValue', []); // Clear parent v-model
        }
    }
}
</script>

<style lang="css" scoped>

</style>
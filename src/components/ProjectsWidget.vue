<template>
    <div class="project-widget">
        <div class="filter-container">
            <NcTextField v-model="searchQuery"
                :label="t('projectcreatoraio', 'Search Projects')"
                :placeholder="t('projectcreatoraio', 'e.g: Project Alpha...')"
                trailing-button-icon="close">
                <template #icon>
                    <Magnify :size="20" />
                </template>
            </NcTextField>
            
            <NcActions v-if="isAdmin">
                <template #icon>
                    <NcAvatar 
                        v-if="selectedUser" 
                        :display-name="selectedUser.displayName" 
                        :is-no-user="true" 
                        size="32" />
                    <AccountPlus v-else :size="20" />
                </template>

                <NcActionInput
                    v-model="selectedUser"
                    ref="usersInputRef"
                    type="multiselect"
                    track-by="label"
                    :append-to-body="true"
                    :multiple="false"
                    :options="allUsers"
                    :loading="isFetchingUsers"
                    @search="fetchUsers"
                    @update:modelValue="fetchProjectsByUser">
                    <template #icon>
                        <Account :size="20" />
                    </template>
                    Please select a user
                </NcActionInput>
            </NcActions>
        </div>

        <div v-if="loading" class="loading-placeholder">
            <NcLoadingIcon :size="44" />
        </div>

        <NcEmptyContent v-else-if="projects.length === 0"
            :name="t('projectcreatoraio', 'No projects found')">
            <template #icon>
                <FolderOutline :size="36" />
            </template>
        </NcEmptyContent>

        <div v-else class="project-list">
            <ul>
                <NcListItem
                    v-for="project in filteredProjects"
                    :name="project.name"
                    :active="selectedProjectId === project.id"
                    :force-display-actions="true"
                    @click="selectProject(project)">

                    <template #icon>
                        <FolderOutline :size="30" />
                    </template>
                    
                    <template #subname>
                        {{ PROJECT_TYPES[project.type]?.label }}
                    </template>

                    <template #indicator>
                        <NcChip
                            no-close 
                            :text="currentProjectStatus(project)" 
                            :variant="project.status && selectedProjectId !== project.id ? 'primary':'secondary'" />
                    </template>

                    <template #actions>
                        <NcActionButton
                            icon="icon-download"
                            :title="t('projectcreatoraio', 'Download project')"
                            @click="onDownload(project)">
                            Download
                        </NcActionButton>
                        <NcActionButton
                            icon="icon-screen"
                            :title="t('projectcreatoraio', 'View details')"
                            @click="onPreview(project)">
                            Project Page
                        </NcActionButton>
                    </template>
                </NcListItem>
            </ul>
        </div>
    </div>
</template>

<script>
import { NcActions, NcActionButton, NcEmptyContent, NcNoteCard, NcTextField } from '@nextcloud/vue'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcUserBubble from '@nextcloud/vue/components/NcUserBubble'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcActionInput from '@nextcloud/vue/components/NcActionInput'
import { t } from '@nextcloud/l10n'

import FolderOutline from 'vue-material-design-icons/FolderOutline.vue'
import Magnify from 'vue-material-design-icons/Magnify.vue'
import Download from 'vue-material-design-icons/Download.vue';
import EyeOutline from 'vue-material-design-icons/EyeOutline.vue';
import Details from 'vue-material-design-icons/Details.vue';
import FilterCog from 'vue-material-design-icons/FilterCog.vue';
import AccountPlus from 'vue-material-design-icons/AccountPlus.vue';
import Account from 'vue-material-design-icons/Account.vue';
import AccountEdit from "vue-material-design-icons/AccountEdit.vue";
import NcAvatar from '@nextcloud/vue/components/NcAvatar';
import NcChip from '@nextcloud/vue/components/NcChip';
import { getCurrentUser } from '@nextcloud/auth'
import UsersFetcher from './UsersFetcher.vue'
import { PROJECT_TYPES } from '../macros/project-types';
import { UsersService } from '../Services/users'
import { ProjectsService } from '../Services/projects'
import { generateUrl, generateRemoteUrl } from '@nextcloud/router';
import { createClient } from 'webdav';

const usersService = UsersService.getInstance();
const projectsService = ProjectsService.getInstance();
const client = createClient(generateRemoteUrl('dav'));

export default {
	name: 'ProjectsWidget',
	components: {
		NcActions,
		NcActionButton,
		NcEmptyContent,
        NcNoteCard,
        NcLoadingIcon,
        NcTextField,
        FolderOutline,
        Magnify,
        NcListItem,
        Download,
        Details,
        NcUserBubble,
        NcDialog,
        NcButton,
        FilterCog,
        UsersFetcher,
        NcActionInput,
        AccountPlus,
        Account,
        AccountEdit,
        NcAvatar,
        EyeOutline,
        NcChip
	},
	data() {
		return {
			t,
			projects: [],
			loading: true,
			searchQuery: null,
            selectedProjectId: null,
            showFilterDialog: false,
            isFetchingUsers: false,
            selectedUser: null,
            PROJECT_TYPES,
            allUsers: [],
            searchTimeout: undefined,
            statuses: [
                { id: 0, label: 'Archived' },
                { id: 1, label: 'Active' }
            ]
		}
	},
	computed: {
        isAdmin() {
            return !!getCurrentUser()?.isAdmin;
        },
		filteredProjects() {
			if (!this.searchQuery) {
				return this.projects;
			}

			return this.projects.filter(project => {
				return project.name.toLowerCase().includes(this.searchQuery.toLowerCase());
			});
		}
	},
	async mounted() {
        const panelContent = this.$el.closest('.panel--content');
        if (panelContent) {
            panelContent.style.overflowY = 'scroll';
        }

        await this.listProjects();
	},
	methods: {
        async listProjects() {
            this.loading = true;
            this.projects = await projectsService.list();
            this.loading = false;
        },
        async fetchUsers(query) {
			if (this.searchTimeout) {
				clearTimeout(this.searchTimeout);
			}

			this.isFetchingUsers = true;

			this.searchTimeout = setTimeout(async () => {
                this.allUsers = await usersService.search(query);
                this.isFetchingUsers = false;
			}, 300);
		},
        async fetchProjectsByUser(user) {
            try {
                if(user) {
                    this.projects = await projectsService.fetchProjectsByUser(user.id);
                } else {
                    this.projects = await projectsService.list();
                }
            } catch(error) {
                console.error('Error searching for user projects', error);
                this.projects = [];
            }
        },
        selectProject(project) {
            let eventPayload = null;

            if (this.selectedProjectId === project.id) {
                this.selectedProjectId = null;
            } else {
                this.selectedProjectId = project.id;
                eventPayload = {
                    projectId: project.id,
                    boardId: project.boardId
                };
            }

            const event = new CustomEvent('projectcreatoraio:project-selected', { detail: eventPayload });
            document.dispatchEvent(event);
        },
        navigateToProjectPage(boardId) {
            const url = generateUrl(`/apps/deck/board/${boardId}`);
            window.open(url, "_blank");
        },
        onPreview(project) {
            if (!project.boardId) {
                return;
            }

            this.navigateToProjectPage(project.boardId);
        },
        onDownload(project) {
            if (!project.folderPath) {
                console.error('Cannot download project, folder name is missing.', project);
                return;
            }

            const path = this.normalizedPath(project.folderPath);
            const downloadUrl = new URL(client.getFileDownloadLink(path));

            downloadUrl.searchParams.append('accept', 'zip')
            this.triggerDownload(downloadUrl.href);
        },
        triggerDownload(href) {
            const link = document.createElement('a');
            link.href = href;
            link.style.display = 'none';
            document.body.appendChild(link);
            link.click();
            link.remove();
        },
        normalizedPath(path) {
            const parts = path.split('/');
            if (parts.length >= 3) {
                [parts[1], parts[2]] = [parts[2], parts[1]];
            }
            return parts.join('/');
        },
        currentProjectStatus(project) {
            if (!this.statuses || !project) {
                return 'Unknown';
            }
            
            const status = this.statuses.find(s => s.id === project.status);
            return status ? status.label : 'Archived'; 
        }
	}
}
</script>

<style lang="css" scoped>
@import '../styles/dashboard.css';
</style>

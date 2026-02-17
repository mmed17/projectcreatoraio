<template>
	<div v-if="contextError" class="projects-home-empty">
		<NcEmptyContent name="Could not load project context" :description="contextError">
			<template #icon>
				<Details :size="44" />
			</template>
		</NcEmptyContent>
	</div>
	<div v-else-if="!hasProjectAccess" class="projects-home-empty">
		<NcEmptyContent
			name="No organization assigned"
			description="Ask your administrator to assign your account to an organization before creating projects.">
			<template #icon>
				<OfficeBuilding :size="44" />
			</template>
		</NcEmptyContent>
	</div>
	<div
		v-else
		class="projects-home"
		:class="{
			'projects-home--narrow': isNarrow,
			'projects-home--narrow-details': isNarrow && mobilePane === 'details',
		}">
		<section class="projects-home__list-pane" v-show="!isNarrow || mobilePane === 'list'">
			<header class="projects-home__header">
				<div class="projects-home__header-left">
					<h2 class="projects-home__title">Projects</h2>
					<p class="projects-home__subtitle">Browse and manage project spaces</p>
					<div class="projects-home__header-meta">
						<span class="projects-home__scope-pill">{{ scopeLabel }}</span>
					</div>
				</div>
				<NcButton type="primary" @click="startCreateProject">
					<template #icon>
						<Plus :size="18" />
					</template>
					New project
				</NcButton>
			</header>

			<div class="projects-home__controls">
				<div v-if="isOrganizationAdmin" class="projects-home__scope-row">
					<label class="projects-home__control-label" for="projects-scope">Scope</label>
					<select
						id="projects-scope"
						v-model="projectScope"
						class="projects-home__filter-select"
						aria-label="Project scope"
						@change="loadProjects">
						<option value="all">All org projects</option>
						<option value="my">My projects</option>
					</select>
				</div>

				<NcTextField
					v-model="searchQuery"
					label="Search projects"
					input-label="Search projects"
					placeholder="Search by name or number">
					<template #icon>
						<Magnify :size="18" />
					</template>
				</NcTextField>

				<div class="projects-home__filters-row">
					<div class="projects-home__filter">
						<label class="projects-home__control-label" for="projects-status">Status</label>
						<select
							id="projects-status"
							v-model="statusFilter"
							class="projects-home__filter-select"
							aria-label="Filter projects">
							<option value="all">All</option>
							<option value="active">Active</option>
							<option value="archived">Archived</option>
						</select>
					</div>
					<div class="projects-home__filter">
						<label class="projects-home__control-label" for="projects-sort">Sort</label>
						<select
							id="projects-sort"
							v-model="sortKey"
							class="projects-home__filter-select"
							aria-label="Sort projects">
							<option value="default">Default</option>
							<option value="name">Name</option>
							<option value="number">Number</option>
						</select>
					</div>
					<NcButton type="tertiary" :disabled="!canClearFilters" class="projects-home__clear" @click="clearFilters">
						Clear
					</NcButton>
				</div>

				<div v-if="!loading" class="projects-home__count-row">
					{{ visibleProjects.length }} / {{ projects.length }} projects
				</div>
			</div>

			<div class="projects-home__list">
				<div v-if="loading" class="projects-home__centered">Loading projects...</div>
				<NcEmptyContent v-else-if="visibleProjects.length === 0" name="No projects yet" description="Create a project to get started.">
					<template #icon>
						<FolderOutline :size="36" />
					</template>
				</NcEmptyContent>
				<ul v-else class="projects-home__items">
					<li v-for="project in visibleProjects" :key="project.id">
						<button
							type="button"
							class="projects-home__project-item"
							:class="{ 'projects-home__project-item--active': selectedProjectId === project.id }"
							@click="selectProject(project)">
							<div class="projects-home__project-main">
								<div class="projects-home__project-title-row">
									<FolderOutline :size="20" />
									<span class="projects-home__project-name">{{ project.name }}</span>
									<span class="projects-home__status-pill" :class="statusPillClass(project.status)">{{ statusLabel(project.status) }}</span>
								</div>
								<div class="projects-home__project-meta">
									<span>{{ project.number || 'No project number' }}</span>
									<span>•</span>
									<span>{{ typeLabel(project.type) }}</span>
								</div>
							</div>
							<div class="projects-home__quick-actions">
								<button
									type="button"
									class="projects-home__quick-action"
									:title="project.boardId ? 'Open Deck board' : 'No Deck board linked'"
									:disabled="!project.boardId"
									@click.stop="openDeck(project)">
									<EyeOutline :size="16" />
								</button>
								<button
									type="button"
									class="projects-home__quick-action"
									:title="project.folderPath ? 'Download project files' : 'No project folder linked'"
									:disabled="!project.folderPath"
									@click.stop="downloadProject(project)">
									<Download :size="16" />
								</button>
							</div>
						</button>
					</li>
				</ul>
			</div>
		</section>

		<section class="projects-home__details-pane" v-show="!isNarrow || mobilePane === 'details'">
			<div v-if="selectedProject" class="projects-home__details-content">
				<div class="projects-home__hero">
					<div v-if="isNarrow" class="projects-home__hero-mobile">
						<NcButton type="tertiary" class="projects-home__back" @click="mobilePane = 'list'">
							<template #icon>
								<ChevronLeft :size="18" />
							</template>
							Back
						</NcButton>
					</div>
					<div class="projects-home__hero-main">
						<div>
							<h2 class="projects-home__details-title">{{ selectedProject.name || 'Unnamed project' }}</h2>
							<p class="projects-home__details-subtitle">{{ selectedProject.description || 'No description provided yet.' }}</p>
						</div>
						<div class="projects-home__hero-aside">
							<div class="projects-home__hero-badges">
								<span class="projects-home__badge">{{ statusLabel(selectedProject.status) }}</span>
								<span class="projects-home__badge">{{ typeLabel(selectedProject.type) }}</span>
								<span class="projects-home__badge">#{{ selectedProject.number || 'N/A' }}</span>
							</div>
							<div v-if="canManageProjects" class="projects-home__hero-actions">
								<NcActions>
									<template #icon>
										<DotsHorizontal :size="18" />
									</template>
									<NcActionButton
										:icon="isArchivedStatus(selectedProject.status) ? 'icon-history' : 'icon-archive'"
										@click="openArchiveDialog">
										<template #icon>
											<ArchiveArrowUp v-if="isArchivedStatus(selectedProject.status)" :size="16" />
											<Archive v-else :size="16" />
										</template>
										{{ isArchivedStatus(selectedProject.status) ? 'Restore project' : 'Archive project' }}
									</NcActionButton>
								</NcActions>
							</div>
							<p v-if="canManageProjects && statusUpdateError" class="projects-home__inline-error">{{ statusUpdateError }}</p>
						</div>
					</div>
				</div>

				<div class="projects-home__tabs" role="tablist" aria-label="Project workspace">
					<button type="button" class="projects-home__tab" :class="{ 'projects-home__tab--active': activeTab === 'overview' }" @click="setActiveTab('overview')">Overview</button>
					<button type="button" class="projects-home__tab" :class="{ 'projects-home__tab--active': activeTab === 'members' }" @click="setActiveTab('members')">Members</button>
					<button type="button" class="projects-home__tab" :class="{ 'projects-home__tab--active': activeTab === 'notes' }" @click="setActiveTab('notes')">Notes</button>
					<button type="button" class="projects-home__tab" :class="{ 'projects-home__tab--active': activeTab === 'timeline' }" @click="setActiveTab('timeline')">Timeline</button>
					<button type="button" class="projects-home__tab" :class="{ 'projects-home__tab--active': activeTab === 'deck' }" @click="setActiveTab('deck')">Deck</button>
					<button type="button" class="projects-home__tab" :class="{ 'projects-home__tab--active': activeTab === 'whiteboard' }" @click="setActiveTab('whiteboard')">Whiteboard</button>
					<button type="button" class="projects-home__tab" :class="{ 'projects-home__tab--active': activeTab === 'files' }" @click="setActiveTab('files')">Files</button>
				</div>

				<div class="projects-home__tab-panel" role="tabpanel">
					<div v-if="activeTab === 'overview'" class="projects-home__detail-grid">
						<article class="projects-home__card">
							<h3 class="projects-home__card-title">Project details</h3>
							<div class="projects-home__kv">
								<span class="projects-home__label">Name</span>
								<span>{{ selectedProject.name || '-' }}</span>
							</div>
							<div class="projects-home__kv">
								<span class="projects-home__label">Number</span>
								<span>{{ selectedProject.number || '-' }}</span>
							</div>
							<div class="projects-home__kv">
								<span class="projects-home__label">External ref</span>
								<span>{{ selectedProject.external_ref || '-' }}</span>
							</div>
						</article>

						<article class="projects-home__card">
							<div class="projects-home__card-head">
								<h3 class="projects-home__card-title">Client information</h3>
								<NcButton
									v-if="canManageProjects"
									type="tertiary"
									@click="startProjectProfileEdit">
									Edit details
								</NcButton>
							</div>
							<div class="projects-home__kv">
								<span class="projects-home__label">Client name</span>
								<span>{{ selectedProject.client_name || '-' }}</span>
							</div>
							<div class="projects-home__kv">
								<span class="projects-home__label">Role</span>
								<span>{{ selectedProject.client_role || '-' }}</span>
							</div>
							<div class="projects-home__kv">
								<span class="projects-home__label">Phone</span>
								<span>{{ selectedProject.client_phone || '-' }}</span>
							</div>
							<div class="projects-home__kv">
								<span class="projects-home__label">Email</span>
								<span>{{ selectedProject.client_email || '-' }}</span>
							</div>
						</article>

						<article class="projects-home__card">
							<h3 class="projects-home__card-title">Location</h3>
							<div class="projects-home__kv">
								<span class="projects-home__label">Street</span>
								<span>{{ selectedProject.loc_street || '-' }}</span>
							</div>
							<div class="projects-home__kv">
								<span class="projects-home__label">City</span>
								<span>{{ selectedProject.loc_city || '-' }}</span>
							</div>
							<div class="projects-home__kv">
								<span class="projects-home__label">ZIP</span>
								<span>{{ selectedProject.loc_zip || '-' }}</span>
							</div>
							<div class="projects-home__kv">
								<span class="projects-home__label">Address</span>
								<span>{{ selectedProject.client_address || '-' }}</span>
							</div>
							<p v-if="projectProfileError" class="projects-home__inline-error projects-home__inline-error--left">{{ projectProfileError }}</p>
							<p v-if="projectProfileMessage" class="projects-home__inline-success">{{ projectProfileMessage }}</p>
						</article>

						<article class="projects-home__card">
							<h3 class="projects-home__card-title">Project links</h3>
							<div class="projects-home__links">
								<NcButton type="secondary" :disabled="!selectedProject.boardId" @click="openDeck(selectedProject)">
									<template #icon>
										<EyeOutline :size="18" />
									</template>
									Open Deck board
								</NcButton>
								<NcButton type="secondary" :disabled="!selectedProject.folderPath" @click="openFolder(selectedProject)">
									<template #icon>
										<FolderOutline :size="18" />
									</template>
									Open folder
								</NcButton>
								<NcButton type="secondary" :disabled="!selectedProject.white_board_id" @click="openWhiteboard(selectedProject)">
									<template #icon>
										<Details :size="18" />
									</template>
									Open whiteboard
								</NcButton>
							</div>
						</article>
					</div>

					<div v-else-if="activeTab === 'members'" class="projects-home__tab-section">
						<div class="projects-home__tab-section-head">
							<h3 class="projects-home__section-title">Members</h3>
							<p class="projects-home__muted">Anyone in this project can invite organization members.</p>
						</div>

						<div class="projects-home__member-invite-row">
							<NcSelect
								:model-value="memberInviteSelection"
								:options="memberInviteOptions"
								:loading="memberSearchLoading"
								:show-label="true"
								:multiple="false"
								:searchable="true"
								:clearable="true"
								input-label="Invite a member"
								placeholder="Search organization members"
								@search="searchMemberCandidates"
								@update:model-value="memberInviteSelection = $event" />
							<NcButton
								type="primary"
								:disabled="memberInviteLoading || !memberInviteSelection || !selectedProject.id"
								@click="inviteSelectedMember">
								{{ memberInviteLoading ? 'Inviting...' : 'Invite' }}
							</NcButton>
						</div>

						<p v-if="memberInviteMessage" class="projects-home__inline-success">{{ memberInviteMessage }}</p>
						<p v-if="membersError" class="projects-home__inline-error projects-home__inline-error--left">{{ membersError }}</p>

						<div v-if="membersLoading" class="projects-home__muted">Loading members...</div>
						<div v-else-if="projectMembers.length === 0" class="projects-home__muted">No members found.</div>
						<ul v-else class="projects-home__members-list">
							<li v-for="member in projectMembers" :key="member.id" class="projects-home__member-item">
								<div class="projects-home__member-main">
									<span class="projects-home__member-name">{{ member.displayName || member.id }}</span>
									<span class="projects-home__member-meta">{{ member.id }}</span>
								</div>
								<div class="projects-home__member-badges">
									<span v-if="member.isOwner" class="projects-home__member-badge">Owner</span>
									<span v-if="member.email" class="projects-home__member-badge projects-home__member-badge--muted">{{ member.email }}</span>
								</div>
							</li>
						</ul>
					</div>

					<div v-else-if="activeTab === 'notes'" class="projects-home__tab-section">
						<ProjectNotesList :project-id="selectedProject.id" />
					</div>

					<div v-else-if="activeTab === 'timeline'" class="projects-home__tab-section">
						<GanttChart :project-id="selectedProject.id" :is-admin="true" />
					</div>

					<div v-else-if="activeTab === 'deck'" class="projects-home__tab-section">
						<DeckBoard
							:board-id="selectedProject.boardId"
							:project-id="selectedProject.id"
							:organization-id="Number(selectedProject.organization_id) || Number(context?.organizationId) || null"
							:can-manage-profiles="canManageProjects"
						/>
					</div>

					<div v-else-if="activeTab === 'whiteboard'" class="projects-home__tab-section">
						<WhiteboardBoard
							ref="whiteboardBoard"
							:project-id="selectedProject.id"
							:user-id="context?.userId || ''"
							:key="String(selectedProject.id || '') + ':' + String(selectedProject.white_board_id || '')"
						/>
					</div>

					<div v-else-if="activeTab === 'files'" class="projects-home__tab-section">
						<div class="projects-home__tab-top">
							<NcButton
								type="secondary"
								:disabled="!selectedProject.folderPath"
								@click.stop.prevent="downloadProject(selectedProject)">
								<template #icon>
									<Download :size="18" />
								</template>
								Project ZIP
							</NcButton>
						</div>
						<ProjectFilesBrowser
							:shared-roots="projectFiles.shared"
							:private-roots="projectFiles.private"
							:loading="filesLoading"
							:error="filesError"
						/>
					</div>
				</div>
			</div>

			<NcEmptyContent v-else name="Select a project" description="Choose a project from the list to view details.">
				<template #icon>
					<Details :size="36" />
				</template>
			</NcEmptyContent>
		</section>

		<NcDialog
			v-if="showArchiveDialog && selectedProject"
			:name="archiveDialogAction === 'archive' ? 'Archive project' : 'Restore project'"
			:message="archiveDialogAction === 'archive'
				? `Are you sure you want to archive '${selectedProject.name}'? Archived projects will be hidden from active project lists but can be restored at any time.`
				: `Are you sure you want to restore '${selectedProject.name}'? This project will become active again and visible in project lists.`"
			:buttons="[
				{
					label: 'Cancel',
					type: 'secondary',
					callback: () => { showArchiveDialog = false },
				},
				{
					label: archiveDialogAction === 'archive' ? 'Archive' : 'Restore',
					type: 'primary',
					nativeType: archiveDialogAction === 'archive' ? 'error' : 'submit',
					callback: () => { executeArchiveAction() },
				},
			]"
			@close="showArchiveDialog = false" />

		<NcModal :show="showCreateModal" size="large" @close="closeCreateModal">
			<ProjectCreator embedded @created="handleProjectCreated" @cancel="closeCreateModal" />
		</NcModal>

		<NcModal :show="showProjectProfileModal" size="normal" @close="cancelProjectProfileEdit">
			<div class="projects-home__profile-modal">
				<h3 class="projects-home__profile-modal-title">Edit client and location details</h3>
				<p class="projects-home__muted projects-home__profile-modal-subtitle">
					Update project client and location metadata.
				</p>

				<div class="projects-home__profile-grid">
					<NcTextField
						v-model="projectProfileDraft.client_name"
						label="Client name"
						:show-label="true"
						input-label="Client name"
						placeholder="Client name" />
					<NcTextField
						v-model="projectProfileDraft.client_role"
						label="Client role"
						:show-label="true"
						input-label="Client role"
						placeholder="Client role" />
					<NcTextField
						v-model="projectProfileDraft.client_phone"
						label="Client phone"
						:show-label="true"
						input-label="Client phone"
						placeholder="Client phone" />
					<NcTextField
						v-model="projectProfileDraft.client_email"
						label="Client email"
						:show-label="true"
						input-label="Client email"
						placeholder="Client email" />
					<NcTextField
						v-model="projectProfileDraft.loc_street"
						label="Location street"
						:show-label="true"
						input-label="Location street"
						placeholder="Location street" />
					<NcTextField
						v-model="projectProfileDraft.loc_city"
						label="Location city"
						:show-label="true"
						input-label="Location city"
						placeholder="Location city" />
					<NcTextField
						v-model="projectProfileDraft.loc_zip"
						label="Location ZIP"
						:show-label="true"
						input-label="Location ZIP"
						placeholder="Location ZIP" />
					<NcTextField
						v-model="projectProfileDraft.client_address"
						label="Client address"
						:show-label="true"
						input-label="Client address"
						placeholder="Client address" />
				</div>

				<p v-if="projectProfileError" class="projects-home__inline-error projects-home__inline-error--left">{{ projectProfileError }}</p>

				<div class="projects-home__profile-actions">
					<NcButton
						type="secondary"
						:disabled="projectProfileSaving"
						@click="cancelProjectProfileEdit">
						Cancel
					</NcButton>
					<NcButton
						type="primary"
						:disabled="projectProfileSaving"
						@click="saveProjectProfile">
						{{ projectProfileSaving ? 'Saving...' : 'Save details' }}
					</NcButton>
				</div>
			</div>
		</NcModal>
	</div>
</template>

<script>
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcModal from '@nextcloud/vue/components/NcModal'

import Details from 'vue-material-design-icons/Details.vue'
import Download from 'vue-material-design-icons/Download.vue'
import EyeOutline from 'vue-material-design-icons/EyeOutline.vue'
import FolderOutline from 'vue-material-design-icons/FolderOutline.vue'
import Magnify from 'vue-material-design-icons/Magnify.vue'
import OfficeBuilding from 'vue-material-design-icons/OfficeBuilding.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import Archive from 'vue-material-design-icons/Archive.vue'
import ArchiveArrowUp from 'vue-material-design-icons/ArchiveArrowUp.vue'
import DotsHorizontal from 'vue-material-design-icons/DotsHorizontal.vue'
import ChevronLeft from 'vue-material-design-icons/ChevronLeft.vue'

import { generateRemoteUrl, generateUrl } from '@nextcloud/router'
import { createClient } from 'webdav'

import { PROJECT_TYPES } from '../macros/project-types'
import { ProjectsService } from '../Services/projects'

import DeckBoard from './ProjectDeck/DeckBoard.vue'
import GanttChart from './ProjectTimeline/GanttChart.vue'
import ProjectFilesBrowser from './ProjectFiles/ProjectFilesBrowser.vue'
import WhiteboardBoard from './ProjectWhiteboard/WhiteboardBoard.vue'
import ProjectCreator from './ProjectCreator.vue'
import ProjectNotesList from './ProjectNotesList.vue'

const projectsService = ProjectsService.getInstance()
const webdavClient = createClient(generateRemoteUrl('dav'))

export default {
	name: 'ProjectsHome',
	components: {
		DeckBoard,
		Details,
		Download,
		EyeOutline,
		FolderOutline,
		GanttChart,
		Magnify,
		NcButton,
		NcEmptyContent,
		NcTextField,
		NcSelect,
		NcActions,
		NcActionButton,
		NcDialog,
		NcModal,
		OfficeBuilding,
		Plus,
		Archive,
		ArchiveArrowUp,
		DotsHorizontal,
		ChevronLeft,
		ProjectFilesBrowser,
		WhiteboardBoard,
		ProjectCreator,
		ProjectNotesList,
	},
	data() {
		return {
			context: null,
			contextError: '',
			filesError: '',
			filesLoading: false,
			statusUpdating: false,
			statusUpdateError: '',
			showCreateModal: false,
			activeTab: 'overview',
			sortKey: 'default',
			isNarrow: false,
			mobilePane: 'list',
			loadedMembersProjectId: null,
			loadedFilesProjectId: null,
			loading: false,
			projectFiles: {
				private: [],
				shared: [],
			},
			projectScope: 'all',
			projects: [],
			searchQuery: '',
			selectedProject: null,
			selectedProjectId: null,
			statusFilter: 'all',
			showArchiveDialog: false,
			archiveDialogAction: 'archive',
			projectMembers: [],
			membersLoading: false,
			membersError: '',
			memberInviteSelection: null,
			memberInviteOptions: [],
			memberSearchLoading: false,
			memberInviteLoading: false,
			memberInviteMessage: '',
			memberSearchTimeout: null,
			showProjectProfileModal: false,
			projectProfileDraft: {
				client_name: '',
				client_role: '',
				client_phone: '',
				client_email: '',
				client_address: '',
				loc_street: '',
				loc_city: '',
				loc_zip: '',
			},
			projectProfileSaving: false,
			projectProfileError: '',
			projectProfileMessage: '',
		}
	},
	computed: {
		hasProjectAccess() {
			if (this.context === null) {
				return true
			}

			return this.context.isGlobalAdmin || this.context.organizationId !== null
		},
		isOrganizationAdmin() {
			return this.context?.organizationRole === 'admin' && !this.context?.isGlobalAdmin
		},
		canManageProjects() {
			return !!(this.context?.isGlobalAdmin || this.context?.organizationRole === 'admin')
		},
		scopeLabel() {
			if (this.context?.isGlobalAdmin) {
				return 'Global admin view'
			}

			if (this.context?.organizationRole === 'admin') {
				return 'Organization admin view'
			}

			return 'My projects view'
		},
		filteredProjects() {
			const search = this.searchQuery.trim().toLowerCase()
			return this.projects.filter((project) => {
				const normalizedStatus = Number(project.status)
				if (this.statusFilter === 'active' && normalizedStatus !== 1) {
					return false
				}
				if (this.statusFilter === 'archived' && normalizedStatus !== 0) {
					return false
				}
				if (search === '') {
					return true
				}
				const name = (project.name || '').toLowerCase()
				const number = (project.number || '').toLowerCase()
				return name.includes(search) || number.includes(search)
			})
		},
		visibleProjects() {
			const list = (this.filteredProjects || []).slice()
			if (this.sortKey === 'name') {
				return list.sort((a, b) => String(a.name || '').localeCompare(String(b.name || ''), undefined, { sensitivity: 'base' }))
			}
			if (this.sortKey === 'number') {
				return list.sort((a, b) => String(a.number || '').localeCompare(String(b.number || ''), undefined, { sensitivity: 'base' }))
			}
			return list
		},
		canClearFilters() {
			return this.searchQuery.trim() !== '' || this.statusFilter !== 'all' || this.sortKey !== 'default'
		},
	},
	async mounted() {
		this.updateNarrowState()
		window.addEventListener('resize', this.updateNarrowState)

		await this.loadContext()
		if (!this.hasProjectAccess) {
			return
		}

		if (this.context?.organizationRole === 'member') {
			this.projectScope = 'my'
		}
		await this.loadProjects()
	},
	beforeDestroy() {
		window.removeEventListener('resize', this.updateNarrowState)
	},
	methods: {
		getProjectProfileDraftFromSelected() {
			const selected = this.selectedProject || {}
			return {
				client_name: selected.client_name || '',
				client_role: selected.client_role || '',
				client_phone: selected.client_phone || '',
				client_email: selected.client_email || '',
				client_address: selected.client_address || '',
				loc_street: selected.loc_street || '',
				loc_city: selected.loc_city || '',
				loc_zip: selected.loc_zip || '',
			}
		},
		resetProjectProfileEditor(clearMessage = true) {
			this.showProjectProfileModal = false
			this.projectProfileSaving = false
			this.projectProfileError = ''
			if (clearMessage) {
				this.projectProfileMessage = ''
			}
			this.projectProfileDraft = {
				client_name: '',
				client_role: '',
				client_phone: '',
				client_email: '',
				client_address: '',
				loc_street: '',
				loc_city: '',
				loc_zip: '',
			}
		},
		startProjectProfileEdit() {
			if (!this.canManageProjects || !this.selectedProject) {
				return
			}

			this.projectProfileError = ''
			this.projectProfileMessage = ''
			this.projectProfileDraft = this.getProjectProfileDraftFromSelected()
			this.showProjectProfileModal = true
		},
		cancelProjectProfileEdit() {
			this.projectProfileError = ''
			this.showProjectProfileModal = false
		},
		async saveProjectProfile() {
			if (!this.selectedProject) {
				return
			}

			const projectId = Number(this.selectedProject.id)
			if (!Number.isFinite(projectId) || projectId <= 0) {
				return
			}

			this.projectProfileSaving = true
			this.projectProfileError = ''
			this.projectProfileMessage = ''

			const payload = {
				client_name: this.projectProfileDraft.client_name,
				client_role: this.projectProfileDraft.client_role,
				client_phone: this.projectProfileDraft.client_phone,
				client_email: this.projectProfileDraft.client_email,
				client_address: this.projectProfileDraft.client_address,
				loc_street: this.projectProfileDraft.loc_street,
				loc_city: this.projectProfileDraft.loc_city,
				loc_zip: this.projectProfileDraft.loc_zip,
			}

			try {
				const updated = await projectsService.update(projectId, payload)

				if (updated && typeof updated === 'object') {
					this.selectedProject = {
						...this.selectedProject,
						...updated,
					}
				} else {
					this.selectedProject = {
						...this.selectedProject,
						...payload,
					}
				}

				const projectIndex = this.projects.findIndex((project) => Number(project.id) === projectId)
				if (projectIndex !== -1) {
					this.projects.splice(projectIndex, 1, {
						...this.projects[projectIndex],
						...this.selectedProject,
					})
				}

				this.projectProfileDraft = this.getProjectProfileDraftFromSelected()
				this.showProjectProfileModal = false
				this.projectProfileMessage = 'Client and location details updated.'
			} catch (error) {
				this.projectProfileError = error?.response?.data?.message || 'Could not update client and location details.'
			} finally {
				this.projectProfileSaving = false
			}
		},
		updateNarrowState() {
			this.isNarrow = window.matchMedia ? window.matchMedia('(max-width: 900px)').matches : (window.innerWidth <= 900)
			if (!this.isNarrow) {
				this.mobilePane = 'list'
				return
			}
			this.mobilePane = this.selectedProjectId ? 'details' : 'list'
		},
		clearFilters() {
			this.searchQuery = ''
			this.statusFilter = 'all'
			this.sortKey = 'default'
		},
		statusPillClass(status) {
			const normalized = Number(status)
			if (normalized === 1) {
				return 'projects-home__status-pill--active'
			}
			if (normalized === 0) {
				return 'projects-home__status-pill--archived'
			}
			return ''
		},
		setActiveTab(tab) {
			this.activeTab = tab
			if (!this.selectedProjectId) {
				return
			}
			const projectId = Number(this.selectedProjectId)
			if (!Number.isFinite(projectId) || projectId <= 0) {
				return
			}
			if (tab === 'members') {
				this.ensureMembersLoaded(projectId)
			}
			if (tab === 'files') {
				this.ensureFilesLoaded(projectId)
			}
		},
		async ensureMembersLoaded(projectId) {
			if (this.loadedMembersProjectId === projectId && !this.membersError) {
				return
			}
			await this.loadProjectMembers(projectId)
			this.loadedMembersProjectId = projectId
		},
		async ensureFilesLoaded(projectId) {
			if (this.loadedFilesProjectId === projectId && !this.filesError) {
				return
			}
			await this.loadProjectFiles(projectId)
			this.loadedFilesProjectId = projectId
		},
		isArchivedStatus(status) {
			return Number(status) === 0
		},
		openArchiveDialog() {
			if (!this.selectedProject) {
				return
			}
			const currentlyArchived = this.isArchivedStatus(this.selectedProject.status)
			this.archiveDialogAction = currentlyArchived ? 'restore' : 'archive'
			this.showArchiveDialog = true
		},
		async executeArchiveAction() {
			if (!this.selectedProject) {
				this.showArchiveDialog = false
				return
			}
			const projectId = Number(this.selectedProject.id)
			if (!Number.isFinite(projectId) || projectId <= 0) {
				this.showArchiveDialog = false
				return
			}

			const currentlyArchived = this.isArchivedStatus(this.selectedProject.status)
			const nextStatus = currentlyArchived ? 1 : 0

			this.showArchiveDialog = false
			this.statusUpdateError = ''
			this.statusUpdating = true
			try {
				await projectsService.update(projectId, { status: nextStatus })
				this.selectedProject.status = nextStatus
				await this.loadProjects()
			} catch (error) {
				this.statusUpdateError = error?.response?.data?.message || 'Could not update project status.'
			} finally {
				this.statusUpdating = false
			}
		},
		async loadContext() {
			this.contextError = ''
			try {
				this.context = await projectsService.context()
			} catch (error) {
				this.context = null
				this.contextError = error?.response?.data?.message || 'Unable to load project context.'
			}
		},
		async loadProjects() {
			const previousSelectedProjectId = this.selectedProjectId
			this.loading = true
			try {
				const userId = this.context?.userId || null
				if (this.isOrganizationAdmin && this.projectScope === 'my' && userId) {
					this.projects = await projectsService.fetchProjectsByUser(userId)
				} else {
					this.projects = await projectsService.list()
				}

				if (previousSelectedProjectId !== null) {
					const stillExists = this.projects.some((project) => project.id === previousSelectedProjectId)
					if (!stillExists) {
						this.resetProjectProfileEditor()
						this.resetMembersState()
						this.resetFilesState()
						this.selectedProject = null
						this.selectedProjectId = null
						this.activeTab = 'overview'
						this.loadedMembersProjectId = null
						this.loadedFilesProjectId = null
						if (this.isNarrow) {
							this.mobilePane = 'list'
						}
					}
				}
			} finally {
				this.loading = false
			}
		},
		async selectProject(project) {
			this.activeTab = 'overview'
			this.statusUpdateError = ''
			this.memberInviteMessage = ''
			this.membersError = ''
			this.resetProjectProfileEditor()
			this.resetMembersState()
			this.resetFilesState()
			this.selectedProjectId = project.id
			this.selectedProject = await projectsService.get(project.id)
			this.projectProfileDraft = this.getProjectProfileDraftFromSelected()
			this.loadedMembersProjectId = null
			this.loadedFilesProjectId = null
			if (this.isNarrow) {
				this.mobilePane = 'details'
			}
		},
		startCreateProject() {
			this.showCreateModal = true
		},
		closeCreateModal() {
			this.showCreateModal = false
		},
		async handleProjectCreated(payload) {
			const createdProjectId = payload?.projectId ?? null
			await this.loadProjects()
			this.showCreateModal = false
			if (createdProjectId === null) {
				return
			}
			const createdProject = this.projects.find((project) => project.id === createdProjectId)
			if (createdProject) {
				await this.selectProject(createdProject)
			}
		},
		typeLabel(typeId) {
			const match = PROJECT_TYPES.find((type) => type.id === typeId)
			return match ? match.label : 'Unknown'
		},
		statusLabel(status) {
			const normalized = Number(status)
			if (normalized === 1) {
				return 'Active'
			}
			if (normalized === 0) {
				return 'Archived'
			}
			return 'Unknown'
		},
		async loadProjectFiles(projectId) {
			this.filesError = ''
			this.filesLoading = true
			try {
				this.projectFiles = await projectsService.getFiles(projectId)
			} catch (error) {
				this.filesError = 'Could not load project files.'
				this.projectFiles = { private: [], shared: [] }
			} finally {
				this.filesLoading = false
			}
		},
		resetFilesState() {
			this.filesError = ''
			this.filesLoading = false
			this.projectFiles = { private: [], shared: [] }
		},
		resetMembersState() {
			this.projectMembers = []
			this.membersLoading = false
			this.membersError = ''
			this.memberInviteSelection = null
			this.memberInviteOptions = []
			this.memberSearchLoading = false
			this.memberInviteLoading = false
			this.memberInviteMessage = ''
			if (this.memberSearchTimeout) {
				clearTimeout(this.memberSearchTimeout)
				this.memberSearchTimeout = null
			}
		},
		getMemberSearchOrganizationId() {
			const selectedProjectOrg = Number(this.selectedProject?.organization_id)
			if (Number.isFinite(selectedProjectOrg) && selectedProjectOrg > 0) {
				return selectedProjectOrg
			}

			const contextOrg = Number(this.context?.organizationId)
			if (Number.isFinite(contextOrg) && contextOrg > 0) {
				return contextOrg
			}

			return null
		},
		async loadProjectMembers(projectId) {
			this.membersLoading = true
			this.membersError = ''
			try {
				this.projectMembers = await projectsService.listMembers(projectId)
			} catch (error) {
				this.projectMembers = []
				this.membersError = error?.response?.data?.message || 'Could not load project members.'
			} finally {
				this.membersLoading = false
			}
		},
		searchMemberCandidates(query) {
			if (this.memberSearchTimeout) {
				clearTimeout(this.memberSearchTimeout)
			}

			if (!query || !this.selectedProject?.id) {
				this.memberInviteOptions = []
				this.memberSearchLoading = false
				return
			}

			this.memberSearchLoading = true
			this.memberSearchTimeout = setTimeout(async () => {
				try {
					const organizationId = this.getMemberSearchOrganizationId()
					if (this.loadedMembersProjectId !== Number(this.selectedProject?.id)) {
						await this.loadProjectMembers(Number(this.selectedProject?.id))
						this.loadedMembersProjectId = Number(this.selectedProject?.id)
					}
					const users = await projectsService.searchUsers(query, organizationId)
					const existingIds = new Set(this.projectMembers.map((member) => String(member.id)))
					this.memberInviteOptions = users
						.filter((user) => !existingIds.has(String(user.id)))
						.map((user) => ({
							id: user.id,
							label: user.displayName || user.label || user.id,
							subname: user.subname || user.id,
						}))
				} catch (error) {
					this.memberInviteOptions = []
					this.membersError = error?.response?.data?.message || 'Could not search organization users.'
				} finally {
					this.memberSearchLoading = false
				}
			}, 250)
		},
		async inviteSelectedMember() {
			if (!this.selectedProject?.id || !this.memberInviteSelection) {
				return
			}

			const userId = typeof this.memberInviteSelection === 'string'
				? this.memberInviteSelection
				: this.memberInviteSelection.id

			if (!userId) {
				return
			}

			this.memberInviteLoading = true
			this.memberInviteMessage = ''
			this.membersError = ''
			try {
				const result = await projectsService.addMember(this.selectedProject.id, String(userId))
				await this.loadProjectMembers(this.selectedProject.id)
				this.loadedMembersProjectId = Number(this.selectedProject.id)
				this.memberInviteMessage = result?.alreadyMember
					? 'This user is already a project member.'
					: 'Member added to project successfully.'
				this.memberInviteSelection = null
				this.memberInviteOptions = []
			} catch (error) {
				this.membersError = error?.response?.data?.message || 'Could not add member to project.'
			} finally {
				this.memberInviteLoading = false
			}
		},
		openDeck(project) {
			if (!project.boardId) {
				return
			}
			const url = generateUrl(`/apps/deck/#/board/${project.boardId}`)
			window.open(url, '_blank')
		},
		openFolder(project) {
			if (!project.folderPath) {
				return
			}
			const directory = project.folderPath.startsWith('/')
				? project.folderPath
				: `/${project.folderPath}`
			const url = generateUrl(`/apps/files/?dir=${encodeURIComponent(directory)}`)
			window.open(url, '_blank')
		},
		openWhiteboard(project) {
			if (!project?.white_board_id) {
				return
			}
			if (this.activeTab !== 'whiteboard') {
				this.setActiveTab('whiteboard')
			}
			this.$nextTick(() => {
				const component = this.$refs.whiteboardBoard
				if (component && typeof component.openOverlay === 'function') {
					component.openOverlay()
					return
				}
				const url = generateUrl(`/apps/files/f/${encodeURIComponent(String(project.white_board_id))}?openfile=true`)
				window.open(url, '_blank')
			})
		},
		downloadProject(project) {
			if (!project.folderPath) {
				return
			}
			const path = this.normalizedPath(project.folderPath)
			const downloadUrl = new URL(webdavClient.getFileDownloadLink(path))
			downloadUrl.searchParams.append('accept', 'zip')
			this.triggerDownload(downloadUrl.href)
		},
		triggerDownload(href) {
			const link = document.createElement('a')
			link.href = href
			link.style.display = 'none'
			document.body.appendChild(link)
			link.click()
			link.remove()
		},
		normalizedPath(path) {
			const parts = path.split('/')
			if (parts.length >= 3) {
				const tmp = parts[1]
				parts[1] = parts[2]
				parts[2] = tmp
			}
			return parts.join('/')
		},
	},
}
</script>

<style scoped>
.projects-home-empty {
	display: flex;
	align-items: center;
	justify-content: center;
	padding: 48px 16px;
	min-height: calc(100vh - 90px);
}

.projects-home {
	display: grid;
	grid-template-columns: minmax(320px, 380px) minmax(0, 1fr);
	gap: 16px;
	width: 100%;
	padding: 16px;
	min-height: calc(100vh - 90px);
	box-sizing: border-box;
}

.projects-home__list-pane,
.projects-home__details-pane {
	border: 1px solid var(--color-border);
	border-radius: 12px;
	background: var(--color-main-background);
	overflow: hidden;
}

.projects-home__list-pane {
	display: flex;
	flex-direction: column;
}

.projects-home__header {
	display: flex;
	justify-content: space-between;
	align-items: flex-start;
	gap: 12px;
	padding: 16px;
	border-bottom: 1px solid var(--color-border);
}

.projects-home__header-left {
	min-width: 0;
}

.projects-home__header-meta {
	margin-top: 10px;
	display: flex;
	gap: 8px;
	flex-wrap: wrap;
}

.projects-home__title {
	margin: 0;
	font-size: 20px;
}

.projects-home__subtitle {
	margin: 4px 0 0;
	color: var(--color-text-maxcontrast);
	font-size: 13px;
}

.projects-home__controls {
	display: grid;
	gap: 10px;
	padding: 12px 16px;
	border-bottom: 1px solid var(--color-border);
}

.projects-home__control-label {
	font-size: 12px;
	color: var(--color-text-maxcontrast);
}

.projects-home__scope-row {
	display: grid;
	grid-template-columns: auto 1fr;
	align-items: center;
	gap: 10px;
}

.projects-home__scope-pill {
	display: inline-flex;
	align-items: center;
	padding: 4px 10px;
	border-radius: 999px;
	background: var(--color-background-hover);
	border: 1px solid var(--color-border-dark);
	font-size: 12px;
	color: var(--color-text-maxcontrast);
}

.projects-home__filters-row {
	display: grid;
	grid-template-columns: 1fr 1fr auto;
	gap: 10px;
	align-items: end;
}

.projects-home__filter {
	display: grid;
	gap: 6px;
}

.projects-home__clear {
	height: 36px;
}

.projects-home__count-row {
	font-size: 12px;
	color: var(--color-text-maxcontrast);
}

.projects-home__filter-select {
	border: 1px solid var(--color-border-dark);
	border-radius: 8px;
	padding: 8px 10px;
	font: inherit;
	background: var(--color-main-background);
	color: var(--color-main-text);
}

.projects-home__list {
	flex: 1;
	min-height: 0;
	overflow: auto;
}

.projects-home__items {
	list-style: none;
	padding: 0;
	margin: 0;
}

.projects-home__project-item {
	width: 100%;
	text-align: left;
	border: 0;
	background: transparent;
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
	padding: 12px 14px;
	cursor: pointer;
	border-bottom: 1px solid var(--color-border);
	position: relative;
}

.projects-home__project-item:hover {
	background: var(--color-background-hover);
}

.projects-home__project-item:focus-visible {
	outline: 2px solid var(--color-primary-element);
	outline-offset: -2px;
}

.projects-home__project-item--active {
	background: var(--color-primary-element-light);
}

.projects-home__project-item--active::before {
	content: '';
	position: absolute;
	left: 0;
	top: 10px;
	bottom: 10px;
	width: 3px;
	border-radius: 99px;
	background: var(--color-primary-element);
}

.projects-home__project-main {
	min-width: 0;
	display: grid;
	gap: 6px;
}

.projects-home__project-title-row {
	display: flex;
	align-items: center;
	gap: 8px;
	min-width: 0;
}

.projects-home__project-name {
	font-weight: 600;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

.projects-home__status-pill {
	padding: 2px 8px;
	border-radius: 999px;
	font-size: 11px;
	background: var(--color-background-dark);
	color: var(--color-main-text);
}

.projects-home__status-pill--active {
	background: rgba(30, 127, 45, 0.12);
	border: 1px solid rgba(30, 127, 45, 0.25);
}

.projects-home__status-pill--archived {
	background: rgba(120, 120, 120, 0.14);
	border: 1px solid rgba(120, 120, 120, 0.22);
}

.projects-home__project-meta {
	display: flex;
	align-items: center;
	gap: 6px;
	font-size: 12px;
	color: var(--color-text-maxcontrast);
}

.projects-home__quick-actions {
	display: inline-flex;
	align-items: center;
	gap: 6px;
	opacity: 0.8;
}

.projects-home__project-item:hover .projects-home__quick-actions,
.projects-home__project-item:focus-within .projects-home__quick-actions {
	opacity: 1;
}

.projects-home__quick-action {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 28px;
	height: 28px;
	border-radius: 8px;
	border: 1px solid var(--color-border-dark);
	background: var(--color-main-background);
	color: var(--color-main-text);
	cursor: pointer;
}

.projects-home__quick-action:disabled {
	opacity: 0.5;
	cursor: not-allowed;
}

.projects-home__details-pane {
	padding: 16px;
	display: flex;
	flex-direction: column;
	overflow: auto;
}

.projects-home__details-content {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.projects-home__hero {
	display: grid;
	gap: 10px;
	padding: 16px;
	border-radius: 14px;
	background:
		radial-gradient(circle at 0% 0%, rgba(36, 153, 255, 0.18), transparent 55%),
		radial-gradient(circle at 100% 20%, rgba(255, 166, 0, 0.2), transparent 40%),
		var(--color-main-background);
	border: 1px solid var(--color-border-dark);
}

.projects-home__hero-mobile {
	display: flex;
}

.projects-home__back {
	--border-radius: 10px;
}

.projects-home__hero-main {
	display: flex;
	justify-content: space-between;
	align-items: flex-start;
	gap: 14px;
}

.projects-home__details-title {
	margin: 0;
	font-size: 22px;
}

.projects-home__details-subtitle {
	margin: 0;
	color: var(--color-text-maxcontrast);
}

.projects-home__tabs {
	display: flex;
	gap: 6px;
	flex-wrap: wrap;
	padding: 8px 2px 0;
}

.projects-home__tab {
	appearance: none;
	border: 1px solid var(--color-border-dark);
	background: var(--color-main-background);
	color: var(--color-main-text);
	border-radius: 999px;
	padding: 6px 10px;
	font: inherit;
	font-size: 13px;
	cursor: pointer;
}

.projects-home__tab:hover {
	background: var(--color-background-hover);
}

.projects-home__tab--active {
	border-color: rgba(0, 0, 0, 0);
	background: var(--color-primary-element-light);
}

.projects-home__tab-panel {
	padding-top: 4px;
}

.projects-home__tab-section {
	border: 1px solid var(--color-border-dark);
	border-radius: 12px;
	background: var(--color-main-background);
	padding: 14px;
}

.projects-home__tab-section-head {
	margin-bottom: 10px;
}

.projects-home__section-title {
	margin: 0;
	font-size: 15px;
	font-weight: 700;
}

.projects-home__tab-top {
	display: flex;
	justify-content: flex-end;
	margin-bottom: 10px;
}

.projects-home__detail-grid {
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: 14px;
}

.projects-home__card {
	display: grid;
	gap: 10px;
	padding: 14px;
	background: var(--color-main-background);
	border: 1px solid var(--color-border-dark);
	border-radius: 12px;
	box-shadow: 0 2px 10px rgba(0, 0, 0, 0.04);
}

.projects-home__card-title {
	margin: 0;
	font-size: 15px;
	font-weight: 700;
}

.projects-home__card-head {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 8px;
}

.projects-home__kv {
	display: grid;
	gap: 4px;
}

.projects-home__label {
	font-size: 12px;
	color: var(--color-text-maxcontrast);
	margin-bottom: 0;
	text-transform: uppercase;
	letter-spacing: 0.04em;
}

.projects-home__links {
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
}

.projects-home__profile-actions {
	display: flex;
	align-items: center;
	gap: 8px;
	flex-wrap: wrap;
	margin-top: 4px;
}

.projects-home__profile-modal {
	display: grid;
	gap: 14px;
	padding: 16px;
}

.projects-home__profile-modal-title {
	margin: 0;
	font-size: 18px;
	font-weight: 700;
}

.projects-home__profile-modal-subtitle {
	margin: -6px 0 0;
	font-size: 13px;
}

.projects-home__profile-grid {
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: 10px;
}

.projects-home__member-invite-row {
	display: grid;
	grid-template-columns: minmax(0, 1fr) auto;
	gap: 8px;
	align-items: end;
}

.projects-home__members-list {
	list-style: none;
	padding: 0;
	margin: 12px 0 0;
	display: grid;
	gap: 8px;
}

.projects-home__member-item {
	display: flex;
	justify-content: space-between;
	align-items: center;
	gap: 10px;
	padding: 8px 10px;
	border: 1px solid var(--color-border-dark);
	border-radius: 10px;
	background: var(--color-background-hover);
}

.projects-home__member-main {
	display: grid;
	gap: 2px;
	min-width: 0;
}

.projects-home__member-name {
	font-weight: 600;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

.projects-home__member-meta {
	font-size: 12px;
	color: var(--color-text-maxcontrast);
}

.projects-home__member-badges {
	display: flex;
	gap: 6px;
	flex-wrap: wrap;
	justify-content: flex-end;
}

.projects-home__member-badge {
	padding: 2px 8px;
	border-radius: 999px;
	border: 1px solid var(--color-border-dark);
	background: var(--color-main-background);
	font-size: 11px;
	font-weight: 600;
}

.projects-home__member-badge--muted {
	font-weight: 500;
	color: var(--color-text-maxcontrast);
}

.projects-home__muted {
	color: var(--color-text-maxcontrast);
}

.projects-home__hero-badges {
	display: flex;
	gap: 8px;
	flex-wrap: wrap;
	justify-content: flex-end;
}

.projects-home__hero-aside {
	display: flex;
	flex-direction: column;
	align-items: flex-end;
	gap: 10px;
}

.projects-home__hero-actions {
	display: flex;
	justify-content: flex-end;
}

.projects-home__inline-error {
	margin: 0;
	font-size: 12px;
	color: var(--color-error-text);
	text-align: right;
	max-width: 320px;
}

.projects-home__inline-error--left {
	text-align: left;
	max-width: none;
}

.projects-home__inline-success {
	margin: 10px 0 0;
	font-size: 12px;
	color: var(--color-success, #1e7f2d);
}

.projects-home__badge {
	padding: 5px 10px;
	border-radius: 999px;
	border: 1px solid var(--color-border-dark);
	background: var(--color-main-background);
	font-size: 12px;
	font-weight: 600;
}

.projects-home__centered {
	padding: 24px;
	color: var(--color-text-maxcontrast);
}

.projects-home__hero-actions :deep(.action-item) {
	--border-radius: 8px;
}

.projects-home__hero-actions :deep(.action-item__menutoggle) {
	background: var(--color-main-background);
	border: 1px solid var(--color-border-dark);
	border-radius: 8px;
	padding: 6px 12px;
}

.projects-home__hero-actions :deep(.action-item__menutoggle:hover) {
	background: var(--color-background-hover);
}

@media (max-width: 900px) {
	.projects-home {
		grid-template-columns: 1fr;
		min-height: auto;
	}

	.projects-home__detail-grid {
		grid-template-columns: 1fr;
	}

	.projects-home__hero-main {
		flex-direction: column;
	}

	.projects-home__hero-aside {
		align-items: flex-start;
	}

	.projects-home__hero-badges,
	.projects-home__hero-actions {
		justify-content: flex-start;
	}

	.projects-home__inline-error {
		text-align: left;
	}

	.projects-home__member-invite-row {
		grid-template-columns: 1fr;
	}

	.projects-home__profile-grid {
		grid-template-columns: 1fr;
	}

	.projects-home__member-item {
		flex-direction: column;
		align-items: flex-start;
	}

	.projects-home__member-badges {
		justify-content: flex-start;
	}
}
</style>

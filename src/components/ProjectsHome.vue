<template>
	<div v-if="!hasProjectAccess" class="projects-home-empty">
		<NcEmptyContent
			name="No organization assigned"
			description="Ask your administrator to assign your account to an organization before creating projects.">
			<template #icon>
				<OfficeBuilding :size="44" />
			</template>
		</NcEmptyContent>
	</div>
	<div v-else class="projects-home">
		<section class="projects-home__list-pane">
			<header class="projects-home__header">
				<div>
					<h2 class="projects-home__title">Projects</h2>
					<p class="projects-home__subtitle">Browse and manage project spaces</p>
				</div>
				<NcButton type="primary" @click="startCreateProject">
					<template #icon>
						<Plus :size="18" />
					</template>
					New project
				</NcButton>
			</header>

			<div class="projects-home__controls">
				<div class="projects-home__scope-row">
					<span class="projects-home__scope-pill">{{ scopeLabel }}</span>
					<select
						v-if="isOrganizationAdmin"
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

				<select v-model="statusFilter" class="projects-home__filter-select" aria-label="Filter projects">
					<option value="all">All statuses</option>
					<option value="active">Active only</option>
					<option value="archived">Archived only</option>
				</select>
			</div>

			<div class="projects-home__list">
				<div v-if="loading" class="projects-home__centered">Loading projects...</div>
				<NcEmptyContent
					v-else-if="filteredProjects.length === 0"
					name="No projects yet"
					description="Create a project to get started.">
					<template #icon>
						<FolderOutline :size="36" />
					</template>
				</NcEmptyContent>
				<ul v-else class="projects-home__items">
					<li v-for="project in filteredProjects" :key="project.id">
						<button
							type="button"
							class="projects-home__project-item"
							:class="{ 'projects-home__project-item--active': selectedProjectId === project.id }"
							@click="selectProject(project)">
							<div class="projects-home__project-main">
								<div class="projects-home__project-title-row">
									<FolderOutline :size="20" />
									<span class="projects-home__project-name">{{ project.name }}</span>
									<span class="projects-home__status-pill">{{ statusLabel(project.status) }}</span>
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

		<section class="projects-home__details-pane">
			<ProjectCreator
				v-if="isCreateMode"
				embedded
				@created="handleProjectCreated"
				@cancel="isCreateMode = false" />

			<div v-else-if="selectedProject" class="projects-home__details-content">
				<div class="projects-home__hero">
					<div>
						<h2 class="projects-home__details-title">{{ selectedProject.name || 'Unnamed project' }}</h2>
						<p class="projects-home__details-subtitle">{{ selectedProject.description || 'No description provided yet.' }}</p>
					</div>
					<div class="projects-home__hero-badges">
						<span class="projects-home__badge">{{ statusLabel(selectedProject.status) }}</span>
						<span class="projects-home__badge">{{ typeLabel(selectedProject.type) }}</span>
						<span class="projects-home__badge">#{{ selectedProject.number || 'N/A' }}</span>
					</div>
				</div>

				<div class="projects-home__detail-grid">
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
						<h3 class="projects-home__card-title">Client information</h3>
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

					<article class="projects-home__card projects-home__card--full">
						<div class="projects-home__card-header-row">
							<h3 class="projects-home__card-title">Files</h3>
							<NcButton type="secondary" :disabled="!selectedProject.folderPath" @click="downloadProject(selectedProject)">
								<template #icon>
									<Download :size="18" />
								</template>
								Download ZIP
							</NcButton>
						</div>

						<div v-if="filesLoading" class="projects-home__muted">Loading file structure...</div>
						<div v-else-if="filesError" class="projects-home__muted">{{ filesError }}</div>
						<div v-else class="projects-home__files-grid">
							<div>
								<div class="projects-home__label">Shared files</div>
								<div class="projects-home__file-stat">{{ sharedFileCount }} files</div>
								<ul class="projects-home__file-list">
									<li v-for="node in topNodes(projectFiles.shared)" :key="`shared-${node.path}`">
										{{ node.name }}
									</li>
								</ul>
							</div>
							<div>
								<div class="projects-home__label">Private files</div>
								<div class="projects-home__file-stat">{{ privateFileCount }} files</div>
								<ul class="projects-home__file-list">
									<li v-for="node in topNodes(projectFiles.private)" :key="`private-${node.path}`">
										{{ node.name }}
									</li>
								</ul>
							</div>
						</div>
					</article>
				</div>
			</div>

			<NcEmptyContent
				v-else
				name="Select a project"
				description="Choose a project from the list to view details.">
				<template #icon>
					<Details :size="36" />
				</template>
			</NcEmptyContent>
		</section>
	</div>
</template>

<script>
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import Details from 'vue-material-design-icons/Details.vue'
import Download from 'vue-material-design-icons/Download.vue'
import EyeOutline from 'vue-material-design-icons/EyeOutline.vue'
import FolderOutline from 'vue-material-design-icons/FolderOutline.vue'
import Magnify from 'vue-material-design-icons/Magnify.vue'
import OfficeBuilding from 'vue-material-design-icons/OfficeBuilding.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import { generateRemoteUrl, generateUrl } from '@nextcloud/router'
import { createClient } from 'webdav'
import { PROJECT_TYPES } from '../macros/project-types'
import { ProjectsService } from '../Services/projects'
import ProjectCreator from './ProjectCreator.vue'

const projectsService = ProjectsService.getInstance()
const webdavClient = createClient(generateRemoteUrl('dav'))

export default {
	name: 'ProjectsHome',
	components: {
		Details,
		Download,
		EyeOutline,
		FolderOutline,
		Magnify,
		NcButton,
		NcEmptyContent,
		NcTextField,
		OfficeBuilding,
		Plus,
		ProjectCreator,
	},
	data() {
		return {
			context: null,
			filesError: '',
			filesLoading: false,
			isCreateMode: false,
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
		scopeLabel() {
			if (this.context?.isGlobalAdmin) {
				return 'Global admin view'
			}

			if (this.context?.organizationRole === 'admin') {
				return 'Organization admin view'
			}

			return 'My projects view'
		},
		privateFileCount() {
			return this.countFiles(this.projectFiles.private)
		},
		sharedFileCount() {
			return this.countFiles(this.projectFiles.shared)
		},
		filteredProjects() {
			const search = this.searchQuery.trim().toLowerCase()
			return this.projects.filter((project) => {
				if (this.statusFilter === 'active' && project.status !== 1) {
					return false
				}
				if (this.statusFilter === 'archived' && project.status !== 0) {
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
	},
	async mounted() {
		await this.loadContext()
		if (!this.hasProjectAccess) {
			return
		}

		if (this.context?.organizationRole === 'member') {
			this.projectScope = 'my'
		}
		await this.loadProjects()
	},
	methods: {
		async loadContext() {
			this.context = await projectsService.context()
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
						this.selectedProject = null
						this.selectedProjectId = null
					}
				}
			} finally {
				this.loading = false
			}
		},
		async selectProject(project) {
			this.isCreateMode = false
			this.selectedProjectId = project.id
			this.selectedProject = await projectsService.get(project.id)
			await this.loadProjectFiles(project.id)
		},
		startCreateProject() {
			this.isCreateMode = true
			this.projectFiles = { private: [], shared: [] }
			this.selectedProject = null
			this.selectedProjectId = null
		},
		async handleProjectCreated(payload) {
			const createdProjectId = payload?.projectId ?? null
			await this.loadProjects()
			this.isCreateMode = false
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
			if (status === 1) {
				return 'Active'
			}
			if (status === 0) {
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
		topNodes(nodes) {
			return (nodes || []).slice(0, 6)
		},
		countFiles(nodes) {
			const list = nodes || []
			let count = 0
			for (const node of list) {
				count += this.countFilesFromNode(node)
			}
			return count
		},
		countFilesFromNode(node) {
			if (!node) {
				return 0
			}

			if (node.type === 'file') {
				return 1
			}

			const children = node.children || []
			let count = 0
			for (const child of children) {
				count += this.countFilesFromNode(child)
			}

			return count
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
			if (!project.white_board_id) {
				return
			}

			const url = generateUrl(`/apps/whiteboard/${project.white_board_id}`)
			window.open(url, '_blank')
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
				[parts[1], parts[2]] = [parts[2], parts[1]]
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
	grid-template-columns: minmax(300px, 360px) minmax(0, 1fr);
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

.projects-home__scope-row {
	display: flex;
	align-items: center;
	justify-content: space-between;
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
}

.projects-home__project-item:hover {
	background: var(--color-background-hover);
}

.projects-home__project-item--active {
	background: var(--color-primary-element-light);
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
	gap: 16px;
}

.projects-home__hero {
	display: flex;
	justify-content: space-between;
	align-items: flex-start;
	gap: 14px;
	padding: 16px;
	border-radius: 14px;
	background:
		radial-gradient(circle at 0% 0%, rgba(36, 153, 255, 0.18), transparent 55%),
		radial-gradient(circle at 100% 20%, rgba(255, 166, 0, 0.2), transparent 40%),
		var(--color-main-background);
	border: 1px solid var(--color-border-dark);
}

.projects-home__details-title {
	margin: 0;
	font-size: 22px;
}

.projects-home__details-subtitle {
	margin: 0;
	color: var(--color-text-maxcontrast);
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

.projects-home__card--full {
	grid-column: 1 / -1;
}

.projects-home__card-title {
	margin: 0;
	font-size: 15px;
	font-weight: 700;
}

.projects-home__card-header-row {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 10px;
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

.projects-home__files-grid {
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: 16px;
	padding-top: 6px;
}

.projects-home__file-list {
	margin: 8px 0 0;
	padding-left: 18px;
	color: var(--color-text-maxcontrast);
}

.projects-home__file-list li {
	margin-bottom: 4px;
	word-break: break-word;
}

.projects-home__file-stat {
	font-weight: 600;
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

@media (max-width: 900px) {
	.projects-home {
		grid-template-columns: 1fr;
		min-height: auto;
	}

	.projects-home__detail-grid,
	.projects-home__files-grid {
		grid-template-columns: 1fr;
	}

	.projects-home__hero {
		flex-direction: column;
	}
}
</style>

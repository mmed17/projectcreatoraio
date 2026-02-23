<template>
	<div class="project-notes-list">
		<div class="project-notes-list__header">
			<div class="project-notes-list__tabs-search">
				<div class="project-notes-list__tabs">
					<button
						type="button"
						class="project-notes-list__tab"
						:class="{ 'project-notes-list__tab--active': activeTab === 'public' }"
						@click="activeTab = 'public'">
						<Earth :size="18" />
						<span>Public</span>
						<span v-if="publicNotes.length > 0" class="project-notes-list__tab-badge">
							{{ publicNotes.length }}
						</span>
					</button>
					<button
						type="button"
						class="project-notes-list__tab"
						:class="{ 'project-notes-list__tab--active': activeTab === 'private' }"
						@click="activeTab = 'private'">
						<Lock :size="18" />
						<span>Private</span>
						<span v-if="privateNotes.length > 0" class="project-notes-list__tab-badge">
							{{ privateNotes.length }}
						</span>
					</button>
				</div>
				<NcTextField
					v-model="searchQuery"
					placeholder="Search notes..."
					class="project-notes-list__search">
					<template #icon>
						<Magnify :size="20" />
					</template>
				</NcTextField>
			</div>
			<NcButton type="secondary" :disabled="!canCreateNote" @click="openCreateModal">
				<template #icon>
					<Plus :size="20" />
				</template>
				Add note
			</NcButton>
		</div>

		<div v-if="loading" class="project-notes-list__loading">
			<NcLoadingIcon :size="48" />
			<span>Loading your notes...</span>
		</div>

		<div v-else-if="filteredNotes.length === 0" class="project-notes-list__empty">
			<div class="project-notes-list__empty-icon-wrapper">
				<FileDocumentOutline :size="64" />
				<Magnify v-if="searchQuery" :size="28" class="project-notes-list__empty-search-overlay" />
			</div>
			<p class="project-notes-list__empty-title">
				{{ searchQuery ? 'No matches found' : `No ${activeTab} notes yet` }}
			</p>
			<p class="project-notes-list__empty-subtitle">
				{{ searchQuery
					? `We couldn't find any notes matching "${searchQuery}"`
					: (activeTab === 'private' && !canCreateNote
						? 'Private notes are not available for this project'
						: 'Create your first note to start documenting this project')
				}}
			</p>
			<NcButton
				v-if="searchQuery"
				type="tertiary"
				@click="searchQuery = ''">
				Clear search
			</NcButton>
		</div>

		<div v-else class="project-notes-list__grid">
			<div
				v-for="note in filteredNotes"
				:key="note.id"
				class="project-notes-list__note-card"
				@click="openEditModal(note)">
				<div class="project-notes-list__note-header">
					<div class="project-notes-list__note-title-group">
						<h4 class="project-notes-list__note-title">
							{{ note.title }}
						</h4>
						<span class="project-notes-list__note-date">
							{{ formatDate(note.updatedAt) }}
						</span>
					</div>
					<div class="project-notes-list__note-actions">
						<button
							type="button"
							class="project-notes-list__action-btn"
							title="Delete"
							@click.stop="confirmDelete(note)">
							<Delete :size="18" />
						</button>
					</div>
				</div>
				<div class="project-notes-list__note-content">
					<p class="project-notes-list__note-preview">
						{{ getPreview(note.content) }}
					</p>
				</div>
				<div class="project-notes-list__note-footer">
					<div class="project-notes-list__note-author">
						<div class="project-notes-list__author-avatar" :title="note.userId">
							{{ note.userId ? note.userId.charAt(0).toUpperCase() : '?' }}
						</div>
						<span class="project-notes-list__author-name">{{ note.userId }}</span>
					</div>
					<div class="project-notes-list__note-type" :class="`project-notes-list__note-type--${note.visibility}`">
						<Earth v-if="note.visibility === 'public'" :size="14" />
						<Lock v-else :size="14" />
						<span>{{ note.visibility }}</span>
					</div>
				</div>
			</div>
		</div>

		<CreateNoteModal
			:show="showCreateModal"
			:project-id="projectId"
			:visibility="activeTab"
			@close="closeCreateModal"
			@created="onNoteCreated" />

		<CreateNoteModal
			v-if="editingNote"
			:show="showEditModal"
			:project-id="projectId"
			:note="editingNote"
			:visibility="editingNote.visibility"
			@close="closeEditModal"
			@updated="onNoteUpdated" />
	</div>
</template>

<script>
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import Earth from 'vue-material-design-icons/Earth.vue'
import Lock from 'vue-material-design-icons/Lock.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import Delete from 'vue-material-design-icons/Delete.vue'
import Magnify from 'vue-material-design-icons/Magnify.vue'
import FileDocumentOutline from 'vue-material-design-icons/FileDocumentOutline.vue'
import CreateNoteModal from './CreateNoteModal.vue'
import { ProjectsService } from '../Services/projects.js'

const projectsService = ProjectsService.getInstance()

export default {
	name: 'ProjectNotesList',
	components: {
		NcButton,
		NcLoadingIcon,
		NcTextField,
		Earth,
		Lock,
		Plus,
		Delete,
		Magnify,
		FileDocumentOutline,
		CreateNoteModal,
	},
	props: {
		projectId: {
			type: Number,
			required: true,
		},
	},
	data() {
		return {
			loading: true,
			activeTab: 'public',
			searchQuery: '',
			publicNotes: [],
			privateNotes: [],
			privateAvailable: false,
			showCreateModal: false,
			showEditModal: false,
			editingNote: null,
		}
	},
	computed: {
		filteredNotes() {
			const notes = this.activeTab === 'public' ? this.publicNotes : this.privateNotes
			if (!this.searchQuery.trim()) {
				return notes
			}
			const query = this.searchQuery.toLowerCase()
			return notes.filter(note =>
				note.title.toLowerCase().includes(query)
				|| note.content.toLowerCase().includes(query),
			)
		},
		canCreateNote() {
			if (this.activeTab === 'public') {
				return true
			}
			return this.privateAvailable
		},
	},
	watch: {
		projectId: {
			immediate: true,
			handler(newId) {
				if (newId) {
					this.loadNotes()
				}
			},
		},
	},
	methods: {
		async loadNotes() {
			this.loading = true
			try {
				const result = await projectsService.listNotes(this.projectId)
				if (result) {
					this.publicNotes = result.notes?.public || []
					this.privateNotes = result.notes?.private || []
					this.privateAvailable = result.canCreatePrivate || result.notes?.private_available || false
				}
			} catch (error) {
				console.error('Failed to load notes:', error)
			} finally {
				this.loading = false
			}
		},
		formatDate(dateString) {
			if (!dateString) return ''
			const date = new Date(dateString)
			const now = new Date()
			const diffDays = Math.floor((now - date) / (1000 * 60 * 60 * 24))

			if (diffDays === 0) {
				const diffHours = Math.floor((now - date) / (1000 * 60 * 60))
				if (diffHours === 0) {
					const diffMinutes = Math.floor((now - date) / (1000 * 60))
					return diffMinutes <= 1 ? 'Just now' : `${diffMinutes} minutes ago`
				}
				return `${diffHours} h ago`
			}
			if (diffDays === 1) return 'Yesterday'
			if (diffDays < 7) return `${diffDays} d ago`

			return date.toLocaleDateString(undefined, {
				month: 'short',
				day: 'numeric',
				year: date.getFullYear() !== now.getFullYear() ? 'numeric' : undefined,
			})
		},
		getPreview(content) {
			if (!content) return 'No content'
			// Strip HTML tags and markdown symbols
			const plainText = content
				.replace(/<[^>]*>?/gm, ' ') // Strip HTML
				.replace(/[#*_`[\]()]/g, '') // Strip Markdown symbols
				.replace(/\s+/g, ' ') // Normalize whitespace
				.trim()
			return plainText.length > 180 ? plainText.slice(0, 180) + '...' : plainText
		},
		openCreateModal() {
			this.showCreateModal = true
		},
		closeCreateModal() {
			this.showCreateModal = false
		},
		onNoteCreated(note) {
			if (note.visibility === 'public') {
				this.publicNotes.unshift(note)
			} else {
				this.privateNotes.unshift(note)
			}
			this.closeCreateModal()
		},
		openEditModal(note) {
			this.editingNote = note
			this.showEditModal = true
		},
		closeEditModal() {
			this.editingNote = null
			this.showEditModal = false
		},
		onNoteUpdated(updatedNote) {
			const noteArray = updatedNote.visibility === 'public' ? this.publicNotes : this.privateNotes
			const index = noteArray.findIndex(n => n.id === updatedNote.id)
			if (index !== -1) {
				this.$set(noteArray, index, updatedNote)
			}
			this.closeEditModal()
		},
		async confirmDelete(note) {
			if (!window.confirm(`Are you sure you want to delete "${note.title}"?`)) {
				return
			}

			try {
				await projectsService.deleteNote(this.projectId, note.id)
				if (note.visibility === 'public') {
					this.publicNotes = this.publicNotes.filter(n => n.id !== note.id)
				} else {
					this.privateNotes = this.privateNotes.filter(n => n.id !== note.id)
				}
			} catch (error) {
				console.error('Failed to delete note:', error)
				alert('Failed to delete note. Please try again.')
			}
		},
	},
}
</script>

<style scoped>
.project-notes-list {
	display: flex;
	flex-direction: column;
	gap: 24px;
	padding-bottom: 12px;
}

.project-notes-list__header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	gap: 32px;
}

.project-notes-list__tabs-search {
	display: flex;
	align-items: center;
	gap: 20px;
	flex: 1;
	min-width: 0;
}

.project-notes-list__tabs {
	display: flex;
	gap: 4px;
	background: var(--color-background-dark);
	border-radius: 14px;
	padding: 4px;
	flex-shrink: 0;
}

.project-notes-list__tab {
	display: flex;
	align-items: center;
	gap: 10px;
	padding: 10px 18px;
	border: none;
	background: transparent;
	color: var(--color-text-lighter);
	font-size: 13px;
	font-weight: 700;
	cursor: pointer;
	border-radius: 11px;
	transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.project-notes-list__tab:hover {
	background: var(--color-background-hover);
	color: var(--color-main-text);
}

.project-notes-list__tab--active {
	background: var(--color-main-background);
	color: var(--color-main-text);
	box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

.project-notes-list__tab-badge {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	min-width: 20px;
	height: 20px;
	padding: 0 6px;
	background: var(--color-primary-element);
	color: var(--color-primary-element-text);
	font-size: 11px;
	font-weight: 800;
	border-radius: 999px;
}

.project-notes-list__search {
	flex: 1;
	max-width: 360px;
	min-width: 180px;
	margin: 0 !important;
}

.project-notes-list__loading {
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 20px;
	padding: 80px 32px;
	color: var(--color-text-maxcontrast);
	font-weight: 600;
}

.project-notes-list__empty {
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 20px;
	padding: 80px 40px;
	background: var(--color-background-hover);
	border-radius: 24px;
	text-align: center;
}

.project-notes-list__empty-icon-wrapper {
	position: relative;
	color: var(--color-text-maxcontrast);
	opacity: 0.5;
	margin-bottom: 8px;
}

.project-notes-list__empty-search-overlay {
	position: absolute;
	bottom: -4px;
	right: -4px;
	background: var(--color-background-hover);
	border-radius: 50%;
	padding: 4px;
}

.project-notes-list__empty-title {
	margin: 0;
	font-size: 22px;
	font-weight: 800;
	color: var(--color-main-text);
}

.project-notes-list__empty-subtitle {
	margin: 0;
	font-size: 15px;
	color: var(--color-text-lighter);
	max-width: 380px;
	line-height: 1.6;
}

.project-notes-list__grid {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
	gap: 24px;
}

.project-notes-list__note-card {
	display: flex;
	flex-direction: column;
	background: var(--color-main-background);
	border: 1px solid var(--color-border);
	border-radius: 20px;
	cursor: pointer;
	transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
	overflow: hidden;
	height: 220px;
	position: relative;
}

.project-notes-list__note-card:hover {
	border-color: var(--color-primary-element);
	box-shadow: 0 12px 30px rgba(0, 0, 0, 0.08);
	transform: translateY(-6px);
}

.project-notes-list__note-header {
	display: flex;
	justify-content: space-between;
	align-items: flex-start;
	padding: 20px 20px 14px;
	gap: 12px;
}

.project-notes-list__note-title-group {
	display: flex;
	flex-direction: column;
	gap: 6px;
	min-width: 0;
}

.project-notes-list__note-title {
	margin: 0;
	font-size: 17px;
	font-weight: 800;
	color: var(--color-main-text);
	line-height: 1.3;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

.project-notes-list__note-date {
	font-size: 10px;
	font-weight: 700;
	color: var(--color-text-maxcontrast);
	text-transform: uppercase;
	letter-spacing: 0.06em;
}

.project-notes-list__note-content {
	padding: 0 20px;
	flex: 1;
	overflow: hidden;
	position: relative;
}

.project-notes-list__note-content::after {
	content: '';
	position: absolute;
	bottom: 0;
	left: 0;
	right: 0;
	height: 30px;
	background: linear-gradient(transparent, var(--color-main-background));
}

.project-notes-list__note-preview {
	margin: 0;
	font-size: 14px;
	color: var(--color-text-lighter);
	line-height: 1.6;
	display: -webkit-box;
	-webkit-line-clamp: 4;
	-webkit-box-orient: vertical;
	overflow: hidden;
}

.project-notes-list__note-footer {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: 14px 20px;
	background: var(--color-background-hover);
	border-top: 1px solid var(--color-border);
}

.project-notes-list__note-author {
	display: flex;
	align-items: center;
	gap: 10px;
	min-width: 0;
}

.project-notes-list__author-avatar {
	width: 26px;
	height: 26px;
	border-radius: 50%;
	background: var(--color-primary-element);
	color: var(--color-primary-element-text);
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 12px;
	font-weight: 800;
	flex-shrink: 0;
}

.project-notes-list__author-name {
	font-size: 13px;
	font-weight: 600;
	color: var(--color-text-lighter);
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

.project-notes-list__note-type {
	display: flex;
	align-items: center;
	gap: 6px;
	font-size: 10px;
	font-weight: 800;
	text-transform: uppercase;
	letter-spacing: 0.06em;
	padding: 4px 8px;
	border-radius: 6px;
}

.project-notes-list__note-type--public {
	color: var(--color-success);
	background: var(--color-success-light);
}

.project-notes-list__note-type--private {
	color: var(--color-warning);
	background: var(--color-warning-light);
}

.project-notes-list__note-actions {
	display: flex;
	gap: 6px;
	opacity: 0;
	transition: opacity 0.2s ease;
}

.project-notes-list__note-card:hover .project-notes-list__note-actions {
	opacity: 1;
}

.project-notes-list__action-btn {
	display: flex;
	align-items: center;
	justify-content: center;
	width: 32px;
	height: 32px;
	border: 1px solid var(--color-border-dark);
	background: var(--color-main-background);
	color: var(--color-text-lighter);
	cursor: pointer;
	border-radius: 10px;
	transition: all 0.2s ease;
}

.project-notes-list__action-btn:hover {
	background: var(--color-error);
	color: white;
	border-color: var(--color-error);
	box-shadow: 0 4px 10px var(--color-error-light);
}

@media (max-width: 1000px) {
	.project-notes-list__header {
		flex-direction: column;
		align-items: stretch;
		gap: 20px;
	}

	.project-notes-list__tabs-search {
		flex-direction: column;
		align-items: stretch;
	}

	.project-notes-list__search {
		max-width: none;
	}

	.project-notes-list__tabs {
		width: 100%;
	}

	.project-notes-list__tab {
		flex: 1;
		justify-content: center;
	}
}

@media (max-width: 600px) {
	.project-notes-list__grid {
		grid-template-columns: 1fr;
	}
}
</style>

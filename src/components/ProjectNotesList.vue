<template>
	<div class="project-notes-list">
		<div class="project-notes-list__header">
			<div class="project-notes-list__tabs">
				<button
					type="button"
					class="project-notes-list__tab"
					:class="{ 'project-notes-list__tab--active': activeTab === 'public' }"
					@click="activeTab = 'public'">
					<Earth :size="16" />
					<span>Public Notes</span>
					<span v-if="publicNotes.length > 0" class="project-notes-list__tab-badge">
						{{ publicNotes.length }}
					</span>
				</button>
				<button
					type="button"
					class="project-notes-list__tab"
					:class="{ 'project-notes-list__tab--active': activeTab === 'private' }"
					@click="activeTab = 'private'">
					<Lock :size="16" />
					<span>Private Notes</span>
					<span v-if="privateNotes.length > 0" class="project-notes-list__tab-badge">
						{{ privateNotes.length }}
					</span>
				</button>
			</div>
			<NcButton type="primary" :disabled="!canCreateNote" @click="openCreateModal">
				<template #icon>
					<Plus :size="16" />
				</template>
				Add note
			</NcButton>
		</div>

		<div v-if="loading" class="project-notes-list__loading">
			<NcLoadingIcon :size="32" />
			<span>Loading notes...</span>
		</div>

		<div v-else-if="filteredNotes.length === 0" class="project-notes-list__empty">
			<FileDocumentOutline :size="48" />
			<p class="project-notes-list__empty-title">No {{ activeTab }} notes yet</p>
			<p class="project-notes-list__empty-subtitle">
				{{ activeTab === 'private' && !canCreateNote
					? 'Private notes are not available for this project'
					: 'Click "Add note" to create your first note'
				}}
			</p>
		</div>

		<div v-else class="project-notes-list__grid">
			<div
				v-for="note in filteredNotes"
				:key="note.id"
				class="project-notes-list__note-card"
				@click="openEditModal(note)">
				<div class="project-notes-list__note-header">
					<h4 class="project-notes-list__note-title">{{ note.title }}</h4>
					<span class="project-notes-list__note-date">
						{{ formatDate(note.updatedAt) }}
					</span>
				</div>
				<p class="project-notes-list__note-preview">{{ getPreview(note.content) }}</p>
				<div class="project-notes-list__note-footer">
					<span class="project-notes-list__note-author">
						<Account :size="14" />
						{{ note.userId }}
					</span>
					<div class="project-notes-list__note-actions">
						<button
							type="button"
							class="project-notes-list__action-btn"
							@click.stop="openEditModal(note)"
							title="Edit">
							<Pencil :size="16" />
						</button>
						<button
							type="button"
							class="project-notes-list__action-btn project-notes-list__action-btn--danger"
							@click.stop="confirmDelete(note)"
							title="Delete">
							<Delete :size="16" />
						</button>
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
import Earth from 'vue-material-design-icons/Earth.vue'
import Lock from 'vue-material-design-icons/Lock.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import Delete from 'vue-material-design-icons/Delete.vue'
import Account from 'vue-material-design-icons/Account.vue'
import FileDocumentOutline from 'vue-material-design-icons/FileDocumentOutline.vue'
import CreateNoteModal from './CreateNoteModal.vue'
import { ProjectsService } from '../Services/projects'

const projectsService = ProjectsService.getInstance()

export default {
	name: 'ProjectNotesList',
	components: {
		NcButton,
		NcLoadingIcon,
		Earth,
		Lock,
		Plus,
		Pencil,
		Delete,
		Account,
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
			return this.activeTab === 'public' ? this.publicNotes : this.privateNotes
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
				return `${diffHours} hours ago`
			}
			if (diffDays === 1) return 'Yesterday'
			if (diffDays < 7) return `${diffDays} days ago`

			return date.toLocaleDateString('en-US', {
				month: 'short',
				day: 'numeric',
				year: date.getFullYear() !== now.getFullYear() ? 'numeric' : undefined,
			})
		},
		getPreview(content) {
			if (!content) return 'No content'
			const plainText = content.replace(/[#*_`\[\]\(\)]/g, '').trim()
			return plainText.length > 150 ? plainText.slice(0, 150) + '...' : plainText
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
	gap: 16px;
}

.project-notes-list__header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	gap: 16px;
	flex-wrap: wrap;
}

.project-notes-list__tabs {
	display: flex;
	gap: 4px;
	background: var(--color-background-hover);
	border-radius: 8px;
	padding: 4px;
}

.project-notes-list__tab {
	display: flex;
	align-items: center;
	gap: 6px;
	padding: 8px 16px;
	border: none;
	background: transparent;
	color: var(--color-text-lighter);
	font-size: 14px;
	font-weight: 500;
	cursor: pointer;
	border-radius: 6px;
	transition: all 0.2s ease;
}

.project-notes-list__tab:hover {
	background: var(--color-background-dark);
	color: var(--color-main-text);
}

.project-notes-list__tab--active {
	background: var(--color-main-background);
	color: var(--color-main-text);
	box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
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
	font-weight: 600;
	border-radius: 999px;
}

.project-notes-list__loading {
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 12px;
	padding: 48px;
	color: var(--color-text-maxcontrast);
}

.project-notes-list__empty {
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 12px;
	padding: 48px;
	color: var(--color-text-maxcontrast);
	text-align: center;
}

.project-notes-list__empty-title {
	margin: 0;
	font-size: 16px;
	font-weight: 600;
	color: var(--color-main-text);
}

.project-notes-list__empty-subtitle {
	margin: 0;
	font-size: 14px;
	max-width: 300px;
}

.project-notes-list__grid {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
	gap: 16px;
}

.project-notes-list__note-card {
	display: flex;
	flex-direction: column;
	gap: 12px;
	padding: 16px;
	background: var(--color-main-background);
	border: 1px solid var(--color-border);
	border-radius: 12px;
	cursor: pointer;
	transition: all 0.2s ease;
}

.project-notes-list__note-card:hover {
	border-color: var(--color-primary-element);
	box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
	transform: translateY(-2px);
}

.project-notes-list__note-header {
	display: flex;
	justify-content: space-between;
	align-items: flex-start;
	gap: 8px;
}

.project-notes-list__note-title {
	margin: 0;
	font-size: 15px;
	font-weight: 600;
	color: var(--color-main-text);
	line-height: 1.3;
	display: -webkit-box;
	-webkit-line-clamp: 2;
	-webkit-box-orient: vertical;
	overflow: hidden;
}

.project-notes-list__note-date {
	font-size: 11px;
	color: var(--color-text-maxcontrast);
	white-space: nowrap;
}

.project-notes-list__note-preview {
	margin: 0;
	font-size: 13px;
	color: var(--color-text-lighter);
	line-height: 1.5;
	display: -webkit-box;
	-webkit-line-clamp: 3;
	-webkit-box-orient: vertical;
	overflow: hidden;
}

.project-notes-list__note-footer {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding-top: 12px;
	border-top: 1px solid var(--color-border);
	margin-top: auto;
}

.project-notes-list__note-author {
	display: flex;
	align-items: center;
	gap: 4px;
	font-size: 12px;
	color: var(--color-text-maxcontrast);
}

.project-notes-list__note-actions {
	display: flex;
	gap: 4px;
}

.project-notes-list__action-btn {
	display: flex;
	align-items: center;
	justify-content: center;
	width: 28px;
	height: 28px;
	border: none;
	background: transparent;
	color: var(--color-text-lighter);
	cursor: pointer;
	border-radius: 6px;
	transition: all 0.2s ease;
}

.project-notes-list__action-btn:hover {
	background: var(--color-background-hover);
	color: var(--color-main-text);
}

.project-notes-list__action-btn--danger:hover {
	background: var(--color-error);
	color: white;
}

@media (max-width: 600px) {
	.project-notes-list__header {
		flex-direction: column;
		align-items: stretch;
	}

	.project-notes-list__tabs {
		justify-content: center;
	}

	.project-notes-list__grid {
		grid-template-columns: 1fr;
	}
}
</style>

<template>
	<NcModal
		:show="show"
		size="large"
		@close="close">
		<div class="create-note-modal">
			<div class="create-note-modal__header">
				<h2 class="create-note-modal__title">
					<template v-if="isEditing">
						<Pencil :size="24" />
						Edit Note
					</template>
					<template v-else>
						<Plus :size="24" />
						Create New Note
					</template>
				</h2>
			</div>

			<div class="create-note-modal__content">
				<div class="create-note-modal__field">
					<label class="create-note-modal__label">
						Note Title
						<span class="create-note-modal__required">*</span>
					</label>
					<NcTextField
						v-model="noteTitle"
						placeholder="Enter note title..."
						:disabled="isSaving"
						@keyup.enter="focusContent" />
				</div>

				<div class="create-note-modal__field">
					<div class="create-note-modal__label-row">
						<label class="create-note-modal__label">
							Content
							<span class="create-note-modal__required">*</span>
						</label>
						<span class="create-note-modal__hint">Markdown supported</span>
					</div>
					<NcTextArea
						ref="contentTextarea"
						v-model="noteContent"
						:placeholder="contentPlaceholder"
						:disabled="isSaving"
						rows="10" />
				</div>

				<div class="create-note-modal__field">
					<label class="create-note-modal__label">Visibility</label>
					<div class="create-note-modal__visibility-options">
						<button
							type="button"
							class="create-note-modal__visibility-btn"
							:class="{ 'create-note-modal__visibility-btn--active': noteVisibility === 'public' }"
							:disabled="isEditing"
							@click="noteVisibility = 'public'">
							<Earth :size="18" />
							<div class="create-note-modal__visibility-info">
								<span class="create-note-modal__visibility-label">Public</span>
								<span class="create-note-modal__visibility-desc">Visible to all project members</span>
							</div>
						</button>
						<button
							type="button"
							class="create-note-modal__visibility-btn"
							:class="{ 'create-note-modal__visibility-btn--active': noteVisibility === 'private' }"
							:disabled="isEditing || !canCreatePrivate"
							@click="noteVisibility = 'private'">
							<Lock :size="18" />
							<div class="create-note-modal__visibility-info">
								<span class="create-note-modal__visibility-label">Private</span>
								<span class="create-note-modal__visibility-desc">Only visible to you</span>
							</div>
						</button>
					</div>
				</div>
			</div>

			<div v-if="error" class="create-note-modal__error">
				<AlertCircle :size="18" />
				<span>{{ error }}</span>
			</div>

			<div class="create-note-modal__actions">
				<NcButton type="tertiary" :disabled="isSaving" @click="close">
					Cancel
				</NcButton>
				<NcButton type="primary" :disabled="!canSave || isSaving" @click="save">
					<template #icon>
						<NcLoadingIcon v-if="isSaving" :size="16" />
						<Check v-else :size="16" />
					</template>
					{{ isSaving ? (isEditing ? 'Saving...' : 'Creating...') : (isEditing ? 'Save Changes' : 'Create Note') }}
				</NcButton>
			</div>
		</div>
	</NcModal>
</template>

<script>
import NcModal from '@nextcloud/vue/components/NcModal'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import Plus from 'vue-material-design-icons/Plus.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import Close from 'vue-material-design-icons/Close.vue'
import Check from 'vue-material-design-icons/Check.vue'
import Earth from 'vue-material-design-icons/Earth.vue'
import Lock from 'vue-material-design-icons/Lock.vue'
import AlertCircle from 'vue-material-design-icons/AlertCircle.vue'
import { ProjectsService } from '../Services/projects'

const projectsService = ProjectsService.getInstance()

export default {
	name: 'CreateNoteModal',
	components: {
		NcModal,
		NcButton,
		NcTextField,
		NcTextArea,
		NcLoadingIcon,
		Plus,
		Pencil,
		Check,
		Earth,
		Lock,
		AlertCircle,
	},
	props: {
		show: {
			type: Boolean,
			default: false,
		},
		projectId: {
			type: Number,
			required: true,
		},
		note: {
			type: Object,
			default: null,
		},
		visibility: {
			type: String,
			default: 'public',
		},
		canCreatePrivate: {
			type: Boolean,
			default: true,
		},
	},
	data() {
		return {
			noteTitle: '',
			noteContent: '',
			noteVisibility: 'public',
			isSaving: false,
			error: '',
		}
	},
	computed: {
		isEditing() {
			return this.note !== null
		},
		canSave() {
			return this.noteTitle.trim().length > 0 && this.noteContent.trim().length > 0
		},
		contentPlaceholder() {
			return this.noteVisibility === 'private'
				? 'Write your private notes here... (only you can see this)'
				: 'Write your notes here... (visible to all project members)'
		},
	},
	watch: {
		show: {
			immediate: true,
			handler(isShown) {
				if (isShown) {
					this.resetForm()
				}
			},
		},
	},
	methods: {
		resetForm() {
			if (this.isEditing && this.note) {
				this.noteTitle = this.note.title || ''
				this.noteContent = this.note.content || ''
				this.noteVisibility = this.note.visibility || 'public'
			} else {
				this.noteTitle = ''
				this.noteContent = ''
				this.noteVisibility = this.visibility
			}
			this.error = ''
		},
		focusContent() {
			this.$refs.contentTextarea?.$el?.querySelector('textarea')?.focus()
		},
		close() {
			if (!this.isSaving) {
				this.$emit('close')
			}
		},
		async save() {
			if (!this.canSave || this.isSaving) {
				return
			}

			this.error = ''
			this.isSaving = true

			try {
				if (this.isEditing) {
					const result = await projectsService.updateNote(
						this.projectId,
						this.note.id,
						{
							title: this.noteTitle.trim(),
							content: this.noteContent.trim(),
						}
					)
					if (result) {
						this.$emit('updated', result)
					}
				} else {
					const result = await projectsService.createNote(
						this.projectId,
						{
							title: this.noteTitle.trim(),
							content: this.noteContent.trim(),
							visibility: this.noteVisibility,
						}
					)
					if (result) {
						this.$emit('created', result)
					}
				}
			} catch (err) {
				this.error = err?.response?.data?.message || 'Failed to save note. Please try again.'
			} finally {
				this.isSaving = false
			}
		},
	},
}
</script>

<style scoped>
.create-note-modal {
	display: flex;
	flex-direction: column;
	max-height: 90vh;
}

.create-note-modal__header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: 20px 24px;
	border-bottom: 1px solid var(--color-border);
}

.create-note-modal__title {
	margin: 0;
	display: flex;
	align-items: center;
	gap: 10px;
	font-size: 20px;
	font-weight: 600;
}

.create-note-modal__content {
	padding: 24px;
	display: flex;
	flex-direction: column;
	gap: 20px;
	overflow-y: auto;
}

.create-note-modal__field {
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.create-note-modal__label {
	font-size: 14px;
	font-weight: 600;
	color: var(--color-main-text);
}

.create-note-modal__label-row {
	display: flex;
	justify-content: space-between;
	align-items: center;
}

.create-note-modal__required {
	color: var(--color-error);
}

.create-note-modal__hint {
	font-size: 12px;
	color: var(--color-text-maxcontrast);
}

.create-note-modal__visibility-options {
	display: grid;
	grid-template-columns: repeat(2, 1fr);
	gap: 12px;
}

.create-note-modal__visibility-btn {
	display: flex;
	align-items: flex-start;
	gap: 12px;
	padding: 16px;
	border: 2px solid var(--color-border);
	border-radius: 12px;
	background: var(--color-main-background);
	cursor: pointer;
	transition: all 0.2s ease;
	text-align: left;
}

.create-note-modal__visibility-btn:hover:not(:disabled) {
	border-color: var(--color-primary-element);
	background: var(--color-primary-element-light);
}

.create-note-modal__visibility-btn--active {
	border-color: var(--color-primary-element);
	background: var(--color-primary-element-light);
}

.create-note-modal__visibility-btn:disabled {
	opacity: 0.5;
	cursor: not-allowed;
}

.create-note-modal__visibility-info {
	display: flex;
	flex-direction: column;
	gap: 4px;
}

.create-note-modal__visibility-label {
	font-size: 14px;
	font-weight: 600;
	color: var(--color-main-text);
}

.create-note-modal__visibility-desc {
	font-size: 12px;
	color: var(--color-text-maxcontrast);
}

.create-note-modal__error {
	display: flex;
	align-items: center;
	gap: 8px;
	margin: 0 24px;
	padding: 12px 16px;
	background: var(--color-error-light);
	color: var(--color-error);
	border-radius: 8px;
	font-size: 14px;
}

.create-note-modal__actions {
	display: flex;
	justify-content: flex-end;
	gap: 12px;
	padding: 20px 24px;
	border-top: 1px solid var(--color-border);
}

@media (max-width: 600px) {
	.create-note-modal__visibility-options {
		grid-template-columns: 1fr;
	}

	.create-note-modal__actions {
		flex-direction: column-reverse;
	}

	.create-note-modal__actions button {
		width: 100%;
	}
}
</style>

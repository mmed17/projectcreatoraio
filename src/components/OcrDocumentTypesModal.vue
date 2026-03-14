<template>
	<NcModal :show="show" size="large" @close="handleClose">
		<div class="ocr-types-modal">
			<header class="ocr-types-modal__header">
				<div class="ocr-types-modal__header-content">
					<h2 class="ocr-types-modal__title">OCR Document Types</h2>
					<p class="ocr-types-modal__subtitle">
						Configure extraction schemas for your organization's project documents.
					</p>
				</div>
			</header>

			<div v-if="error" class="ocr-types-modal__error-banner" role="alert">
				<AlertCircle :size="20" />
				<span>{{ error }}</span>
			</div>

			<div v-if="loading" class="ocr-types-modal__loading-state">
				<NcLoadingIcon :size="32" />
				<p>Syncing document types...</p>
			</div>

			<div v-else class="ocr-types-modal__layout">
				<aside class="ocr-types-modal__sidebar">
					<NcButton
						type="primary"
						class="ocr-types-modal__add-type-btn"
						@click="startCreate">
						<template #icon>
							<Plus :size="20" />
						</template>
						New document type
					</NcButton>

					<div class="ocr-types-modal__sidebar-content">
						<h3 class="ocr-types-modal__section-label">Configured types</h3>
						<div v-if="documentTypes.length === 0" class="ocr-types-modal__empty-sidebar">
							No types defined yet.
						</div>
						<nav v-else class="ocr-types-modal__nav">
							<button
								v-for="type in documentTypes"
								:key="type.id"
								type="button"
								class="ocr-types-modal__nav-item"
								:class="{ 'ocr-types-modal__nav-item--active': Number(selectedTypeId) === Number(type.id) }"
								@click="selectType(type)">
								<div class="ocr-types-modal__nav-item-header">
									<span class="ocr-types-modal__nav-item-label">{{ type.name }}</span>
									<span class="ocr-types-modal__status-dot" :class="{ 'ocr-types-modal__status-dot--active': type.is_active }" />
								</div>
								<div class="ocr-types-modal__nav-item-meta">
									<span>{{ (type.fields || []).length }} fields</span>
								</div>
							</button>
						</nav>
					</div>
				</aside>

				<main class="ocr-types-modal__main">
					<div class="ocr-types-modal__editor-surface">
						<div class="ocr-types-modal__editor-intro">
							<h3 class="ocr-types-modal__editor-title">
								{{ form.id ? 'Edit Type' : 'Create New Type' }}
							</h3>
							<p class="ocr-types-modal__editor-hint">
								{{ form.id ? 'Modify the existing extraction schema.' : 'Define a new structure for automated data extraction.' }}
							</p>
						</div>

						<section class="ocr-types-modal__editor-section">
							<div class="ocr-types-modal__section-header">
								<h4 class="ocr-types-modal__section-title">General Information</h4>
								<div class="ocr-types-modal__toggle-wrapper">
									<span class="ocr-types-modal__toggle-label">Active</span>
									<input v-model="form.is_active" type="checkbox" class="ocr-types-modal__checkbox-input">
								</div>
							</div>
							<div class="ocr-types-modal__form-grid">
								<NcTextField
									v-model="form.name"
									label="Document Type Name"
									input-label="Name used in OCR and the UI (e.g. Invoice)"
									placeholder="e.g. Invoice" />
							</div>
						</section>

						<section class="ocr-types-modal__editor-section">
							<div class="ocr-types-modal__section-header">
								<h4 class="ocr-types-modal__section-title">Extraction Fields</h4>
								<p class="ocr-types-modal__section-subtitle">Define the specific data points to extract from documents of this type.</p>
							</div>

							<div v-if="form.fields.length === 0" class="ocr-types-modal__fields-empty">
								No fields defined. Add at least one field to start extracting data.
							</div>
							<div v-else class="ocr-types-modal__fields-list">
								<div v-for="(field, index) in form.fields" :key="`field-${index}`" class="ocr-types-modal__field-card">
									<div class="ocr-types-modal__field-row">
										<NcTextField
											v-model="field.name"
											label="Field Name"
											placeholder="e.g. Total Amount" />
									</div>
									<div class="ocr-types-modal__field-actions">
										<button type="button" class="ocr-types-modal__field-remove" @click="removeField(index)">
											<Delete :size="16" />
											Remove
										</button>
									</div>
								</div>
							</div>

							<NcButton
								type="secondary"
								class="ocr-types-modal__add-field-btn"
								@click="addField">
								<template #icon>
									<Plus :size="18" />
								</template>
								Add another field
							</NcButton>
						</section>
					</div>

					<footer class="ocr-types-modal__actions">
						<div class="ocr-types-modal__actions-left">
							<NcButton
								v-if="form.id"
								type="error"
								:disabled="saving || deleting"
								@click="deleteType">
								<template #icon>
									<NcLoadingIcon v-if="deleting" :size="18" />
									<Delete v-else :size="18" />
								</template>
								{{ deleting ? 'Deleting...' : 'Delete' }}
							</NcButton>
						</div>
						<div class="ocr-types-modal__actions-right">
							<NcButton type="tertiary" :disabled="saving" @click="resetForm">
								Reset
							</NcButton>
							<NcButton
								type="primary"
								:disabled="saving"
								@click="saveType">
								<template #icon>
									<NcLoadingIcon v-if="saving" :size="18" />
									<Check v-else :size="18" />
								</template>
								{{ saving ? 'Saving...' : form.id ? 'Update type' : 'Create type' }}
							</NcButton>
						</div>
					</footer>
				</main>
			</div>
		</div>
	</NcModal>
</template>

<script>
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcModal from '@nextcloud/vue/components/NcModal'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import Plus from 'vue-material-design-icons/Plus.vue'
import Delete from 'vue-material-design-icons/Delete.vue'
import Check from 'vue-material-design-icons/Check.vue'
import AlertCircle from 'vue-material-design-icons/AlertCircle.vue'

import { ProjectsService } from '../Services/projects.js'

const projectsService = ProjectsService.getInstance()

const createEmptyField = () => ({
	name: '',
})

const createEmptyForm = () => ({
	id: null,
	name: '',
	is_active: true,
	fields: [createEmptyField()],
})

export default {
	name: 'OcrDocumentTypesModal',
	components: {
		NcButton,
		NcLoadingIcon,
		NcModal,
		NcTextField,
		Plus,
		Delete,
		Check,
		AlertCircle,
	},
	props: {
		show: {
			type: Boolean,
			default: false,
		},
		organizationId: {
			type: Number,
			default: null,
		},
	},
	data() {
		return {
			loading: false,
			saving: false,
			deleting: false,
			error: '',
			documentTypes: [],
			selectedTypeId: null,
			form: createEmptyForm(),
		}
	},
	watch: {
		show: {
			immediate: true,
			handler(value) {
				if (value) {
					this.loadDocumentTypes()
				}
			},
		},
		organizationId(nextId, prevId) {
			if (!this.show || nextId === prevId) {
				return
			}
			this.loadDocumentTypes()
		},
	},
	methods: {
		handleClose() {
			this.$emit('close')
		},
		async loadDocumentTypes() {
			if (!Number.isFinite(Number(this.organizationId)) || Number(this.organizationId) <= 0) {
				this.documentTypes = []
				this.selectedTypeId = null
				this.form = createEmptyForm()
				return
			}

			this.loading = true
			this.error = ''
			try {
				this.documentTypes = await projectsService.listOrganizationDocumentTypes(Number(this.organizationId), true)
				if (this.selectedTypeId !== null) {
					const current = this.documentTypes.find((type) => Number(type.id) === Number(this.selectedTypeId))
					if (current) {
						this.applyTypeToForm(current)
						return
					}
				}
				this.startCreate()
			} catch (error) {
				this.error = error?.response?.data?.message || 'Could not load OCR document types.'
			} finally {
				this.loading = false
			}
		},
		startCreate() {
			this.selectedTypeId = null
			this.form = createEmptyForm()
		},
		selectType(type) {
			this.selectedTypeId = Number(type.id)
			this.applyTypeToForm(type)
		},
		applyTypeToForm(type) {
			const fields = Array.isArray(type?.fields) && type.fields.length > 0
				? type.fields.map((field) => ({
					name: field?.name || field?.label || field?.key || '',
				}))
				: [createEmptyField()]
			this.form = {
				id: Number(type?.id) || null,
				name: type?.name || type?.label || type?.key || '',
				is_active: !!type?.is_active,
				fields,
			}
		},
		resetForm() {
			if (this.selectedTypeId !== null) {
				const current = this.documentTypes.find((type) => Number(type.id) === Number(this.selectedTypeId))
				if (current) {
					this.applyTypeToForm(current)
					return
				}
			}
			this.startCreate()
		},
		addField() {
			this.form.fields.push(createEmptyField())
		},
		removeField(index) {
			this.form.fields.splice(index, 1)
			if (this.form.fields.length === 0) {
				this.form.fields.push(createEmptyField())
			}
		},
		buildPayload() {
			return {
				name: this.form.name,
				is_active: this.form.is_active ? 1 : 0,
				fields: this.form.fields.map((field) => ({
					name: field.name,
				})),
			}
		},
		async saveType() {
			if (!Number.isFinite(Number(this.organizationId)) || Number(this.organizationId) <= 0) {
				return
			}
			this.saving = true
			this.error = ''
			try {
				const payload = this.buildPayload()
				let saved = null
				if (this.form.id) {
					saved = await projectsService.updateOrganizationDocumentType(Number(this.organizationId), Number(this.form.id), {
						name: payload.name,
						is_active: payload.is_active,
						fields: payload.fields,
					})
				} else {
					saved = await projectsService.createOrganizationDocumentType(Number(this.organizationId), payload)
				}
				await this.loadDocumentTypes()
				if (saved?.id) {
					const current = this.documentTypes.find((type) => Number(type.id) === Number(saved.id))
					if (current) {
						this.selectType(current)
					}
				}
				this.$emit('updated')
			} catch (error) {
				this.error = error?.response?.data?.message || 'Could not save OCR document type.'
			} finally {
				this.saving = false
			}
		},
		async deleteType() {
			if (!this.form.id || !Number.isFinite(Number(this.organizationId)) || Number(this.organizationId) <= 0) {
				return
			}
			if (!window.confirm(`Delete OCR document type "${this.form.name}"?`)) {
				return
			}
			this.deleting = true
			this.error = ''
			try {
				await projectsService.deleteOrganizationDocumentType(Number(this.organizationId), Number(this.form.id))
				await this.loadDocumentTypes()
				this.$emit('updated')
			} catch (error) {
				this.error = error?.response?.data?.message || 'Could not delete OCR document type.'
			} finally {
				this.deleting = false
			}
		},
	},
}
</script>

<style scoped>
.ocr-types-modal {
	--surface-light: var(--color-main-background);
	--surface-dimmed: var(--color-background-hover);
	--border-color: var(--color-border);
	--border-color-dark: var(--color-border-dark);
	--accent-color: var(--color-primary-element);
	--text-dimmed: var(--color-text-maxcontrast);

	display: flex;
	flex-direction: column;
	min-width: min(100%, 980px);
	max-height: 85vh;
	overflow: hidden;
}

.ocr-types-modal__header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: 24px 32px;
	background: linear-gradient(to bottom, var(--surface-light), var(--surface-dimmed));
	border-bottom: 1px solid var(--border-color);
	z-index: 10;
}

.ocr-types-modal__title {
	margin: 0;
	font-size: 24px;
	font-weight: 800;
	letter-spacing: -0.02em;
	color: var(--color-main-text);
}

.ocr-types-modal__subtitle {
	margin: 4px 0 0;
	font-size: 14px;
	color: var(--text-dimmed);
}

.ocr-types-modal__error-banner {
	display: flex;
	align-items: center;
	gap: 12px;
	margin: 16px 32px 0;
	padding: 12px 16px;
	background: color-mix(in srgb, var(--color-error) 10%, var(--surface-light));
	border: 1px solid color-mix(in srgb, var(--color-error) 30%, var(--surface-light));
	border-radius: 12px;
	color: var(--color-error-text);
	font-weight: 600;
	font-size: 14px;
}

.ocr-types-modal__loading-state {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	padding: 64px;
	gap: 16px;
	color: var(--text-dimmed);
}

.ocr-types-modal__layout {
	display: grid;
	grid-template-columns: 280px 1fr;
	flex-grow: 1;
	overflow: hidden;
}

.ocr-types-modal__sidebar {
	background: var(--surface-dimmed);
	border-right: 1px solid var(--border-color);
	display: flex;
	flex-direction: column;
	overflow-y: auto;
}

.ocr-types-modal__add-type-btn {
	margin: 24px 32px 16px;
}

.ocr-types-modal__add-type-btn :deep(button) {
	width: 100%;
	justify-content: center;
	padding: 10px;
}

.ocr-types-modal__sidebar-content {
	padding: 0 16px 24px 32px;
	display: flex;
	flex-direction: column;
	gap: 16px;
}

.ocr-types-modal__section-label {
	font-size: 11px;
	font-weight: 700;
	text-transform: uppercase;
	letter-spacing: 0.1em;
	color: var(--text-dimmed);
	margin: 0;
}

.ocr-types-modal__nav {
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.ocr-types-modal__nav-item {
	width: 100%;
	padding: 12px 16px;
	border: 1px solid transparent;
	border-radius: 12px;
	background: var(--surface-light);
	text-align: left;
	cursor: pointer;
	transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
	box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
}

.ocr-types-modal__nav-item:hover {
	border-color: var(--border-color-dark);
	transform: translateY(-1px);
	box-shadow: 0 4px 8px rgba(0, 0, 0, 0.04);
}

.ocr-types-modal__nav-item--active {
	background: var(--accent-color);
	color: var(--color-primary-text);
}

.ocr-types-modal__nav-item--active .ocr-types-modal__nav-item-meta,
.ocr-types-modal__nav-item--active .ocr-types-modal__nav-item-label {
	color: var(--color-primary-text);
}

.ocr-types-modal__nav-item-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 4px;
}

.ocr-types-modal__nav-item-label {
	font-weight: 700;
	font-size: 14px;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

.ocr-types-modal__status-dot {
	width: 8px;
	height: 8px;
	border-radius: 50%;
	background: var(--text-dimmed);
	opacity: 0.5;
}

.ocr-types-modal__status-dot--active {
	background: var(--color-success);
	opacity: 1;
	box-shadow: 0 0 0 2px color-mix(in srgb, var(--color-success) 20%, transparent);
}

.ocr-types-modal__nav-item-meta {
	display: flex;
	justify-content: space-between;
	font-size: 11px;
	color: var(--text-dimmed);
}

.ocr-types-modal__main {
	display: flex;
	flex-direction: column;
	overflow: hidden;
}

.ocr-types-modal__editor-surface {
	padding: 40px;
	flex-grow: 1;
	overflow-y: auto;
	display: flex;
	flex-direction: column;
	gap: 48px;
}

.ocr-types-modal__editor-intro {
	display: flex;
	flex-direction: column;
	gap: 4px;
}

.ocr-types-modal__editor-title {
	margin: 0;
	font-size: 24px;
	font-weight: 800;
	letter-spacing: -0.02em;
}

.ocr-types-modal__editor-hint {
	margin: 0;
	font-size: 14px;
	color: var(--text-dimmed);
}

.ocr-types-modal__editor-section {
	display: flex;
	flex-direction: column;
	gap: 24px;
}

.ocr-types-modal__section-header {
	display: flex;
	flex-direction: column;
	gap: 4px;
	border-bottom: 1px solid var(--border-color);
	padding-bottom: 16px;
	position: relative;
}

.ocr-types-modal__section-header .ocr-types-modal__toggle-wrapper {
	position: absolute;
	right: 0;
	top: 0;
}

.ocr-types-modal__section-title {
	margin: 0;
	font-size: 16px;
	font-weight: 700;
	color: var(--color-main-text);
}

.ocr-types-modal__section-subtitle {
	margin: 0;
	font-size: 13px;
	color: var(--text-dimmed);
}

.ocr-types-modal__toggle-wrapper {
	display: flex;
	align-items: center;
	gap: 12px;
	padding: 6px 14px;
	background: var(--surface-dimmed);
	border-radius: 999px;
	font-weight: 600;
	font-size: 12px;
}

.ocr-types-modal__form-grid {
	display: grid;
	grid-template-columns: 1fr;
	gap: 24px;
}

.ocr-types-modal__fields-list {
	display: flex;
	flex-direction: column;
	gap: 20px;
}

.ocr-types-modal__field-card {
	padding: 24px;
	border: 1px solid var(--border-color);
	border-radius: 20px;
	background: var(--surface-light);
	display: flex;
	flex-direction: column;
	gap: 20px;
	transition: all 0.2s;
	box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
}

.ocr-types-modal__field-card:focus-within {
	border-color: var(--accent-color);
	box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

.ocr-types-modal__field-row {
	display: grid;
	grid-template-columns: 1fr;
	gap: 16px;
	align-items: flex-end;
}

.ocr-types-modal__field-actions {
	display: flex;
	justify-content: flex-end;
	align-items: center;
	padding-top: 16px;
	border-top: 1px dashed var(--border-color);
}

.ocr-types-modal__add-field-btn {
	align-self: flex-start;
	margin-top: 8px;
}

.ocr-types-modal__field-remove {
	display: flex;
	align-items: center;
	gap: 6px;
	background: transparent;
	border: none;
	color: var(--color-error-text);
	font-size: 12px;
	font-weight: 700;
	cursor: pointer;
	padding: 6px 12px;
	border-radius: 8px;
	transition: background 0.2s;
}

.ocr-types-modal__field-remove:hover {
	background: color-mix(in srgb, var(--color-error) 10%, transparent);
}

.ocr-types-modal__actions {
	padding: 24px 40px;
	background: var(--surface-dimmed);
	border-top: 1px solid var(--border-color);
	display: flex;
	justify-content: space-between;
	align-items: center;
}

.ocr-types-modal__actions-right {
	display: flex;
	gap: 12px;
}

.ocr-types-modal__empty-sidebar,
.ocr-types-modal__fields-empty {
	padding: 32px;
	text-align: center;
	color: var(--text-dimmed);
	font-size: 14px;
	border: 2px dashed var(--border-color);
	border-radius: 16px;
}

@media (max-width: 900px) {
	.ocr-types-modal {
		max-height: none;
	}

	.ocr-types-modal__layout {
		grid-template-columns: 1fr;
	}

	.ocr-types-modal__sidebar {
		border-right: none;
		border-bottom: 1px solid var(--border-color);
	}

	.ocr-types-modal__add-type-btn {
		margin: 24px;
	}

	.ocr-types-modal__sidebar-content {
		padding: 0 24px 24px;
	}

	.ocr-types-modal__editor-surface {
		padding: 24px;
	}

	.ocr-types-modal__form-grid,
	.ocr-types-modal__field-row {
		grid-template-columns: 1fr;
	}

	.ocr-types-modal__section-header .ocr-types-modal__toggle-wrapper {
		position: static;
		margin-top: 8px;
		align-self: flex-start;
	}

	.ocr-types-modal__actions {
		padding: 24px;
		flex-direction: column;
		gap: 16px;
	}

	.ocr-types-modal__actions-right {
		width: 100%;
	}

	.ocr-types-modal__actions-right :deep(button) {
		flex-grow: 1;
	}
}
</style>

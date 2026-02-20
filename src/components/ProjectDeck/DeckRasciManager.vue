<template>
	<div class="transition-permissions-manager">
		<div class="permissions-header">
			<h4>D-RASCI-VF Permissions</h4>
			<p class="description">
				Control who can move cards between columns using D-RASCI-VF roles.
			</p>
		</div>

		<div v-if="organizationId" class="role-profile-manager">
			<div class="profile-header">
				<h5>D-RASCI-VF Role Profiles</h5>
				<p class="description">
					Save and apply role templates across projects in your organization.
				</p>
			</div>

			<div class="actions-bar">
				<NcButton type="primary" :disabled="!canManageProfiles" @click="openSaveProfileModal">
					<template #icon>
						<ContentSave :size="18" />
					</template>
					Save Current as Profile
				</NcButton>

				<div class="apply-profile-section">
					<NcSelect
						v-model="selectedProfile"
						:options="profiles"
						placeholder="Select a profile to apply"
						label="name"
						track-by="id"
						class="profile-select"
					/>
					<NcButton type="secondary" :disabled="!selectedProfile" @click="openApplyProfileModal">
						<template #icon>
							<Download :size="18" />
						</template>
						Apply Profile
					</NcButton>
				</div>
			</div>

			<div v-if="profiles.length > 0" class="profiles-list">
				<div class="profiles-grid">
					<div v-for="profile in profiles" :key="profile.id" class="profile-card" @click="viewProfile(profile)">
						<div class="profile-info">
							<strong>{{ profile.name }}</strong>
							<span class="profile-meta">
								{{ profile.permissions?.length || 0 }} rules · by {{ profile.ownerId }}
							</span>
						</div>
						<div class="profile-actions">
							<NcButton type="tertiary" aria-label="View details" @click.stop="viewProfile(profile)">
								<template #icon>
									<Eye :size="18" />
								</template>
							</NcButton>
							<NcButton
								type="tertiary"
								:disabled="!canManageProfiles"
								aria-label="Delete profile"
								@click.stop="deleteProfile(profile)">
								<template #icon>
									<Delete :size="18" />
								</template>
							</NcButton>
						</div>
					</div>
				</div>
			</div>
		</div>

		<div class="add-permission-form">
			<h5>Add New Permission Rule</h5>
			<div class="form-grid">
				<div class="form-field">
					<label>From Stack</label>
					<NcSelect
						v-model="newPermission.fromStackId"
						:options="stackOptions"
						placeholder="Any stack"
						:clearable="true"
						label="title"
						track-by="id"
					/>
				</div>

				<div class="form-field">
					<label>To Stack *</label>
					<NcSelect
						v-model="newPermission.toStackId"
						:options="stackOptions"
						placeholder="Select destination"
						label="title"
						track-by="id"
					/>
				</div>

				<div class="form-field">
					<label>Required Roles *</label>
					<NcSelect
						v-model="newPermission.requiredRoles"
						:options="roleOptions"
						placeholder="Select one or more roles"
						label="label"
						track-by="value"
						:multiple="true"
						:close-on-select="false"
					/>
				</div>

				<div class="form-field">
					<label>Participant Type *</label>
					<NcSelect
						v-model="newPermission.participantType"
						:options="participantTypeOptions"
						placeholder="User, group, or circle"
						label="label"
						track-by="value"
					/>
				</div>

				<div class="form-field participant-field">
					<label>Participant *</label>
					<NcSelect
						v-if="newPermission.participantType && newPermission.participantType.value === 0"
						v-model="newPermission.participant"
						:options="participantOptions"
						placeholder="Select user"
						label="label"
						track-by="value"
					/>
					<input
						v-else
						v-model="newPermission.participant"
						type="text"
						class="participant-input"
						:placeholder="participantPlaceholder"
					/>
				</div>

				<div class="form-field form-actions">
					<NcButton type="primary" :disabled="!canAddPermission || loadingPermissions" @click="addPermission">
						<template #icon>
							<Plus :size="18" />
						</template>
						Add Rule
					</NcButton>
				</div>
			</div>
		</div>

		<div v-if="message" class="inline-success">{{ message }}</div>
		<div v-if="error" class="inline-error">{{ error }}</div>

		<div v-if="permissions.length > 0" class="permissions-list">
			<h5>Active Permission Rules</h5>
			<div class="permissions-table">
				<div class="table-header">
					<div class="col-from">From</div>
					<div class="col-to">To</div>
					<div class="col-role">Roles</div>
					<div class="col-participant">Who</div>
					<div class="col-actions">Actions</div>
				</div>
				<div v-for="group in groupedPermissions" :key="group.key" class="table-row">
					<div class="col-from">
						<span class="stack-badge">{{ getStackName(group.fromStackId) || 'Any' }}</span>
					</div>
					<div class="col-to">
						<span class="stack-badge">{{ getStackName(group.toStackId) }}</span>
					</div>
					<div class="col-role">
						<div class="role-badges">
							<span
								v-for="role in group.roles"
								:key="role.id"
								class="role-badge clickable"
								:class="`role-${role.requiredRole}`"
								title="Click to remove this role"
								@click="deletePermission(role.id)">
								{{ getRoleLabel(role.requiredRole) }}
								<span class="remove-icon">x</span>
							</span>
						</div>
					</div>
					<div class="col-participant">
						<span class="participant-info">
							<component :is="getParticipantIcon(group.participantType)" :size="16" />
							{{ group.participant }}
						</span>
					</div>
					<div class="col-actions">
						<NcButton type="error" aria-label="Delete all roles for this participant" @click="deleteGroupPermissions(group)">
							<template #icon>
								<Delete :size="18" />
							</template>
						</NcButton>
					</div>
				</div>
			</div>
		</div>

		<div v-else class="empty-state">
			<p>No permission rules defined. No card movements are allowed.</p>
		</div>

		<NcModal v-if="showSaveModal" @close="showSaveModal = false">
			<div class="save-modal-content">
				<h3>Save Role Profile</h3>
				<p>
					Save the current D-RASCI-VF configuration as a reusable template for your organization.
				</p>
				<div class="form-field">
					<label>Profile Name *</label>
					<input
						v-model="newProfileName"
						type="text"
						class="profile-name-input"
						placeholder="e.g., Standard Review Process"
					/>
				</div>
				<div class="modal-actions">
					<NcButton type="tertiary" @click="showSaveModal = false">Cancel</NcButton>
					<NcButton type="primary" :disabled="!newProfileName.trim()" @click="confirmSaveProfile">Save Profile</NcButton>
				</div>
			</div>
		</NcModal>

		<NcModal v-if="showApplyModal" @close="showApplyModal = false">
			<div class="apply-modal-content">
				<h3>Apply Role Profile</h3>
				<p>Apply "{{ selectedProfile?.name }}" to this board?</p>
				<label class="switch-option">
					<input v-model="clearExisting" type="checkbox">
					<span>Clear existing permissions first</span>
				</label>
				<p class="option-note">
					If disabled, profile permissions will be added to existing ones.
				</p>
				<div class="note-card">
					Only permissions for stacks with matching names will be created. Users and groups must exist in the board context.
				</div>
				<div class="modal-actions">
					<NcButton type="tertiary" @click="showApplyModal = false">Cancel</NcButton>
					<NcButton type="primary" @click="confirmApplyProfile">Apply</NcButton>
				</div>
			</div>
		</NcModal>

		<NcModal v-if="showDetailsModal" size="large" @close="showDetailsModal = false">
			<div class="details-modal-content">
				<h3>{{ viewingProfile?.name }}</h3>
				<p class="profile-owner">Created by {{ viewingProfile?.ownerId }}</p>
				<div v-if="viewingProfile?.permissions?.length > 0" class="permissions-table">
					<div class="table-header">
						<div class="col">From Stack</div>
						<div class="col">To Stack</div>
						<div class="col">Role</div>
						<div class="col">Participant</div>
					</div>
					<div v-for="(perm, index) in viewingProfile.permissions" :key="index" class="table-row">
						<div class="col"><span class="stack-badge">{{ perm.fromStackName || 'Any' }}</span></div>
						<div class="col"><span class="stack-badge">{{ perm.toStackName }}</span></div>
						<div class="col"><span class="role-badge" :class="'role-' + perm.requiredRole">{{ getRoleLabel(perm.requiredRole) }}</span></div>
						<div class="col"><span class="participant-info">{{ perm.participant }}</span></div>
					</div>
				</div>
				<div v-else class="empty-permissions">
					This profile has no permission rules defined.
				</div>
				<div class="modal-actions">
					<NcButton type="tertiary" @click="showDetailsModal = false">Close</NcButton>
				</div>
			</div>
		</NcModal>
	</div>
</template>

<script>
import NcButton from '@nextcloud/vue/components/NcButton'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcModal from '@nextcloud/vue/components/NcModal'

import Account from 'vue-material-design-icons/Account.vue'
import AccountGroup from 'vue-material-design-icons/AccountGroup.vue'
import ContentSave from 'vue-material-design-icons/ContentSave.vue'
import Delete from 'vue-material-design-icons/Delete.vue'
import Download from 'vue-material-design-icons/Download.vue'
import Eye from 'vue-material-design-icons/Eye.vue'
import GoogleCirclesExtended from 'vue-material-design-icons/GoogleCirclesExtended.vue'
import Plus from 'vue-material-design-icons/Plus.vue'

import { DeckService } from '../../Services/deck.js'

const deckService = DeckService.getInstance()

export default {
	name: 'DeckRasciManager',
	components: {
		Account,
		AccountGroup,
		ContentSave,
		Delete,
		Download,
		Eye,
		GoogleCirclesExtended,
		NcButton,
		NcModal,
		NcSelect,
		Plus,
	},
	props: {
		boardId: {
			type: [String, Number],
			required: true,
		},
		organizationId: {
			type: Number,
			default: null,
		},
		members: {
			type: Array,
			default: () => [],
		},
		stacks: {
			type: Array,
			default: () => [],
		},
		canManageProfiles: {
			type: Boolean,
			default: false,
		},
	},
	data() {
		return {
			permissions: [],
			profiles: [],
			selectedProfile: null,
			loadingPermissions: false,
			message: '',
			error: '',
			newPermission: {
				fromStackId: null,
				toStackId: null,
				requiredRoles: [],
				participant: '',
				participantType: null,
			},
			participantTypeOptions: [
				{ value: 0, label: 'User' },
				{ value: 1, label: 'Group' },
				{ value: 7, label: 'Circle' },
			],
			roleOptions: [
				{ value: 'D', label: 'Driver', description: 'Initiates and drives the work forward' },
				{ value: 'R', label: 'Responsible', description: 'Does the actual work' },
				{ value: 'A', label: 'Accountable', description: 'Ultimately answerable for completion' },
				{ value: 'S', label: 'Support', description: 'Provides support and resources' },
				{ value: 'C', label: 'Consulted', description: 'Provides input and feedback' },
				{ value: 'I', label: 'Informed', description: 'Kept informed of progress' },
				{ value: 'V', label: 'Verify', description: 'Verifies that work meets requirements' },
				{ value: 'F', label: 'Final Approver', description: 'Gives final approval' },
			],
			showSaveModal: false,
			showApplyModal: false,
			showDetailsModal: false,
			newProfileName: '',
			clearExisting: false,
			viewingProfile: null,
		}
	},
	computed: {
		stackOptions() {
			return Array.isArray(this.stacks) ? this.stacks.map((stack) => ({ id: stack.id, title: stack.title })) : []
		},
		participantOptions() {
			if (!this.newPermission.participantType || this.newPermission.participantType.value !== 0) {
				return []
			}

			return (this.members || []).map((member) => ({
				value: member.id,
				label: member.displayName || member.id,
				subname: member.email || member.id,
			}))
		},
		participantPlaceholder() {
			if (!this.newPermission.participantType) {
				return 'Select type first'
			}
			if (this.newPermission.participantType.value === 1) {
				return 'Enter group name'
			}
			if (this.newPermission.participantType.value === 7) {
				return 'Enter circle name'
			}
			return 'Select participant'
		},
		canAddPermission() {
			const participant = this.newPermission.participant
			const participantValue = typeof participant === 'string' ? participant.trim() : participant?.value
			return !!(
				this.newPermission.toStackId
				&& this.newPermission.requiredRoles
				&& this.newPermission.requiredRoles.length > 0
				&& this.newPermission.participantType
				&& participantValue
			)
		},
		groupedPermissions() {
			const groups = {}
			for (const permission of this.permissions) {
				const key = `${permission.fromStackId || 'any'}-${permission.toStackId}-${permission.participant}-${permission.participantType}`
				if (!groups[key]) {
					groups[key] = {
						key,
						fromStackId: permission.fromStackId,
						toStackId: permission.toStackId,
						participant: permission.participant,
						participantType: permission.participantType,
						roles: [],
					}
				}
				groups[key].roles.push({
					id: permission.id,
					requiredRole: permission.requiredRole,
				})
			}
			return Object.values(groups)
		},
	},
	watch: {
		boardId: {
			immediate: true,
			handler() {
				this.loadPermissions()
			},
		},
		organizationId: {
			immediate: true,
			handler() {
				this.loadProfiles()
			},
		},
		'newPermission.participantType'() {
			this.newPermission.participant = ''
		},
	},
	methods: {
		setMessage(text = '') {
			this.message = text
			if (text) {
				this.error = ''
			}
		},
		setError(text = '') {
			this.error = text
			if (text) {
				this.message = ''
			}
		},
		async loadPermissions() {
			if (!this.boardId) {
				this.permissions = []
				return
			}
			this.loadingPermissions = true
			try {
				this.permissions = await deckService.getTransitionPermissions(this.boardId)
			} catch (error) {
				this.permissions = []
				this.setError(error?.response?.data?.message || 'Failed to load permission rules.')
			} finally {
				this.loadingPermissions = false
			}
		},
		async loadProfiles() {
			if (!this.organizationId) {
				this.profiles = []
				this.selectedProfile = null
				return
			}
			try {
				this.profiles = await deckService.listRoleProfiles(this.organizationId)
			} catch (error) {
				this.profiles = []
				this.setError(error?.response?.data?.error || 'Failed to load role profiles.')
			}
		},
		async addPermission() {
			if (!this.canAddPermission) {
				return
			}

			const participant = this.newPermission.participant
			const participantValue = typeof participant === 'string' ? participant.trim() : participant.value

			const baseData = {
				fromStackId: this.newPermission.fromStackId?.id || null,
				toStackId: this.newPermission.toStackId.id,
				participant: participantValue,
				participantType: this.newPermission.participantType.value,
			}

			try {
				await Promise.all(this.newPermission.requiredRoles.map((role) => {
					return deckService.addTransitionPermission(this.boardId, {
						...baseData,
						requiredRole: role.value,
					})
				}))

				this.newPermission = {
					fromStackId: null,
					toStackId: null,
					requiredRoles: [],
					participant: '',
					participantType: null,
				}
				this.setMessage('Permission rule(s) added.')
				await this.loadPermissions()
			} catch (error) {
				this.setError(error?.response?.data?.message || 'Failed to add permission rule.')
			}
		},
		async deletePermission(id) {
			if (!window.confirm('Are you sure you want to delete this permission rule?')) {
				return
			}

			try {
				await deckService.deleteTransitionPermission(id)
				this.setMessage('Permission rule deleted.')
				await this.loadPermissions()
			} catch (error) {
				this.setError(error?.response?.data?.message || 'Failed to delete permission rule.')
			}
		},
		async deleteGroupPermissions(group) {
			if (!window.confirm(`Are you sure you want to delete all ${group.roles.length} role(s) for this participant?`)) {
				return
			}

			try {
				await Promise.all(group.roles.map((role) => deckService.deleteTransitionPermission(role.id)))
				this.setMessage('Permission rules deleted.')
				await this.loadPermissions()
			} catch (error) {
				this.setError(error?.response?.data?.message || 'Failed to delete permission rules.')
			}
		},
		getStackName(stackId) {
			if (!stackId) {
				return null
			}
			const stack = this.stackOptions.find((item) => Number(item.id) === Number(stackId))
			return stack?.title || `Stack #${stackId}`
		},
		getRoleLabel(roleValue) {
			const role = this.roleOptions.find((item) => item.value === roleValue)
			return role?.label || roleValue
		},
		getParticipantIcon(type) {
			if (Number(type) === 1) {
				return 'AccountGroup'
			}
			if (Number(type) === 7) {
				return 'GoogleCirclesExtended'
			}
			return 'Account'
		},
		openSaveProfileModal() {
			if (!this.canManageProfiles) {
				this.setError('Only organization admins can create role profiles.')
				return
			}
			this.newProfileName = ''
			this.showSaveModal = true
		},
		async confirmSaveProfile() {
			if (!this.newProfileName.trim()) {
				return
			}
			try {
				await deckService.createRoleProfileFromBoard(
					this.boardId,
					this.newProfileName.trim(),
					this.organizationId,
				)
				this.showSaveModal = false
				this.setMessage('Role profile saved successfully.')
				await this.loadProfiles()
			} catch (error) {
				this.setError(error?.response?.data?.error || 'Failed to save role profile.')
			}
		},
		openApplyProfileModal() {
			if (!this.selectedProfile) {
				return
			}
			this.clearExisting = false
			this.showApplyModal = true
		},
		async confirmApplyProfile() {
			if (!this.selectedProfile) {
				return
			}
			try {
				const result = await deckService.applyRoleProfile(this.boardId, this.selectedProfile.id, this.clearExisting)
				this.showApplyModal = false
				this.selectedProfile = null
				this.setMessage(`Profile applied: ${result.created} rules created, ${result.skipped} skipped.`)
				await this.loadPermissions()
			} catch (error) {
				this.setError(error?.response?.data?.error || 'Failed to apply role profile.')
			}
		},
		async viewProfile(profile) {
			try {
				this.viewingProfile = await deckService.getRoleProfile(profile.id)
				this.showDetailsModal = true
			} catch (error) {
				this.setError(error?.response?.data?.error || 'Failed to load profile details.')
			}
		},
		async deleteProfile(profile) {
			if (!this.canManageProfiles) {
				this.setError('Only organization admins can delete role profiles.')
				return
			}
			if (!window.confirm(`Are you sure you want to delete the profile "${profile.name}"?`)) {
				return
			}
			try {
				await deckService.deleteRoleProfile(profile.id)
				this.setMessage('Role profile deleted.')
				await this.loadProfiles()
			} catch (error) {
				this.setError(error?.response?.data?.error || 'Failed to delete role profile.')
			}
		},
	},
}
</script>

<style scoped>
.transition-permissions-manager {
	padding: 20px;
	background: var(--color-main-background);
	border: 1px solid var(--color-border);
	border-radius: 12px;
	margin-bottom: 12px;
}

.permissions-header {
	margin-bottom: 24px;
}

.permissions-header h4 {
	margin: 0 0 8px 0;
	font-size: 16px;
	font-weight: 600;
	color: var(--color-main-text);
}

.description {
	margin: 0;
	color: var(--color-text-maxcontrast);
	font-size: 13px;
}

.role-profile-manager {
	padding: 16px;
	border: 1px solid var(--color-border);
	border-radius: 10px;
	margin-bottom: 16px;
	background: var(--color-background-dark);
}

.profile-header {
	margin-bottom: 12px;
}

.profile-header h5,
.permissions-list h5,
.add-permission-form h5 {
	margin: 0 0 10px 0;
	font-size: 14px;
	font-weight: 600;
	color: var(--color-main-text);
}

.actions-bar {
	display: flex;
	gap: 12px;
	align-items: center;
	flex-wrap: wrap;
	margin-bottom: 12px;
}

.apply-profile-section {
	display: flex;
	gap: 8px;
	align-items: center;
	flex: 1;
	min-width: 320px;
}

.profile-select {
	flex: 1;
	max-width: 320px;
}

.profiles-grid {
	display: grid;
	gap: 8px;
}

.profile-card {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: 10px 12px;
	border-radius: 8px;
	background: var(--color-main-background);
	border: 1px solid var(--color-border);
}

.profile-card:hover {
	background: var(--color-background-hover);
	cursor: pointer;
}

.profile-info {
	display: flex;
	flex-direction: column;
	gap: 2px;
}

.profile-meta {
	font-size: 12px;
	color: var(--color-text-maxcontrast);
}

.profile-actions {
	display: flex;
	gap: 4px;
}

.add-permission-form {
	background: var(--color-background-dark);
	border-radius: 10px;
	padding: 16px;
	margin-bottom: 16px;
}

.form-grid {
	display: grid;
	grid-template-columns: repeat(3, minmax(0, 1fr));
	gap: 14px;
	align-items: end;
}

.form-field {
	display: flex;
	flex-direction: column;
	gap: 6px;
}

.form-field label {
	font-size: 13px;
	font-weight: 500;
	color: var(--color-text-maxcontrast);
}

.participant-input,
.profile-name-input {
	width: 100%;
	padding: 10px 12px;
	border: 1px solid var(--color-border-dark);
	border-radius: 6px;
	font-size: 14px;
	background: var(--color-main-background);
	color: var(--color-main-text);
}

.participant-input:focus,
.profile-name-input:focus {
	outline: none;
	border-color: var(--color-primary-element);
}

.form-actions {
	grid-column: 1 / -1;
	display: flex;
	justify-content: flex-end;
}

.inline-success {
	background: rgba(46, 204, 113, 0.12);
	border: 1px solid rgba(46, 204, 113, 0.45);
	color: #187d4a;
	padding: 8px 10px;
	border-radius: 8px;
	font-size: 13px;
	margin-bottom: 10px;
}

.inline-error {
	background: rgba(231, 76, 60, 0.12);
	border: 1px solid rgba(231, 76, 60, 0.4);
	color: #a1281c;
	padding: 8px 10px;
	border-radius: 8px;
	font-size: 13px;
	margin-bottom: 10px;
}

.permissions-table {
	border: 1px solid var(--color-border);
	border-radius: 8px;
	overflow: hidden;
}

.table-header,
.table-row {
	display: grid;
	grid-template-columns: 1.2fr 1.2fr 1fr 1.4fr 92px;
	gap: 10px;
	padding: 10px 14px;
	align-items: center;
}

.table-header {
	background: var(--color-background-dark);
	font-weight: 600;
	font-size: 13px;
	color: var(--color-text-maxcontrast);
}

.table-row {
	border-top: 1px solid var(--color-border);
}

.table-row:hover {
	background: var(--color-background-hover);
}

.stack-badge {
	display: inline-block;
	padding: 4px 10px;
	border-radius: 10px;
	background: var(--color-background-dark);
	font-size: 12px;
	font-weight: 500;
}

.role-badges {
	display: flex;
	flex-wrap: wrap;
	gap: 4px;
}

.role-badge {
	display: inline-flex;
	align-items: center;
	gap: 4px;
	padding: 4px 8px;
	border-radius: 4px;
	font-size: 11px;
	font-weight: 600;
	color: #fff;
}

.role-badge.clickable {
	cursor: pointer;
}

.role-badge.clickable .remove-icon {
	opacity: 0;
	transition: opacity .18s ease;
}

.role-badge.clickable:hover .remove-icon {
	opacity: 1;
}

.role-D { background: #e91e63; }
.role-R { background: #9c27b0; }
.role-A { background: #3f51b5; }
.role-S { background: #00bcd4; }
.role-C { background: #4caf50; }
.role-I { background: #8bc34a; }
.role-V { background: #ff9800; }
.role-F { background: #f44336; }

.participant-info {
	display: inline-flex;
	align-items: center;
	gap: 8px;
	font-size: 13px;
}

.empty-state {
	text-align: center;
	padding: 26px 16px;
	color: var(--color-text-maxcontrast);
	background: var(--color-background-dark);
	border-radius: 8px;
}

.save-modal-content,
.apply-modal-content {
	padding: 20px;
	max-width: 520px;
}

.save-modal-content h3,
.apply-modal-content h3 {
	margin: 0 0 8px 0;
}

.save-modal-content p,
.apply-modal-content p {
	margin: 0 0 14px 0;
	color: var(--color-text-maxcontrast);
}

.switch-option {
	display: inline-flex;
	align-items: center;
	gap: 8px;
	font-size: 14px;
	margin-bottom: 8px;
}

.option-note {
	font-size: 12px;
	margin-bottom: 12px;
}

.note-card {
	padding: 10px 12px;
	border-radius: 8px;
	border: 1px solid rgba(245, 158, 11, 0.55);
	background: rgba(245, 158, 11, 0.12);
	font-size: 12px;
	color: var(--color-main-text);
	margin-bottom: 14px;
}

.details-modal-content {
	padding: 20px;
	min-width: 640px;
}

.details-modal-content .profile-owner {
	margin: 0 0 16px 0;
	font-size: 13px;
	color: var(--color-text-maxcontrast);
}

.details-modal-content .permissions-table {
	margin-bottom: 16px;
}

.details-modal-content .table-header,
.details-modal-content .table-row {
	grid-template-columns: 1fr 1fr 1fr 1.5fr;
}

.empty-permissions {
	padding: 36px 16px;
	text-align: center;
	color: var(--color-text-maxcontrast);
	background: var(--color-background-dark);
	border-radius: 8px;
	margin-bottom: 14px;
}

.modal-actions {
	display: flex;
	justify-content: flex-end;
	gap: 8px;
	margin-top: 14px;
}

@media (max-width: 1100px) {
	.form-grid {
		grid-template-columns: repeat(2, minmax(0, 1fr));
	}

	.table-header,
	.table-row {
		grid-template-columns: 1fr;
		gap: 8px;
	}

	.details-modal-content {
		min-width: auto;
	}
}

@media (max-width: 760px) {
	.form-grid {
		grid-template-columns: 1fr;
	}
}
</style>

<template>
	<div class="pc-policy-app">
		
		<div v-if="loading && !cards.length" class="pc-loading-state">
			<div class="pc-spinner"></div>
			<p>Loading permissions data...</p>
		</div>

		<div v-else-if="error" class="pc-error-state">
			<div class="pc-error-icon">⚠️</div>
			<h3>Failed to load permissions</h3>
			<p>{{ error }}</p>
			<NcButton @click="load">Retry</NcButton>
		</div>

		<div v-else-if="settings.permissionMode !== 'card_policy'" class="pc-enable-state">
			<div class="pc-enable-icon">🛡️</div>
			<h3>Granular Permissions Disabled</h3>
			<p class="muted">This board is using legacy permissions. Upgrade to the new card-policy system to enable granular access controls.</p>
			<NcButton type="primary" size="large" @click="enable">Enable Granular Permissions</NcButton>
		</div>

		<div v-else class="pc-policy-container">
			
			<!-- TOP HEADER & TOOLBAR -->
			<div class="pc-app-header">
				<div class="pc-header-titles">
					<h2>Card Permissions</h2>
					<p class="muted">Assign move and approve permissions to specific cards.</p>
				</div>
				<div class="pc-header-actions">
					<NcButton type="tertiary" @click="showMembersModal = true">
						<template #icon><span class="pc-emoji-icon">👥</span></template>
						Board Roles & Members
					</NcButton>
					<NcButton type="tertiary" @click="showDefaultsModal = true">
						<template #icon><span class="pc-emoji-icon">⚙️</span></template>
						Board Defaults
					</NcButton>
					<NcButton type="tertiary" @click="openTemplates">
						<template #icon><span class="pc-emoji-icon">📋</span></template>
						Templates
					</NcButton>
				</div>
			</div>

			<div class="pc-toolbar">
				<div class="pc-toolbar-search">
					<span class="pc-search-icon">🔍</span>
					<input v-model.trim="cardSearch" type="search" placeholder="Search cards..." class="pc-search-input">
				</div>
				<div class="pc-toolbar-filters">
					<select v-model.number="stackFilter" class="pc-stack-select">
						<option :value="0">All stacks ({{ cards.length }} cards)</option>
						<option v-for="s in stacks" :key="s.id" :value="Number(s.id)">{{ s.title }}</option>
					</select>
					<label class="pc-filter-checkbox">
						<input type="checkbox" v-model="showOnlyCustom">
						Show only custom overrides
					</label>
				</div>
			</div>

			<!-- MAIN DATA GRID -->
			<div class="pc-data-grid-wrapper">
				<table class="pc-data-grid">
					<thead>
						<tr>
							<th class="pc-col-check">
								<input type="checkbox" :checked="allVisibleSelected" @change="toggleSelectAllVisible" title="Select all visible">
							</th>
							<th class="pc-col-name">Card Name</th>
							<th class="pc-col-stack">Stack</th>
							<th class="pc-col-perms">Who can Move</th>
							<th class="pc-col-perms">Who can Approve</th>
							<th class="pc-col-actions"></th>
						</tr>
					</thead>
					<tbody>
						<tr v-if="filteredCards.length === 0">
							<td colspan="6" class="pc-empty-row">No cards match your filters.</td>
						</tr>
						<tr v-for="card in filteredCards" :key="card.id" 
							:class="{ 'is-selected': isSelected(card.id), 'is-custom': card.hasExplicitPolicy }"
							@click="toggleSelection(card.id, $event)">
							<td class="pc-col-check" @click.stop>
								<input type="checkbox" :value="card.id" v-model="selectedCardIds">
							</td>
							<td class="pc-col-name">
								<div class="pc-card-title-wrap">
									<span class="pc-card-title">{{ card.title }}</span>
									<span v-if="card.hasExplicitPolicy" class="pc-badge pc-badge-custom" title="This card has specific override rules">Custom</span>
								</div>
							</td>
							<td class="pc-col-stack">
								<span class="pc-stack-label">{{ getStackTitle(card.stackId) }}</span>
							</td>
							<td class="pc-col-perms">
								<div class="pc-role-chips">
									<span v-for="rk in getEffectivePerms(card, 'move')" :key="rk" class="pc-role-chip" :style="chipStyleByKey(rk)">
										<span class="pc-dot" :style="{ background: roleColorByKey(rk) }"></span>
										{{ roleNameByKey(rk) }}
									</span>
									<span v-if="!getEffectivePerms(card, 'move').length" class="muted-dash">—</span>
								</div>
							</td>
							<td class="pc-col-perms">
								<div class="pc-role-chips">
									<span v-for="rk in getEffectivePerms(card, 'approve')" :key="rk" class="pc-role-chip" :style="chipStyleByKey(rk)">
										<span class="pc-dot" :style="{ background: roleColorByKey(rk) }"></span>
										{{ roleNameByKey(rk) }}
									</span>
									<span v-if="!getEffectivePerms(card, 'approve').length" class="muted-dash">—</span>
								</div>
							</td>
							<td class="pc-col-actions" @click.stop>
							<NcButton v-if="card.hasExplicitPolicy" type="tertiary" size="small" aria-label="Reset to board defaults" @click="resetCard(card)" title="Reset to board defaults">
								<template #icon>↺</template>
							</NcButton>
							</td>
						</tr>
					</tbody>
				</table>
			</div>

			<!-- FLOATING BULK ACTION BAR -->
			<transition name="slide-up">
				<div v-if="selectedCardIds.length > 0" class="pc-floating-action-bar">
					<div class="pc-fab-left">
						<span class="pc-fab-count">{{ selectedCardIds.length }} cards selected</span>
						<button class="pc-fab-clear" @click="selectedCardIds = []">Clear selection</button>
					</div>
					
					<div class="pc-fab-controls">
						<div class="pc-fab-field">
							<label>Move:</label>
							<NcSelect v-model="bulkEdits.move" :options="roleOptions" label="label" track-by="value" :multiple="true" :close-on-select="false" placeholder="Select roles..." />
						</div>
						<div class="pc-fab-field">
							<label>Approve:</label>
							<NcSelect v-model="bulkEdits.approve" :options="roleOptions" label="label" track-by="value" :multiple="true" :close-on-select="false" placeholder="Select roles..." />
						</div>
					</div>

					<div class="pc-fab-actions">
						<NcButton type="primary" :loading="savingBulk" @click="saveBulk">Apply Rules</NcButton>
						<NcButton type="tertiary" :loading="savingBulk" @click="resetBulk" title="Remove custom rules from selected cards">Reset to Defaults</NcButton>
					</div>
				</div>
			</transition>
		</div>

		<!-- MODAL: BOARD DEFAULTS -->
		<NcModal v-if="showDefaultsModal" @close="showDefaultsModal = false" title="Board Defaults" size="normal">
			<div class="pc-modal-content">
				<div class="pc-modal-header-desc">
					<p>These permissions apply automatically to any card that does not have custom overrides set.</p>
				</div>
				<div class="pc-modal-field">
					<label>Who can move cards</label>
					<NcSelect v-model="defaults.move" :options="roleOptions" label="label" track-by="value" :multiple="true" :close-on-select="false" />
				</div>
				<div class="pc-modal-field">
					<label>Who can approve / mark done</label>
					<NcSelect v-model="defaults.approve" :options="roleOptions" label="label" track-by="value" :multiple="true" :close-on-select="false" />
				</div>
				<div class="pc-modal-footer">
					<NcButton @click="showDefaultsModal = false">Cancel</NcButton>
					<NcButton type="primary" :loading="savingDefaults" @click="saveDefaults">Save Defaults</NcButton>
				</div>
			</div>
		</NcModal>

		<!-- MODAL: ROLES & MEMBERS -->
		<NcModal v-if="showMembersModal" @close="showMembersModal = false" title="Board Roles & Members" size="large">
			<div class="pc-modal-content pc-members-modal">
				<div class="pc-modal-tabs" role="tablist" aria-label="Role management">
					<button type="button" class="pc-modal-tab" :class="{ 'pc-modal-tab--active': membersModalTab === 'members' }" @click="membersModalTab = 'members'">Members</button>
					<button type="button" class="pc-modal-tab" :class="{ 'pc-modal-tab--active': membersModalTab === 'roles' }" @click="membersModalTab = 'roles'">Roles</button>
				</div>

				<div v-if="membersModalTab === 'roles'">
					<div class="pc-add-role-box">
						<h4>Create Role</h4>
						<div class="pc-add-role-row">
							<NcTextField class="pc-flex-1" v-model="newRole.name" label="Role name" :show-label="true" placeholder="e.g., Installer" />
							<NcTextField
								class="pc-flex-1"
								v-model="newRole.roleKey"
								label="Role key"
								:show-label="true"
								placeholder="e.g., installer"
								@focus="newRoleKeyTouched = true"
								@input="newRoleKeyTouched = true" />
							<div class="pc-role-color-field">
								<label class="pc-role-color-label">Color</label>
								<NcColorPicker v-model="newRole.color" class="pc-role-color-picker">
									<div class="pc-role-color-swatch" :style="{ backgroundColor: newRole.color }" />
								</NcColorPicker>
							</div>
							<NcButton type="primary" :loading="creatingRole" :disabled="!newRole.name.trim() || !newRole.roleKey.trim()" @click="createRole">Create</NcButton>
						</div>
						<p class="pc-help-muted">Role keys must be lowercase and can contain letters, numbers, underscores and dashes.</p>
					</div>

					<div class="pc-roles-list-box">
						<h4>Existing Roles</h4>
						<div v-if="roles.length === 0" class="muted" style="padding: 20px; text-align: center;">No roles available.</div>
						<div v-else class="pc-roles-chips">
							<span v-for="r in roles" :key="r.id" class="pc-role-chip" :style="{ borderColor: r.color }">
								<span class="pc-dot" :style="{ background: r.color }"></span>
								{{ r.name }} <span class="pc-role-key">({{ r.roleKey }})</span>
							</span>
						</div>
					</div>
				</div>

				<div v-else>
					<div class="pc-add-member-box">
						<h4>Add Member to Role</h4>
						<div class="pc-add-member-row">
							<NcSelect class="pc-flex-1" v-model="newMembership.user" :options="memberOptions" placeholder="Select a user..." label="label" track-by="value" />
							<NcSelect class="pc-flex-1" v-model="newMembership.role" :options="roleOptions" placeholder="Select a role..." label="label" track-by="value" />
							<NcButton type="primary" :disabled="!canAddMembership" @click="addMembership">Add</NcButton>
						</div>
					</div>

					<div class="pc-members-list-box">
						<h4>Current Members</h4>
						<div v-if="memberships.length === 0" class="muted" style="padding: 20px; text-align: center;">No roles assigned.</div>
						<table v-else class="pc-simple-table">
							<tbody>
								<tr v-for="m in memberships" :key="m.id">
									<td class="pc-simple-td-name"><strong>{{ getMemberDisplayName(m.participant) }}</strong></td>
									<td class="pc-simple-td-role">
										<span class="pc-role-chip" :style="chipStyleByRoleId(m.roleId)">
											<span class="pc-dot" :style="{ background: roleColorById(m.roleId) }"></span>
											{{ roleNameById[m.roleId] || m.roleId }}
										</span>
									</td>
									<td class="pc-simple-td-action">
										<button class="pc-danger-btn" @click="removeMembership(m)">Remove</button>
									</td>
								</tr>
							</tbody>
						</table>
					</div>
				</div>

			</div>
		</NcModal>

		<!-- MODAL: TEMPLATES -->
		<NcModal v-if="showTemplatesModal" @close="showTemplatesModal = false" title="Permission Templates" size="large">
			<div class="pc-modal-content pc-templates-modal">
				<div class="pc-modal-header-desc">
					<p class="muted">Apply an existing template to this board, or save the current setup as a new template.</p>
				</div>

				<!-- List saved templates section -->
				<div class="pc-template-list-box">
					<div class="pc-box-header">
						<h4 style="margin: 0;">Available Templates</h4>
					</div>
					
					<div v-if="templatesLoading" class="pc-state-message muted">
						<div class="pc-spinner"></div> Loading templates...
					</div>
					<div v-else-if="templatesError" class="pc-state-message error">
						{{ templatesError }}
					</div>
					<div v-else>
						<div v-if="templates.length === 0" class="pc-empty-state muted">
							<span style="font-size: 24px; display: block; margin-bottom: 8px;">📋</span>
							No templates saved yet. Save the current board setup below.
						</div>
						<div v-else class="pc-template-cards">
								<div v-for="t in templates" :key="t.id" class="pc-template-card">
									<div class="pc-template-card-info">
										<strong class="pc-template-name">{{ t.name }}</strong>
										<span class="pc-template-meta muted">By {{ t.createdBy }} · {{ formatIso(t.createdAt) }}</span>
									</div>
									<div class="pc-template-card-actions">
										<NcButton
											type="primary"
											size="small"
											:loading="applyingTemplateId === t.id"
											:disabled="(!!applyingTemplateId && applyingTemplateId !== t.id) || t.canApply === false"
											:title="t.canApply === false ? 'Only project owners can apply templates' : ''"
											@click="applyTemplate(t)">
											<template #icon>✨</template>
											Apply Template
										</NcButton>
										<NcButton
											type="tertiary"
											size="small"
											:disabled="t.canDelete === false"
											:title="t.canDelete === false ? 'You can only delete templates you created' : 'Delete template'"
											aria-label="Delete template"
											@click="deleteTemplate(t)">
											<template #icon>
												<span style="color: var(--color-error); font-size: 16px;">🗑️</span>
											</template>
										</NcButton>
									</div>
								</div>
						</div>
					</div>
				</div>

				<hr class="pc-modal-divider">

				<!-- Create new template section -->
				<div class="pc-template-create-box">
					<h4 style="margin: 0 0 12px;">Save Current Board as Template</h4>
					<p class="muted" style="margin: 0 0 12px; font-size: 13px;">This will capture the current stacks, cards, roles, and permission settings.</p>
					<div class="pc-template-create-row">
						<NcTextField class="pc-flex-1" v-model="templateName" label="Template name" :show-label="false" placeholder="e.g., Standard Flow v2" />
						<NcButton type="secondary" :loading="savingTemplate" :disabled="!templateName.trim()" @click="saveTemplate">
							<template #icon>💾</template>
							Save as Template
						</NcButton>
					</div>
				</div>

				<div class="pc-modal-footer">
					<NcButton @click="showTemplatesModal = false">Close</NcButton>
				</div>
			</div>
		</NcModal>

	</div>
</template>

<script>
import NcButton from '@nextcloud/vue/components/NcButton'
import NcColorPicker from '@nextcloud/vue/components/NcColorPicker'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcModal from '@nextcloud/vue/components/NcModal'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { showError, showSuccess } from '@nextcloud/dialogs'

import { DeckService } from '../../Services/deck.js'
import { DeckTemplatesService } from '../../Services/deckTemplates.js'

const deckService = DeckService.getInstance()
const deckTemplatesService = DeckTemplatesService.getInstance()

export default {
	name: 'DeckCardPolicyManager',
	components: { NcButton, NcColorPicker, NcSelect, NcModal, NcTextField },
	props: {
		boardId: { type: [String, Number], required: true },
		members: { type: Array, default: () => [] },
	},
	data() {
		return {
			loading: false,
			error: '',
			
			settings: { permissionMode: 'legacy', approvedStackId: null },
			roles: [],
			memberships: [],
			defaults: { move: [], approve: [] },
			defaultRoleKeys: { move: [], approve: [] },
			
			cards: [],
			stacks: [],
			
			// UI State
			cardSearch: '',
			stackFilter: 0,
			showOnlyCustom: false,
			selectedCardIds: [],
			
			// Modals
			showDefaultsModal: false,
			showMembersModal: false,
			showTemplatesModal: false,
			membersModalTab: 'members',

			// Templates
			templates: [],
			templatesLoading: false,
			templatesError: '',
			templateName: '',
			savingTemplate: false,
			applyingTemplateId: null,
			
			// Forms
			newMembership: { role: null, user: null },
			newRole: { name: '', roleKey: '', color: '#111111' },
			newRoleKeyTouched: false,
			bulkEdits: { move: [], approve: [] },
			
			// Spinners
			savingDefaults: false,
			savingBulk: false,
			creatingRole: false,
		}
	},
	computed: {
		boardIdNum() { return Number(this.boardId) },
		roleOptions() { return this.roles.map(r => ({ value: r.roleKey, label: r.name })) },
		roleNameById() {
			const map = {}
			for (const r of this.roles) map[r.id] = r.name
			return map
		},
		memberOptions() {
			return (this.members || []).map(m => ({ value: m.id, label: m.displayName || m.id }))
		},
		canAddMembership() { return !!(this.newMembership.role && this.newMembership.user) },
		roleByKey() {
			const map = {}
			for (const r of this.roles) map[r.roleKey] = r
			return map
		},
		filteredCards() {
			const q = (this.cardSearch || '').toLowerCase()
			const filtered = (this.cards || [])
				.filter(c => (this.stackFilter && Number(c.stackId) !== Number(this.stackFilter)) ? false : true)
				.filter(c => (this.showOnlyCustom ? !!c.hasExplicitPolicy : true))
				.filter(c => (q ? String(c.title || '').toLowerCase().includes(q) : true))

			return filtered.sort((a, b) => {
				const sa = this.getStackTitle(a.stackId)
				const sb = this.getStackTitle(b.stackId)
				if (sa !== sb) return sa.localeCompare(sb)
				return String(a.title || '').localeCompare(String(b.title || ''))
			})
		},
		allVisibleSelected() {
			if (this.filteredCards.length === 0) return false
			return this.filteredCards.every(c => this.selectedCardIds.includes(c.id))
		},
	},
	watch: {
		showMembersModal(val) {
			if (val) {
				this.membersModalTab = 'members'
			}
		},
		boardId: {
			handler() { this.load() },
			immediate: true,
		},
		selectedCardIds(newIds) {
			// Auto-fill bulk edits if exactly 1 is selected or if we want to intelligently pre-fill
			if (newIds.length === 1) {
				const card = this.cards.find(c => c.id === newIds[0])
				if (card) {
					const mKeys = card.hasExplicitPolicy ? (card.policy?.move || []) : (this.defaultRoleKeys.move || [])
					const aKeys = card.hasExplicitPolicy ? (card.policy?.approve || []) : (this.defaultRoleKeys.approve || [])
					this.bulkEdits.move = mKeys.map(v => ({ value: v, label: this.roleOptions.find(o => o.value === v)?.label || v }))
					this.bulkEdits.approve = aKeys.map(v => ({ value: v, label: this.roleOptions.find(o => o.value === v)?.label || v }))
				}
			} else if (newIds.length === 0) {
				this.bulkEdits = { move: [], approve: [] }
			}
		},
		'newRole.name'(val) {
			if (this.newRoleKeyTouched) return
			this.newRole.roleKey = this.slugifyRoleKey(val)
		},
	},
	methods: {
		unwrap(data) { return data?.ocs?.data || data },
		normStr(input) {
			return String(input || '')
				.trim()
				.toLowerCase()
				.replace(/\s+/g, ' ')
		},
		formatIso(iso) {
			if (!iso) return ''
			try {
				return new Date(iso).toLocaleString()
			} catch (e) {
				return String(iso)
			}
		},
		slugifyRoleKey(input) {
			const raw = String(input || '').trim().toLowerCase()
			return raw
				.replace(/\s+/g, '_')
				.replace(/[^a-z0-9_-]/g, '')
				.replace(/_+/g, '_')
				.replace(/^-+/, '')
				.replace(/^_+/, '')
				.replace(/-+$/, '')
				.replace(/_+$/, '')
		},
		resetNewRoleForm() {
			this.newRole = { name: '', roleKey: '', color: '#111111' }
			this.newRoleKeyTouched = false
		},
		async load() {
			this.loading = true
			this.error = ''
			try {
				const [raw, stacks] = await Promise.all([
					deckService.getCardPolicy(this.boardIdNum),
					deckService.listStacks(this.boardIdNum),
				])
				const data = this.unwrap(raw)
				this.settings = data.settings || { permissionMode: 'legacy', approvedStackId: null }
				this.roles = data.roles || []
				this.memberships = data.memberships || []
				this.defaultRoleKeys = data.defaultRoleKeys || { move: [], approve: [] }
				this.defaults = {
					move: (this.defaultRoleKeys.move || []).map(v => ({ value: v, label: this.roleOptions.find(o => o.value === v)?.label || v })),
					approve: (this.defaultRoleKeys.approve || []).map(v => ({ value: v, label: this.roleOptions.find(o => o.value === v)?.label || v })),
				}
				this.stacks = (stacks || []).map(s => ({ id: Number(s.id), title: String(s.title || '') }))
				this.cards = data.cards || []
				
				this.selectedCardIds = this.selectedCardIds.filter(id => this.cards.some(c => c.id === id))
			} catch (e) {
				this.error = e?.response?.data?.ocs?.data?.message || e?.response?.data?.message || e?.message || 'Error loading permissions'
			} finally {
				this.loading = false
			}
		},
		async openTemplates() {
			this.showTemplatesModal = true
			await this.loadTemplates()
		},
		async loadTemplates() {
			this.templatesLoading = true
			this.templatesError = ''
			try {
				this.templates = await deckTemplatesService.list(this.boardIdNum)
			} catch (e) {
				this.templatesError = e?.response?.data?.message || e?.response?.data?.error || e?.message || 'Failed to load templates'
			} finally {
				this.templatesLoading = false
			}
		},
		async saveTemplate() {
			this.savingTemplate = true
			try {
				await deckTemplatesService.createFromBoard(this.boardIdNum, this.templateName.trim())
				showSuccess('Template saved')
				this.templateName = ''
				await this.loadTemplates()
			} catch (e) {
				showError(e?.response?.data?.message || e?.response?.data?.error || e?.message || 'Failed to save template')
			} finally {
				this.savingTemplate = false
			}
		},
		async deleteTemplate(t) {
			if (!t?.id) return
			try {
				await deckTemplatesService.delete(t.id, this.boardIdNum)
				showSuccess('Template deleted')
				await this.loadTemplates()
			} catch (e) {
				showError(e?.response?.data?.message || e?.response?.data?.error || e?.message || 'Failed to delete template')
			}
		},
		async applyTemplate(t) {
			if (!t?.id) return
			if (t?.canApply === false) {
				showError('Only project owners can apply templates')
				return
			}
			if (this.applyingTemplateId) return
			if (!confirm(`Apply template "${t.name}" to this board? This can create missing roles, stacks, and cards.`)) return
			this.applyingTemplateId = t.id
			try {
				const tpl = await deckTemplatesService.get(t.id, this.boardIdNum)
				const payload = tpl?.payload
				if (!payload || typeof payload !== 'object') {
					throw new Error('Invalid template payload')
				}

				let rawState = await deckService.getCardPolicy(this.boardIdNum)
				let state = this.unwrap(rawState)
				const mode = String(state?.settings?.permissionMode || '')
				if (mode !== 'card_policy') {
					await deckService.enableCardPolicy(this.boardIdNum)
					rawState = await deckService.getCardPolicy(this.boardIdNum)
					state = this.unwrap(rawState)
				}

				const report = {
					createdRoles: 0,
					createdStacks: 0,
					createdCards: 0,
					appliedCardPolicies: 0,
					clearedCardPolicies: 0,
					skippedCardPolicies: 0,
					skippedCards: 0,
					ambiguousCards: 0,
					missingRoleKeys: new Set(),
				}

				// Roles
				const existingRoleKeys = new Set((state?.roles || []).map(r => String(r.roleKey || '')))
				for (const r of (payload.roles || [])) {
					const roleKey = String(r?.roleKey || '').trim()
					if (!roleKey) continue
					if (existingRoleKeys.has(roleKey)) continue
					await deckService.createCardPolicyRole(this.boardIdNum, {
						roleKey,
						name: String(r?.name || roleKey),
						color: String(r?.color || '#111111'),
					})
					report.createdRoles++
					existingRoleKeys.add(roleKey)
				}

				// Refresh state after role creation
				rawState = await deckService.getCardPolicy(this.boardIdNum)
				state = this.unwrap(rawState)
				const finalRoleKeys = new Set((state?.roles || []).map(r => String(r.roleKey || '')))

				// Defaults
				const defaults = payload.defaults || {}
				const move = (defaults.move || []).map(String).filter(k => finalRoleKeys.has(k))
				const approve = (defaults.approve || []).map(String).filter(k => finalRoleKeys.has(k))
				const view = (defaults.view || []).map(String).filter(k => finalRoleKeys.has(k))
				await deckService.updateCardPolicyDefaults(this.boardIdNum, { move, approve, view })

				// Stacks
				let stacks = await deckService.listStacks(this.boardIdNum)
				const stackIdByTitle = new Map(stacks.map(s => [this.normStr(s.title), Number(s.id)]))
				for (const s of (payload.stacks || [])) {
					const title = String(s?.title || '').trim()
					if (!title) continue
					const key = this.normStr(title)
					if (stackIdByTitle.has(key)) continue
					const created = await deckService.createStack(this.boardIdNum, title, Number(s?.order ?? 999))
					const createdId = Number(created?.id)
					if (createdId > 0) {
						report.createdStacks++
						stackIdByTitle.set(key, createdId)
					}
				}

				stacks = await deckService.listStacks(this.boardIdNum)
				const refreshedStackIdByTitle = new Map(stacks.map(s => [this.normStr(s.title), Number(s.id)]))

				// Cards (use card-policy state because it returns all cards + ids)
				rawState = await deckService.getCardPolicy(this.boardIdNum)
				state = this.unwrap(rawState)
				let boardCards = (state?.cards || []).map(c => ({
					id: Number(c.id),
					title: String(c.title || ''),
					stackId: Number(c.stackId || 0),
				}))

				const findCardsByTitle = (title) => {
					const k = this.normStr(title)
					return boardCards.filter(c => this.normStr(c.title) === k)
				}
				const findCardInStack = (title, stackId) => {
					const k = this.normStr(title)
					return boardCards.find(c => c.stackId === Number(stackId) && this.normStr(c.title) === k) || null
				}

				for (const c of (payload.cards || [])) {
					const title = String(c?.title || '').trim()
					const stackTitle = String(c?.stackTitle || '').trim()
					if (!title || !stackTitle) {
						report.skippedCards++
						continue
					}
					const desiredStackId = refreshedStackIdByTitle.get(this.normStr(stackTitle))
					let targetCard = desiredStackId ? findCardInStack(title, desiredStackId) : null
					if (!targetCard) {
						const matches = findCardsByTitle(title)
						if (matches.length === 1) {
							// Card exists elsewhere: keep it in its current stack
							targetCard = matches[0]
						} else if (matches.length > 1) {
							// Ambiguous: create a new card in the template stack if possible
							report.ambiguousCards++
							if (desiredStackId) {
								const created = await deckService.createCard(desiredStackId, title, Number(c?.order ?? 999), String(c?.description || ''))
								const createdId = Number(created?.id)
								if (createdId > 0) {
									report.createdCards++
									targetCard = { id: createdId, title, stackId: desiredStackId }
									boardCards.push(targetCard)
								}
							}
						} else {
							// Not found: create in the template stack
							if (desiredStackId) {
								const created = await deckService.createCard(desiredStackId, title, Number(c?.order ?? 999), String(c?.description || ''))
								const createdId = Number(created?.id)
								if (createdId > 0) {
									report.createdCards++
									targetCard = { id: createdId, title, stackId: desiredStackId }
									boardCards.push(targetCard)
								}
							}
						}
					}

					if (!targetCard?.id) {
						report.skippedCards++
						continue
					}

					const policy = c?.policy || {}
					const moveKeys = (policy.move || []).map(String)
					const approveKeys = (policy.approve || []).map(String)
					const viewKeys = (policy.view || []).map(String)
					const hadAnyKeys = moveKeys.length > 0 || approveKeys.length > 0 || viewKeys.length > 0
					const filteredMove = moveKeys.filter(k => finalRoleKeys.has(k))
					const filteredApprove = approveKeys.filter(k => finalRoleKeys.has(k))
					const filteredView = viewKeys.filter(k => finalRoleKeys.has(k))

					for (const k of [...moveKeys, ...approveKeys, ...viewKeys]) {
						if (k && !finalRoleKeys.has(k)) report.missingRoleKeys.add(k)
					}

					if (filteredMove.length || filteredApprove.length || filteredView.length) {
						await deckService.setCardPolicy(this.boardIdNum, Number(targetCard.id), {
							move: filteredMove,
							approve: filteredApprove,
							view: filteredView,
						})
						report.appliedCardPolicies++
					} else if (!hadAnyKeys) {
						await deckService.clearCardPolicy(this.boardIdNum, Number(targetCard.id))
						report.clearedCardPolicies++
					} else {
						report.skippedCardPolicies++
					}
				}

				const missing = Array.from(report.missingRoleKeys)
				const msg = `Applied template. Roles +${report.createdRoles}, stacks +${report.createdStacks}, cards +${report.createdCards}, policies set ${report.appliedCardPolicies}, cleared ${report.clearedCardPolicies}, skipped ${report.skippedCardPolicies}.` +
					(missing.length ? ` Missing role keys skipped: ${missing.join(', ')}.` : '') +
					(report.ambiguousCards ? ` Ambiguous titles: ${report.ambiguousCards} (created new cards in template stacks).` : '')
				showSuccess(msg)
				await this.load()
			} catch (e) {
				showError(e?.response?.data?.message || e?.response?.data?.ocs?.data?.message || e?.message || 'Failed to apply template')
			} finally {
				this.applyingTemplateId = null
			}
		},
		getStackTitle(stackId) {
			const s = this.stacks.find(s => Number(s.id) === Number(stackId))
			return s ? s.title : 'Unknown'
		},
		getMemberDisplayName(participant) {
			const m = this.members.find(x => x.id === participant || x.uid === participant)
			return m ? (m.displayName || m.uid || m.id) : participant
		},
		roleColorById(roleId) { return this.roles.find(x => x.id === roleId)?.color || 'var(--color-border)' },
		chipStyleByRoleId(roleId) { return { borderColor: this.roleColorById(roleId) } },
		roleNameByKey(roleKey) { return this.roleByKey[roleKey]?.name || roleKey },
		roleColorByKey(roleKey) { return this.roleByKey[roleKey]?.color || 'var(--color-border)' },
		chipStyleByKey(roleKey) { return { borderColor: this.roleColorByKey(roleKey) } },
		
		getEffectivePerms(card, type) {
			if (card.hasExplicitPolicy && card.policy) {
				return card.policy[type] || []
			}
			return this.defaultRoleKeys[type] || []
		},

		// Interactions
		isSelected(cardId) { return this.selectedCardIds.includes(cardId) },
		toggleSelection(cardId, event) {
			// Ignore if clicking on buttons or checkboxes directly
			if (['INPUT', 'BUTTON', 'A'].includes(event.target.tagName)) return;
			
			const idx = this.selectedCardIds.indexOf(cardId)
			if (idx === -1) this.selectedCardIds.push(cardId)
			else this.selectedCardIds.splice(idx, 1)
		},
		toggleSelectAllVisible(e) {
			if (e.target.checked) {
				const visibleIds = this.filteredCards.map(c => c.id)
				const newSet = new Set([...this.selectedCardIds, ...visibleIds])
				this.selectedCardIds = Array.from(newSet)
			} else {
				const visibleIds = new Set(this.filteredCards.map(c => c.id))
				this.selectedCardIds = this.selectedCardIds.filter(id => !visibleIds.has(id))
			}
		},

		// Actions
		async saveDefaults() {
			this.savingDefaults = true
			try {
				await deckService.updateCardPolicyDefaults(this.boardIdNum, {
					move: this.defaults.move.map(o => o.value),
					approve: this.defaults.approve.map(o => o.value),
				})
				showSuccess('Board defaults updated')
				this.showDefaultsModal = false
				await this.load()
			} catch (e) {
				showError(e?.response?.data?.ocs?.data?.message || 'Error saving defaults')
			} finally {
				this.savingDefaults = false
			}
		},
		async resetCard(card) {
			try {
				await deckService.clearCardPolicy(this.boardIdNum, card.id)
				showSuccess('Card reset to default')
				await this.load()
			} catch (e) {
				showError(e?.response?.data?.ocs?.data?.message || 'Error resetting card')
			}
		},
		async saveBulk() {
			if (this.selectedCardIds.length === 0) return
			this.savingBulk = true
			let errors = 0
			try {
				const payload = {
					move: this.bulkEdits.move.map(o => o.value),
					approve: this.bulkEdits.approve.map(o => o.value),
				}
				for (let i = 0; i < this.selectedCardIds.length; i += 5) {
					const batch = this.selectedCardIds.slice(i, i + 5)
					await Promise.all(batch.map(async cardId => {
						try { await deckService.setCardPolicy(this.boardIdNum, cardId, payload) } 
						catch (e) { errors++ }
					}))
				}
				
				if (errors > 0) showError(`Failed to update ${errors} cards`)
				else showSuccess(`Updated ${this.selectedCardIds.length} cards`)
				
				this.selectedCardIds = []
				await this.load()
			} finally {
				this.savingBulk = false
			}
		},
		async resetBulk() {
			if (this.selectedCardIds.length === 0) return
			this.savingBulk = true
			let errors = 0
			try {
				for (let i = 0; i < this.selectedCardIds.length; i += 5) {
					const batch = this.selectedCardIds.slice(i, i + 5)
					await Promise.all(batch.map(async cardId => {
						try { await deckService.clearCardPolicy(this.boardIdNum, cardId) } 
						catch (e) { errors++ }
					}))
				}
				
				if (errors > 0) showError(`Failed to reset ${errors} cards`)
				else showSuccess(`Reset ${this.selectedCardIds.length} cards`)
				
				this.selectedCardIds = []
				await this.load()
			} finally {
				this.savingBulk = false
			}
		},

		// Roles/Members
		async enable() {
			try {
				await deckService.enableCardPolicy(this.boardIdNum)
				showSuccess('Card policies enabled')
				await this.load()
			} catch (e) {
				showError(e?.response?.data?.ocs?.data?.message || 'Error')
			}
		},
		async addMembership() {
			try {
				await deckService.addCardPolicyMembership(this.boardIdNum, {
					roleKey: this.newMembership.role.value,
					participant: this.newMembership.user.value,
					participantType: 0,
				})
				this.newMembership.role = null
				this.newMembership.user = null
				showSuccess('Member added')
				await this.load()
			} catch (e) {
				showError(e?.response?.data?.ocs?.data?.message || 'Error adding member')
			}
		},
		async removeMembership(m) {
			try {
				await deckService.deleteCardPolicyMembership(this.boardIdNum, m.id)
				showSuccess('Member removed')
				await this.load()
			} catch (e) {
				showError(e?.response?.data?.ocs?.data?.message || 'Error removing member')
			}
		},
		async createRole() {
			if (this.creatingRole) return
			const name = String(this.newRole.name || '').trim()
			const roleKey = String(this.newRole.roleKey || '').trim()
			const color = String(this.newRole.color || '').trim()
			if (!name || !roleKey) return
			this.creatingRole = true
			try {
				const raw = await deckService.createCardPolicyRole(this.boardIdNum, { roleKey, name, color })
				this.unwrap(raw)
				showSuccess('Role created')
				this.resetNewRoleForm()
				await this.load()
			} catch (e) {
				showError(e?.response?.data?.ocs?.data?.message || 'Error creating role')
			} finally {
				this.creatingRole = false
			}
		},
	},
}
</script>

<style scoped>
/* Base Setup */
.pc-policy-app {
	--pc-border: var(--color-border, #ededed);
	--pc-bg: var(--color-main-background, #ffffff);
	--pc-bg-hover: var(--color-background-hover, #f5f5f5);
	--pc-text: var(--color-main-text, #222);
	--pc-text-muted: var(--color-text-maxcontrast, #777);
	--pc-primary: var(--color-primary-element, #0082c9);
	--pc-primary-text: var(--color-primary-text, #ffffff);
	--pc-radius: var(--border-radius-large, 8px);
	
	position: relative;
	height: calc(100vh - 120px);
	min-height: 500px;
	display: flex;
	flex-direction: column;
	background: var(--pc-bg);
	border: 1px solid var(--pc-border);
	border-radius: var(--pc-radius);
	box-shadow: 0 2px 14px rgba(0,0,0,0.05);
	overflow: hidden;
}

/* States */
.pc-loading-state, .pc-error-state, .pc-enable-state {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	height: 100%;
	padding: 40px;
	text-align: center;
}
.pc-enable-icon, .pc-error-icon {
	font-size: 48px;
	margin-bottom: 24px;
}

/* Header */
.pc-policy-container {
	display: flex;
	flex-direction: column;
	height: 100%;
	position: relative;
}
.pc-app-header {
	padding: 24px 32px;
	display: flex;
	justify-content: space-between;
	align-items: flex-start;
	background: var(--pc-bg);
	border-bottom: 1px solid var(--pc-border);
	z-index: 10;
}
.pc-header-titles h2 { margin: 0 0 8px 0; font-size: 24px; font-weight: bold; color: var(--pc-text); }
.pc-header-titles p { margin: 0; color: var(--pc-text-muted); font-size: 14px; }
.pc-header-actions { display: flex; gap: 12px; }
.pc-emoji-icon { font-size: 16px; margin-right: 6px; }

/* Toolbar */
.pc-toolbar {
	padding: 16px 32px;
	display: flex;
	justify-content: space-between;
	align-items: center;
	background: var(--pc-bg-hover);
	border-bottom: 1px solid var(--pc-border);
}
.pc-toolbar-search {
	position: relative;
	width: 300px;
}
.pc-search-icon {
	position: absolute;
	left: 12px;
	top: 50%;
	transform: translateY(-50%);
	font-size: 14px;
	opacity: 0.5;
}
.pc-search-input {
	width: 100%;
	padding: 8px 16px 8px 36px;
	border: 1px solid var(--pc-border);
	border-radius: 20px;
	background: var(--pc-bg);
	color: var(--pc-text);
	transition: border-color 0.2s;
}
.pc-search-input:focus { border-color: var(--pc-primary); outline: none; }
.pc-toolbar-filters {
	display: flex;
	align-items: center;
	gap: 24px;
}
.pc-stack-select {
	padding: 8px 32px 8px 16px;
	border: 1px solid var(--pc-border);
	border-radius: 8px;
	background: var(--pc-bg);
}
.pc-filter-checkbox {
	display: flex;
	align-items: center;
	gap: 8px;
	font-size: 14px;
	cursor: pointer;
	color: var(--pc-text);
}

/* Data Grid (The Main Show) */
.pc-data-grid-wrapper {
	flex: 1;
	overflow: auto;
	background: var(--pc-bg);
}
.pc-data-grid {
	width: 100%;
	border-collapse: collapse;
	text-align: left;
}
.pc-data-grid th {
	position: sticky;
	top: 0;
	background: var(--pc-bg);
	padding: 12px 16px;
	font-size: 13px;
	font-weight: 600;
	color: var(--pc-text-muted);
	border-bottom: 2px solid var(--pc-border);
	z-index: 5;
	white-space: nowrap;
}
.pc-data-grid td {
	padding: 14px 16px;
	border-bottom: 1px solid var(--pc-border);
	vertical-align: middle;
}
.pc-data-grid tr {
	transition: background-color 0.15s;
	cursor: pointer;
}
.pc-data-grid tr:hover { background-color: var(--pc-bg-hover); }
.pc-data-grid tr.is-selected {
	background-color: rgba(0, 130, 201, 0.04);
}
.pc-data-grid tr.is-selected td {
	border-bottom-color: rgba(0, 130, 201, 0.1);
}

/* Columns */
.pc-col-check { width: 40px; text-align: center; }
.pc-col-check input { width: 16px; height: 16px; cursor: pointer; }
.pc-col-name { min-width: 250px; font-weight: 500; color: var(--pc-text); }
.pc-col-stack { width: 150px; }
.pc-col-perms { width: 250px; }
.pc-col-actions { width: 60px; text-align: right; }

.pc-card-title-wrap {
	display: flex;
	align-items: center;
	gap: 12px;
}
.pc-badge-custom {
	font-size: 10px;
	padding: 2px 6px;
	border-radius: 4px;
	background: var(--pc-primary);
	color: var(--pc-primary-text);
	text-transform: uppercase;
	font-weight: bold;
	letter-spacing: 0.5px;
}
.pc-stack-label {
	font-size: 13px;
	color: var(--pc-text-muted);
}
.muted-dash { color: var(--pc-border); font-weight: bold; }

/* Chips inside table */
.pc-role-chips {
	display: flex;
	flex-wrap: wrap;
	gap: 6px;
}
.pc-role-chip {
	display: inline-flex;
	align-items: center;
	gap: 6px;
	padding: 2px 8px;
	border-radius: 999px;
	border: 1px solid var(--pc-border);
	font-size: 12px;
	background: var(--pc-bg);
}
.pc-dot { width: 8px; height: 8px; border-radius: 50%; }

/* Floating Action Bar */
.pc-floating-action-bar {
	position: absolute;
	bottom: 24px;
	left: 50%;
	transform: translateX(-50%);
	background: var(--pc-bg);
	border: 1px solid var(--pc-border);
	border-radius: 12px;
	box-shadow: 0 10px 30px rgba(0,0,0,0.15);
	display: flex;
	align-items: center;
	padding: 12px 24px;
	gap: 32px;
	z-index: 50;
	white-space: nowrap;
}
.pc-fab-left {
	display: flex;
	flex-direction: column;
	gap: 4px;
}
.pc-fab-count { font-weight: bold; color: var(--pc-primary); font-size: 15px; }
.pc-fab-clear {
	background: none; border: none; padding: 0;
	color: var(--pc-text-muted); font-size: 12px; cursor: pointer;
	text-decoration: underline; text-align: left;
}
.pc-fab-controls {
	display: flex;
	gap: 24px;
	border-left: 1px solid var(--pc-border);
	border-right: 1px solid var(--pc-border);
	padding: 0 32px;
}
.pc-fab-field {
	display: flex;
	align-items: center;
	gap: 12px;
}
.pc-fab-field label { font-weight: 600; font-size: 14px; }
/* Override specific to select in FAB */
.pc-fab-field ::v-deep .multiselect { min-width: 220px; }
.pc-fab-actions {
	display: flex;
	gap: 12px;
}

/* Modals */
.pc-modal-content { padding: 24px; }
.pc-modal-header-desc { margin-bottom: 24px; color: var(--pc-text-muted); line-height: 1.5; }
.pc-modal-field { margin-bottom: 24px; }
.pc-modal-field label { display: block; font-weight: bold; margin-bottom: 8px; }
.pc-modal-footer { display: flex; justify-content: flex-end; gap: 12px; margin-top: 32px; border-top: 1px solid var(--pc-border); padding-top: 24px; }

/* Members Modal Specifics */
.pc-members-modal { padding: 0; display: flex; flex-direction: column; gap: 0; }
.pc-modal-tabs { display: flex; gap: 8px; padding: 12px 24px; border-bottom: 1px solid var(--pc-border); background: var(--pc-bg); }
.pc-modal-tab { appearance: none; border: 1px solid var(--pc-border); background: var(--pc-bg); color: var(--pc-text); border-radius: 999px; padding: 8px 12px; font-weight: bold; cursor: pointer; transition: background 0.15s ease, border-color 0.15s ease; }
.pc-modal-tab:hover { background: var(--pc-bg-hover); }
.pc-modal-tab--active { border-color: var(--pc-primary); box-shadow: 0 0 0 2px rgba(0, 130, 201, 0.15); }
.pc-add-role-box { padding: 24px; background: var(--pc-bg-hover); border-bottom: 1px solid var(--pc-border); }
.pc-add-role-box h4 { margin: 0 0 16px 0; font-size: 16px; }
.pc-add-role-row { display: flex; gap: 16px; align-items: flex-end; flex-wrap: wrap; }
.pc-role-color-field { width: 120px; }
.pc-role-color-label { display: block; font-weight: bold; margin-bottom: 8px; }
.pc-role-color-swatch { width: 36px; height: 36px; border-radius: 10px; border: 1px solid var(--pc-border); box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
.pc-help-muted { margin: 12px 0 0; color: var(--pc-text-muted); font-size: 13px; }
.pc-roles-list-box { padding: 24px; max-height: 320px; overflow-y: auto; }
.pc-roles-list-box h4 { margin: 0 0 16px 0; font-size: 16px; }
.pc-roles-chips { display: flex; flex-wrap: wrap; gap: 10px; }
.pc-role-key { margin-left: 6px; color: var(--pc-text-muted); font-weight: 600; }
.pc-add-member-box { padding: 24px; background: var(--pc-bg-hover); border-bottom: 1px solid var(--pc-border); }
.pc-add-member-box h4 { margin: 0 0 16px 0; font-size: 16px; }
.pc-add-member-row { display: flex; gap: 16px; align-items: center; }
.pc-flex-1 { flex: 1; }

.pc-members-list-box { padding: 24px; max-height: 500px; overflow-y: auto; }
.pc-members-list-box h4 { margin: 0 0 16px 0; font-size: 16px; }
.pc-simple-table { width: 100%; border-collapse: collapse; }
.pc-simple-table td { padding: 12px 8px; border-bottom: 1px solid var(--pc-border); }
.pc-simple-td-role { width: 200px; }
.pc-simple-td-action { width: 80px; text-align: right; }
.pc-danger-btn {
	background: none; border: 1px solid var(--color-error); color: var(--color-error);
	border-radius: 4px; padding: 4px 12px; cursor: pointer; font-size: 12px; transition: all 0.2s;
}
.pc-danger-btn:hover { background: var(--color-error); color: white; }

/* Transitions */
.slide-up-enter-active, .slide-up-leave-active { transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
.slide-up-enter, .slide-up-leave-to { opacity: 0; transform: translate(-50%, 20px); }
</style>

<style scoped>
/* Templates Modal Styling */
.pc-templates-modal {
	padding: 16px 8px;
}
.pc-box-header {
	margin-bottom: 16px;
	padding-bottom: 8px;
}
.pc-template-cards {
	display: flex;
	flex-direction: column;
	gap: 12px;
}
.pc-template-card {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: 16px;
	background: var(--color-background-hover);
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	transition: border-color 0.2s;
}
.pc-template-card:hover {
	border-color: var(--color-primary-element);
}
.pc-template-card-info {
	display: flex;
	flex-direction: column;
	gap: 4px;
}
.pc-template-name {
	font-size: 16px;
	color: var(--color-main-text);
}
.pc-template-meta {
	font-size: 12px;
}
.pc-template-card-actions {
	display: flex;
	gap: 8px;
	align-items: center;
}
.pc-modal-divider {
	margin: 32px 0;
	border: 0;
	border-top: 1px solid var(--color-border);
}
.pc-template-create-row {
	display: flex;
	gap: 12px;
	align-items: center;
}
.pc-state-message {
	padding: 20px;
	text-align: center;
	border-radius: var(--border-radius-large);
	background: var(--color-background-hover);
}
.pc-state-message.error {
	color: var(--color-error);
	background: rgba(255, 0, 0, 0.05);
}
</style>
